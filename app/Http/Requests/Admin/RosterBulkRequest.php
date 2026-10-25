<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class RosterBulkRequest extends WeeklyRosterRequest
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
            'mode' => ['required', Rule::in(['hours', 'clear'])],
            'staff_ids' => ['required', 'array', 'min:1', 'max:15'],
            'staff_ids.*' => ['required', 'integer', 'distinct', 'exists:staff_profiles,id'],
            'dates' => ['required', 'array', 'min:1', 'max:7'],
            'dates.*' => ['required', 'date_format:Y-m-d', 'distinct'],
            'start_time' => ['exclude_if:mode,clear', 'required', 'date_format:H:i'],
            'end_time' => ['exclude_if:mode,clear', 'required', 'date_format:H:i'],
            'ends_next_day' => ['exclude_if:mode,clear', 'required', 'boolean'],
        ];
    }

    public function bulkData(): array
    {
        return $this->safe()->only(['mode', 'staff_ids', 'dates', 'start_time', 'end_time', 'ends_next_day']);
    }
}
