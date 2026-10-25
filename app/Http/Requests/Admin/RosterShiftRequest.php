<?php

namespace App\Http\Requests\Admin;

class RosterShiftRequest extends WeeklyRosterRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('ends_next_day')) {
            $this->merge(['ends_next_day' => $this->input('end_time') === '00:00']);
        }
    }

    public function rules(): array
    {
        return [...parent::rules(),
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'schedule_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'ends_next_day' => ['required', 'boolean'],
        ];
    }

    public function shiftData(): array
    {
        return $this->safe()->only(['staff_profile_id', 'schedule_date', 'start_time', 'end_time', 'ends_next_day']);
    }
}
