<?php

namespace App\Services;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RosterShiftRules
{
    public function __construct(private readonly ScheduleWindowResolver $windows) {}

    public function assertEditableStaff(array $ids): void
    {
        $valid = StaffProfile::query()->whereKey($ids)->where('staff_type', StaffProfile::TYPE_THERAPIST)
            ->whereHas('user', fn ($query) => $query->where('is_active', true)->where('role', User::ROLE_STAFF))->count();
        if ($valid !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['staff_ids' => __('Choose active therapist accounts. Booking settings are managed separately.')]);
        }
    }

    public function assertEditableWeek(Carbon $start): void
    {
        if ($start->copy()->addWeek()->lte(now('Asia/Manila')->startOfDay())) {
            throw ValidationException::withMessages(['week' => __('Past weeks are view-only. Choose the current week or a future week.')]);
        }
    }

    public function assertEditableDate(Carbon $start, string $date): void
    {
        $this->assertEditableWeek($start);
        if ($date < now('Asia/Manila')->toDateString() || $date < $start->toDateString() || $date > $start->copy()->addDays(6)->toDateString()) {
            throw ValidationException::withMessages(['schedule_date' => __('Choose today or a later date within this week.')]);
        }
    }

    public function assertStoredTimes(Collection $shifts): void
    {
        foreach ($shifts as $shift) {
            foreach (['start_time', 'end_time'] as $field) {
                if (! preg_match('/^\d{2}:\d{2}(?::00)?$/', (string) $shift[$field])) {
                    throw ValidationException::withMessages(['start_time' => __('Stored shift times must use whole minutes. Correct the source hours before copying or publishing.')]);
                }
            }
        }
    }

    /** Validate the complete candidate, including copied legacy ranges and split shifts. */
    public function validate(Carbon $week, Collection $shifts): void
    {
        $ids = $shifts->pluck('staff_profile_id')->unique()->all();
        $validIds = StaffProfile::query()->whereKey($ids)->where('staff_type', StaffProfile::TYPE_THERAPIST)->pluck('id')->all();
        $cells = [];
        foreach ($shifts as $shift) {
            $date = (string) $shift['schedule_date'];
            $label = __('Therapist #:id on :date', ['id' => $shift['staff_profile_id'], 'date' => $date]);
            if (! in_array((int) $shift['staff_profile_id'], $validIds, true) || $date < $week->toDateString() || $date > $week->copy()->addDays(6)->toDateString()) {
                throw ValidationException::withMessages(['roster' => __(':label has an invalid therapist or date. Clear or correct that range.', ['label' => $label])]);
            }
            $start = (string) $shift['start_time'];
            $end = (string) $shift['end_time'];
            if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end)) {
                throw ValidationException::withMessages(['start_time' => __(':label has an invalid time.', ['label' => $label])]);
            }
            $interval = $this->windows->intervalForDate(Carbon::parse($date, 'Asia/Manila'), $start, $end, (bool) $shift['ends_next_day']);
            if ((int) substr($start, 3, 2) % 30 || (int) substr($end, 3, 2) % 30
                || ($shift['ends_next_day'] && $end !== '00:00') || $interval['end']->lte($interval['start'])
                || ! $this->windows->withinBusinessHours($interval['start'], $interval['end'])) {
                throw ValidationException::withMessages(['start_time' => __(':label must use 30-minute boundaries between 1:00 PM and midnight.', ['label' => $label])]);
            }
            $key = $shift['staff_profile_id'].':'.$date;
            foreach ($cells[$key] ?? [] as $existing) {
                if ($interval['start']->lt($existing['end']) && $interval['end']->gt($existing['start'])) {
                    throw ValidationException::withMessages(['start_time' => __(':label overlaps another draft shift. Adjust or remove the overlapping range.', ['label' => $label])]);
                }
            }
            $cells[$key][] = $interval;
        }
    }
}
