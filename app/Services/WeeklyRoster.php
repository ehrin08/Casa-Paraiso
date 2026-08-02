<?php

namespace App\Services;

use App\Exceptions\StaffScheduleConflictException;
use App\Models\StaffProfile;
use App\Models\StaffScheduleShift;
use App\Models\StaffScheduleWeek;
use App\Models\StaffWeeklySchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeeklyRoster
{
    public function __construct(private readonly StaffScheduleConflictGuard $conflicts) {}

    public function week(string|Carbon $week): StaffScheduleWeek
    {
        $start = Carbon::parse($week)->startOfWeek(Carbon::SUNDAY)->toDateString();

        return StaffScheduleWeek::firstOrCreate(['week_start_date' => $start]);
    }

    /** @return array<string, mixed> */
    public function payload(string|Carbon $week): array
    {
        $roster = $this->week($week);
        $start = $roster->week_start_date->copy();
        $staff = StaffProfile::query()->with('user')->where('is_bookable', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))->get()->sortBy('user.name')->values();
        $draft = $roster->shifts()->where('version', StaffScheduleShift::VERSION_DRAFT)->get();
        $published = $roster->shifts()->where('version', StaffScheduleShift::VERSION_PUBLISHED)->get();

        return [
            'schedule_week_id' => $roster->id,
            'week_start' => $start->toDateString(),
            'week_end' => $start->copy()->addDays(6)->toDateString(),
            'published_at' => $roster->published_at?->toIso8601String(),
            'has_draft' => $draft->isNotEmpty(),
            'resources' => $staff->map(fn (StaffProfile $person) => ['id' => $person->id, 'name' => $person->user?->name, 'subtitle' => $person->specialization ?: $person->position ?: __('Spa therapist')])->all(),
            'draft_shifts' => $this->serialize($draft),
            'published_shifts' => $this->serialize($published),
        ];
    }

    public function copyPrevious(string|Carbon $week): StaffScheduleWeek
    {
        return DB::transaction(function () use ($week): StaffScheduleWeek {
            $roster = $this->week($week);
            $start = $roster->week_start_date->copy();
            $roster->shifts()->where('version', StaffScheduleShift::VERSION_DRAFT)->delete();
            $source = StaffScheduleWeek::query()->whereNotNull('published_at')->whereDate('week_start_date', '<', $start)->orderByDesc('week_start_date')->first();

            if ($source) {
                $source->shifts()->where('version', StaffScheduleShift::VERSION_PUBLISHED)->get()->each(function (StaffScheduleShift $shift) use ($roster, $start): void {
                    $date = $start->copy()->addDays(Carbon::parse($shift->schedule_date)->dayOfWeek);
                    $roster->shifts()->create([...$shift->only(['staff_profile_id', 'start_time', 'end_time', 'ends_next_day']), 'version' => StaffScheduleShift::VERSION_DRAFT, 'schedule_date' => $date->toDateString()]);
                });
            } else {
                StaffWeeklySchedule::query()->where('is_available', true)->get()->each(function (StaffWeeklySchedule $shift) use ($roster, $start): void {
                    $roster->shifts()->create(['staff_profile_id' => $shift->staff_profile_id, 'version' => StaffScheduleShift::VERSION_DRAFT, 'schedule_date' => $start->copy()->addDays($shift->day_of_week)->toDateString(), 'start_time' => $shift->start_time, 'end_time' => $shift->end_time, 'ends_next_day' => $shift->ends_next_day]);
                });
            }

            return $roster;
        });
    }

    public function saveShift(StaffScheduleWeek $roster, array $data): StaffScheduleShift
    {
        $this->validateShift($roster, $data);

        return $roster->shifts()->create([...$data, 'version' => StaffScheduleShift::VERSION_DRAFT, 'ends_next_day' => (bool) ($data['ends_next_day'] ?? false)]);
    }

    public function deleteShift(StaffScheduleWeek $roster, StaffScheduleShift $shift): void
    {
        abort_unless($shift->staff_schedule_week_id === $roster->id && $shift->version === StaffScheduleShift::VERSION_DRAFT, 404);
        $shift->delete();
    }

    /** @throws StaffScheduleConflictException */
    public function publish(StaffScheduleWeek $roster, int $userId): void
    {
        DB::transaction(function () use ($roster, $userId): void {
            $roster = StaffScheduleWeek::query()->lockForUpdate()->findOrFail($roster->id);
            $draft = $roster->shifts()->where('version', StaffScheduleShift::VERSION_DRAFT)->get();
            if ($draft->isEmpty()) {
                throw ValidationException::withMessages(['roster' => __('Add at least one therapist shift before publishing this week.')]);
            }
            $roster->shifts()->where('version', StaffScheduleShift::VERSION_PUBLISHED)->delete();
            foreach ($draft as $shift) {
                $roster->shifts()->create([...$shift->only(['staff_profile_id', 'schedule_date', 'start_time', 'end_time', 'ends_next_day']), 'version' => StaffScheduleShift::VERSION_PUBLISHED]);
            }
            $roster->update(['published_at' => now(), 'published_by' => $userId]);
            $draft->pluck('staff_profile_id')->unique()->each(fn (int $id) => $this->conflicts->assertFutureAppointmentsRemainCovered(StaffProfile::query()->findOrFail($id)));
        });
    }

    private function validateShift(StaffScheduleWeek $roster, array $data): void
    {
        $date = Carbon::parse($data['schedule_date']);
        if (! $date->betweenIncluded($roster->week_start_date, $roster->week_start_date->copy()->addDays(6))) {
            throw ValidationException::withMessages(['schedule_date' => __('Choose a date in this roster week.')]);
        }
        $start = $this->minutes($data['start_time']);
        $end = ! empty($data['ends_next_day']) ? 1440 : $this->minutes($data['end_time']);
        if ($start < 780 || $start % 30 || $end % 30 || $end <= $start || (! empty($data['ends_next_day']) && $data['end_time'] !== '00:00')) {
            throw ValidationException::withMessages(['start_time' => __('Use a non-overlapping 30-minute shift between 1:00 PM and midnight.')]);
        }
        $overlap = $roster->shifts()->where('version', StaffScheduleShift::VERSION_DRAFT)->where('staff_profile_id', $data['staff_profile_id'])->whereDate('schedule_date', $date)->get()->contains(function (StaffScheduleShift $existing) use ($start, $end): bool {
            $existingStart = $this->minutes((string) $existing->start_time);
            $existingEnd = $existing->ends_next_day ? 1440 : $this->minutes((string) $existing->end_time);

            return $start < $existingEnd && $end > $existingStart;
        });
        if ($overlap) {
            throw ValidationException::withMessages(['start_time' => __('This shift overlaps another draft shift for the therapist.')]);
        }
    }

    private function minutes(string $value): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($value, 0, 5)));

        return ($h * 60) + $m;
    }

    private function serialize(Collection $shifts): array
    {
        return $shifts->map(fn (StaffScheduleShift $shift) => ['id' => $shift->id, 'staff_profile_id' => $shift->staff_profile_id, 'schedule_date' => $shift->schedule_date->toDateString(), 'start_time' => substr((string) $shift->start_time, 0, 5), 'end_time' => substr((string) $shift->end_time, 0, 5), 'ends_next_day' => $shift->ends_next_day])->values()->all();
    }
}
