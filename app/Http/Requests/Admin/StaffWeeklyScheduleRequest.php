<?php

namespace App\Http\Requests\Admin;

use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StaffWeeklyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'ends_next_day' => ['sometimes', 'boolean'],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $staff = $this->route('staff');
                $weeklySchedule = $this->route('weeklySchedule');
                $startMinutes = $this->minutes((string) $this->input('start_time'));
                $endsNextDay = $this->boolean('ends_next_day');
                $endMinutes = $endsNextDay ? 1440 : $this->minutes((string) $this->input('end_time'));
                $openingMinutes = $this->minutes((string) config('casa.business_hours.opens_at', '13:00'));

                if ($startMinutes < $openingMinutes) {
                    $validator->errors()->add('start_time', 'Therapist availability must begin within business hours at 1:00 PM or later.');

                    return;
                }

                if ($endsNextDay && $this->input('end_time') !== '00:00') {
                    $validator->errors()->add('end_time', 'A next-day shift must end at 12:00 midnight.');

                    return;
                }

                if ($endMinutes <= $startMinutes) {
                    $validator->errors()->add('end_time', 'The end time must be after the start time.');

                    return;
                }

                if (! $staff instanceof StaffProfile) {
                    return;
                }

                $hasOverlap = StaffWeeklySchedule::query()
                    ->where('staff_profile_id', $staff->id)
                    ->where('day_of_week', (int) $this->input('day_of_week'))
                    ->when($weeklySchedule instanceof StaffWeeklySchedule, fn ($query) => $query->whereKeyNot($weeklySchedule->getKey()))
                    ->get()
                    ->contains(function (StaffWeeklySchedule $existing) use ($startMinutes, $endMinutes): bool {
                        $existingStart = $this->minutes((string) $existing->start_time);
                        $existingEnd = $existing->ends_next_day ? 1440 : $this->minutes((string) $existing->end_time);

                        return $startMinutes < $existingEnd && $endMinutes > $existingStart;
                    });

                if ($hasOverlap) {
                    $validator->errors()->add('start_time', 'This weekly shift overlaps an existing shift for the selected day.');
                }
            },
        ];
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return ($hour * 60) + $minute;
    }
}
