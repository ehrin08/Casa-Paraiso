<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class AdminAppointmentUpdateRequest extends AppointmentRequest
{
    protected function prepareForValidation(): void
    {
        $this->synchronizeAppointmentTime();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Appointment $appointment */
        $appointment = $this->route('appointment');

        return [
            'customer_profile_id' => ['required', 'integer', $this->customerProfileRuleForUpdate($appointment)],
            'service_id' => ['required', 'integer', $this->serviceRuleForUpdate($appointment)],
            'staff_profile_id' => ['nullable', 'integer', $this->staffProfileRuleForUpdate($appointment, 'staff_profile_id')],
            'preferred_staff_profile_id' => ['nullable', 'integer', $this->staffProfileRuleForUpdate($appointment, 'preferred_staff_profile_id')],
            'requested_start_at' => ['required', 'date'],
            'scheduled_start_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(Appointment::STATUSES)],
            'addon_codes' => ['nullable', 'array'],
            'addon_codes.*' => ['required', 'string', 'distinct'],
            ...$this->noteRules(),
        ];
    }
}
