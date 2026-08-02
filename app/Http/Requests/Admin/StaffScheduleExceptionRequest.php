<?php

namespace App\Http\Requests\Admin;

use App\Models\StaffScheduleException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StaffScheduleExceptionRequest extends FormRequest
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
            'exception_date' => ['required', 'date'],
            'exception_type' => ['required', Rule::in(StaffScheduleException::TYPES)],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'ends_next_day' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $type = $this->input('exception_type');
                $startTime = $this->input('start_time');
                $endTime = $this->input('end_time');
                $endsNextDay = $this->boolean('ends_next_day');

                if ($type === StaffScheduleException::TYPE_AVAILABLE && (empty($startTime) || empty($endTime))) {
                    $validator->errors()->add('start_time', 'Available exceptions require start and end times.');

                    return;
                }

                if ((empty($startTime) && ! empty($endTime)) || (! empty($startTime) && empty($endTime))) {
                    $validator->errors()->add('start_time', 'Provide both start and end times, or leave both blank for a full-day unavailable exception.');

                    return;
                }

                if (empty($startTime) && empty($endTime)) {
                    if ($endsNextDay) {
                        $validator->errors()->add('ends_next_day', 'Full-day exceptions do not need a next-day ending.');
                    }

                    return;
                }

                $startMinutes = $this->minutes((string) $startTime);
                $endMinutes = $endsNextDay ? 1440 : $this->minutes((string) $endTime);
                $openingMinutes = $this->minutes((string) config('casa.business_hours.opens_at', '13:00'));

                if ($startMinutes < $openingMinutes) {
                    $validator->errors()->add('start_time', 'Schedule exceptions must begin within business hours at 1:00 PM or later.');

                    return;
                }

                if ($endsNextDay && $endTime !== '00:00') {
                    $validator->errors()->add('end_time', 'A next-day exception must end at 12:00 midnight.');

                    return;
                }

                if ($endMinutes <= $startMinutes) {
                    $validator->errors()->add('end_time', 'The end time must be after the start time.');
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
