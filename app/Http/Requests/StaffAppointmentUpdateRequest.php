<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StaffAppointmentUpdateRequest extends AppointmentRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scheduled_start_at' => [
                Rule::requiredIf(fn () => $this->input('status') === Appointment::STATUS_CONFIRMED),
                'nullable',
                'date',
            ],
            'status' => ['required', Rule::in(Appointment::STATUSES)],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
