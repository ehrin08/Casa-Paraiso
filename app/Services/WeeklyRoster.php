<?php

namespace App\Services;

use App\Models\StaffProfile;
use App\Models\StaffScheduleShift;
use App\Models\StaffScheduleWeek;
use App\Models\StaffWeeklySchedule;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeeklyRoster
{
    public function __construct(
        private readonly StaffScheduleConflictGuard $conflicts,
        private readonly ScheduleWindowResolver $windows,
        private readonly RosterShiftRules $rules,
    ) {}

    public function weekStart(string|Carbon $week): Carbon
    {
        return Carbon::parse($week, 'Asia/Manila')->startOfDay()->startOfWeek(Carbon::SUNDAY);
    }

    /** Reading a roster never initializes it. */
    public function week(string|Carbon $week): ?StaffScheduleWeek
    {
        return StaffScheduleWeek::query()->whereDate('week_start_date', $this->weekStart($week))->first();
    }

    public function snapshot(string|Carbon $week): array
    {
        $start = $this->weekStart($week);
        $roster = $this->week($start);
        [$source, $live] = $this->sourceShifts($start);
        $draft = $roster?->draft_initialized_at
            ? $this->rows($roster->shifts()->where('version', StaffScheduleShift::VERSION_DRAFT)->orderBy('id')->get())
            : collect();
        $until = StaffScheduleWeek::query()->whereNotNull('published_at')->where('week_start_date', '>', $start->toDateString())
            ->orderBy('week_start_date')->value('week_start_date');

        return [
            'roster' => $roster, 'start' => $start, 'until' => $until ? Carbon::parse($until, 'Asia/Manila')->startOfDay() : null,
            'source' => $source, 'live_shifts' => $live, 'draft_shifts' => $draft,
            'has_draft' => (bool) $roster?->draft_initialized_at,
            'has_changes' => (bool) $roster?->draft_initialized_at && $this->signature($draft) !== $this->signature($live),
            'revision' => $roster?->revision ?? 0,
            'editable' => $start->copy()->addWeek()->gt(now('Asia/Manila')->startOfDay()),
        ];
    }

    public function payload(string|Carbon $week): array
    {
        $state = $this->snapshot($week);
        $staff = StaffProfile::query()->with('user')->whereHas('user', fn ($query) => $query->where('is_active', true)->where('role', 'staff'))
            ->get()->sortBy('user.name')->values();

        return [
            'schedule_week_id' => $state['roster']?->id,
            'week_start' => $state['start']->toDateString(),
            'week_end' => $state['start']->copy()->addDays(6)->toDateString(),
            'published_at' => $state['roster']?->published_at?->toIso8601String(),
            'source_week' => $state['source']?->week_start_date->toDateString(),
            'has_draft' => $state['has_draft'], 'has_changes' => $state['has_changes'], 'revision' => $state['revision'],
            'resources' => $staff->map(fn (StaffProfile $person) => ['id' => $person->id, 'name' => $person->user?->name, 'subtitle' => $person->specialization ?: $person->position ?: __('Spa therapist')])->all(),
            'draft_shifts' => $state['draft_shifts']->all(),
            'published_shifts' => $state['live_shifts']->all(),
        ];
    }

    public function startDraft(string|Carbon $week, int $revision): StaffScheduleWeek
    {
        return DB::transaction(function () use ($week, $revision): StaffScheduleWeek {
            $roster = $this->lockWeek($week, $revision);
            if (! $roster->draft_initialized_at) {
                [, $candidate] = $this->sourceShifts($roster->week_start_date, false, true);
                $this->rules->validate($roster->week_start_date, $candidate);
                $this->replaceDraft($roster, $candidate);
            }

            return $roster;
        }, 3);
    }

    public function copyReview(string|Carbon $week): array
    {
        $state = $this->snapshot($week);
        $this->rules->assertEditableWeek($state['start']);
        [$source, $candidate] = $this->sourceShifts($state['start'], true, true);
        // Copying the current week must not rewrite elapsed days.
        $base = $state['has_draft'] ? $state['draft_shifts'] : $state['live_shifts'];
        $today = now('Asia/Manila')->toDateString();
        $candidate = $candidate->filter(fn ($row) => $row['schedule_date'] >= $today)
            ->concat($base->filter(fn ($row) => $row['schedule_date'] < $today))->values();
        $this->rules->validate($state['start'], $candidate);

        return [...$this->review($state, $candidate), 'copy_source' => $source, 'source_signature' => $this->signature($candidate)];
    }

    public function copyPrevious(string|Carbon $week, int $revision, string $sourceSignature): StaffScheduleWeek
    {
        return DB::transaction(function () use ($week, $revision, $sourceSignature): StaffScheduleWeek {
            $roster = $this->lockWeek($week, $revision);
            $review = $this->copyReview($roster->week_start_date);
            if (! hash_equals($review['source_signature'], $sourceSignature)) {
                throw ValidationException::withMessages(['roster' => __('The copy source changed. Review the source hours again before replacing this draft.')]);
            }
            $this->replaceDraft($roster, $review['candidate']);

            return $roster;
        }, 3);
    }

    public function saveShift(StaffScheduleWeek $roster, array $data, int $revision, StaffScheduleShift|int|null $shift = null): StaffScheduleShift
    {
        return $this->mutate($roster, $revision, function (StaffScheduleWeek $locked) use ($data, $shift): StaffScheduleShift {
            $this->rules->assertEditableStaff([(int) $data['staff_profile_id']]);
            $this->rules->assertEditableDate($locked->week_start_date, $data['schedule_date']);
            $existing = $shift ? $this->ownedShift($locked, $shift) : null;
            if ($existing) {
                $this->rules->assertEditableDate($locked->week_start_date, $existing->schedule_date->toDateString());
            }
            $row = $this->normalize($data);
            $candidate = $this->draftRows($locked)->reject(fn ($row) => $existing && $row['id'] === $existing->id)->push($row);
            $this->rules->validate($locked->week_start_date, $candidate);

            if ($existing) {
                $existing->update($row);

                return $existing;
            }

            return $locked->shifts()->create([...$row, 'version' => StaffScheduleShift::VERSION_DRAFT]);
        });
    }

    public function deleteShift(StaffScheduleWeek $roster, StaffScheduleShift|int $shift, int $revision): void
    {
        $this->mutate($roster, $revision, function (StaffScheduleWeek $locked) use ($shift): void {
            $target = $this->ownedShift($locked, $shift);
            $this->rules->assertEditableDate($locked->week_start_date, $target->schedule_date->toDateString());
            $target->delete();
        });
    }

    public function bulkReview(StaffScheduleWeek $roster, array $data, int $revision): array
    {
        $state = $this->snapshot($roster->week_start_date);
        $this->assertRevision($state['roster'], $revision);
        $this->assertDraft($state['roster']);
        $candidate = $this->bulkCandidate($state['roster'], $data);

        return $this->review($state, $candidate);
    }

    public function bulkReplace(StaffScheduleWeek $roster, array $data, int $revision): void
    {
        $this->mutate($roster, $revision, function (StaffScheduleWeek $locked) use ($data): void {
            $candidate = $this->bulkCandidate($locked, $data);
            $ids = array_map('intval', $data['staff_ids']);
            $locked->shifts()->where('version', StaffScheduleShift::VERSION_DRAFT)
                ->whereIn('staff_profile_id', $ids)->whereIn('schedule_date', $data['dates'])->delete();
            foreach ($candidate->filter(fn ($row) => in_array($row['staff_profile_id'], $ids, true)
                && in_array($row['schedule_date'], $data['dates'], true)) as $row) {
                $locked->shifts()->create([...$this->normalize($row), 'version' => StaffScheduleShift::VERSION_DRAFT]);
            }
        });
    }

    public function publishReview(StaffScheduleWeek $roster): array
    {
        $state = $this->snapshot($roster->week_start_date);
        $this->rules->assertEditableWeek($state['start']);
        $this->assertDraft($state['roster']);
        $candidate = $this->draftRows($state['roster'], true);
        $this->rules->validate($state['start'], $candidate);

        return $this->review($state, $candidate);
    }

    public function publish(StaffScheduleWeek $roster, int $userId, int $revision): void
    {
        try {
            DB::transaction(function () use ($roster, $userId, $revision): void {
                // Match booking's staff-first order. Protect new reservations even for therapists omitted from the draft.
                StaffProfile::withTrashed()->orderBy('id')->lockForUpdate()->get(['id']);
                $locked = StaffScheduleWeek::query()->lockForUpdate()->findOrFail($roster->id);
                $this->assertRevision($locked, $revision);
                $this->rules->assertEditableWeek($locked->week_start_date);
                $this->assertDraft($locked);
                $candidate = $this->draftRows($locked, true);
                $this->rules->validate($locked->week_start_date, $candidate);
                $next = StaffScheduleWeek::query()->whereNotNull('published_at')
                    ->where('week_start_date', '>', $locked->week_start_date->toDateString())
                    ->orderBy('week_start_date')->sharedLock()->first();
                $this->conflicts->throwIfConflicts($this->conflicts->rosterConflicts(
                    $locked->week_start_date, $next?->week_start_date, $candidate, true,
                ));
                $locked->shifts()->where('version', StaffScheduleShift::VERSION_PUBLISHED)->delete();
                foreach ($candidate as $row) {
                    $locked->shifts()->create([...$this->normalize($row), 'version' => StaffScheduleShift::VERSION_PUBLISHED]);
                }
                $locked->update(['published_at' => now(), 'published_by' => $userId, 'revision' => $locked->revision + 1]);
            }, 3);
        } finally {
            $this->windows->clearCache();
        }
    }

    public function review(array $state, Collection $candidate): array
    {
        $old = $state['live_shifts']->groupBy(fn ($row) => $row['staff_profile_id'].':'.$row['schedule_date']);
        $new = $candidate->groupBy(fn ($row) => $row['staff_profile_id'].':'.$row['schedule_date']);
        $changes = collect($old->keys()->merge($new->keys())->unique())->map(function (string $key) use ($old, $new): ?array {
            $before = $old->get($key, collect());
            $after = $new->get($key, collect());
            if ($this->signature($before) === $this->signature($after)) {
                return null;
            }
            [$staffId, $date] = explode(':', $key, 2);

            return ['staff_profile_id' => (int) $staffId, 'date' => $date, 'before' => $before, 'after' => $after];
        })->filter()->values();

        return [
            ...$state, 'candidate' => $candidate, 'changes' => $changes,
            'conflicts' => $this->conflicts->rosterConflicts($state['start'], $state['until'], $candidate),
        ];
    }

    public function signature(Collection $shifts): string
    {
        $rows = $shifts->map(fn ($row) => $this->normalize($row))
            ->sortBy(fn ($row) => implode('|', array_values($row)))->values()->all();

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private function sourceShifts(Carbon $start, bool $previous = false, bool $validateTimes = false): array
    {
        $source = StaffScheduleWeek::query()->whereNotNull('published_at')
            ->where('week_start_date', $previous ? '<' : '<=', $start->toDateString())->orderByDesc('week_start_date')->first();
        $rows = $source
            ? $source->shifts()->where('version', StaffScheduleShift::VERSION_PUBLISHED)->orderBy('id')->get()
            : StaffWeeklySchedule::query()->where('is_available', true)->orderBy('id')->get();
        if ($validateTimes) {
            $this->rules->assertStoredTimes($rows);
        }
        $candidate = $rows->map(function ($shift) use ($start): array {
            $weekday = $shift instanceof StaffWeeklySchedule ? $shift->day_of_week : $shift->schedule_date->dayOfWeek;

            return ['id' => $shift->id, ...$this->normalize([
                ...$shift->only(['staff_profile_id', 'start_time', 'end_time', 'ends_next_day']),
                'schedule_date' => $start->copy()->addDays($weekday)->toDateString(),
            ])];
        })->values();

        return [$source, $candidate];
    }

    private function bulkCandidate(StaffScheduleWeek $roster, array $data): Collection
    {
        $ids = array_map('intval', $data['staff_ids']);
        $dates = $data['dates'];
        foreach ($dates as $date) {
            $this->rules->assertEditableDate($roster->week_start_date, $date);
        }
        if ($data['mode'] !== 'clear') {
            $this->rules->assertEditableStaff($ids);
        }
        $candidate = $this->draftRows($roster)->reject(fn ($row) => in_array($row['staff_profile_id'], $ids, true) && in_array($row['schedule_date'], $dates, true))->values();
        if ($data['mode'] !== 'clear') {
            foreach ($ids as $id) {
                foreach ($dates as $date) {
                    $candidate->push($this->normalize([...$data, 'staff_profile_id' => $id, 'schedule_date' => $date]));
                }
            }
        }
        $this->rules->validate($roster->week_start_date, $candidate);

        return $candidate;
    }

    private function mutate(StaffScheduleWeek $roster, int $revision, Closure $callback): mixed
    {
        return DB::transaction(function () use ($roster, $revision, $callback): mixed {
            $locked = $this->lockWeek($roster->week_start_date, $revision);
            $this->assertDraft($locked);
            $result = $callback($locked);
            $locked->increment('revision');

            return $result;
        }, 3);
    }

    private function lockWeek(string|Carbon $week, int $revision): StaffScheduleWeek
    {
        $start = $this->weekStart($week);
        $this->rules->assertEditableWeek($start);
        $roster = StaffScheduleWeek::firstOrCreate(['week_start_date' => $start->toDateString()]);
        $roster = StaffScheduleWeek::query()->lockForUpdate()->findOrFail($roster->id);
        $this->assertRevision($roster, $revision);

        return $roster;
    }

    private function assertRevision(?StaffScheduleWeek $roster, int $revision): void
    {
        if (($roster?->revision ?? 0) !== $revision) {
            throw ValidationException::withMessages(['roster' => __('This schedule changed in another request. Reload and review the current draft before trying again.')]);
        }
    }

    private function assertDraft(?StaffScheduleWeek $roster): void
    {
        if (! $roster?->draft_initialized_at) {
            throw ValidationException::withMessages(['roster' => __('Start a draft before editing or publishing this week.')]);
        }
    }

    private function ownedShift(StaffScheduleWeek $roster, StaffScheduleShift|int $shift): StaffScheduleShift
    {
        return $roster->shifts()->whereKey($shift instanceof StaffScheduleShift ? $shift->id : $shift)
            ->where('version', StaffScheduleShift::VERSION_DRAFT)->lockForUpdate()->firstOrFail();
    }

    private function replaceDraft(StaffScheduleWeek $roster, Collection $candidate, bool $increment = true): void
    {
        $roster->shifts()->where('version', StaffScheduleShift::VERSION_DRAFT)->delete();
        foreach ($candidate as $row) {
            $roster->shifts()->create([...$this->normalize($row), 'version' => StaffScheduleShift::VERSION_DRAFT]);
        }
        $roster->update(['draft_initialized_at' => $roster->draft_initialized_at ?? now(), 'revision' => $roster->revision + ($increment ? 1 : 0)]);
    }

    private function draftRows(StaffScheduleWeek $roster, bool $validateTimes = false): Collection
    {
        $rows = $roster->shifts()->where('version', StaffScheduleShift::VERSION_DRAFT)->orderBy('id')->get();
        if ($validateTimes) {
            $this->rules->assertStoredTimes($rows);
        }

        return $this->rows($rows);
    }

    private function rows(Collection $models): Collection
    {
        return $models->map(fn (StaffScheduleShift $shift) => ['id' => $shift->id, ...$this->normalize([
            ...$shift->only(['staff_profile_id', 'start_time', 'end_time', 'ends_next_day']),
            'schedule_date' => $shift->schedule_date->toDateString(),
        ])])->values();
    }

    private function normalize(array $row): array
    {
        return [
            'staff_profile_id' => (int) $row['staff_profile_id'], 'schedule_date' => (string) $row['schedule_date'],
            'start_time' => substr((string) $row['start_time'], 0, 5), 'end_time' => substr((string) $row['end_time'], 0, 5),
            'ends_next_day' => (bool) ($row['ends_next_day'] ?? false),
        ];
    }
}
