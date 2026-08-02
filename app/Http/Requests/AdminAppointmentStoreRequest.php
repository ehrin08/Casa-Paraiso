<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class AdminAppointmentStoreRequest extends AppointmentRequest
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
        return [
            'customer_profile_id' => ['required', 'integer', Rule::exists('customer_profiles', 'id')->whereNull('deleted_at')],
            'service_id' => ['required', 'integer', $this->activeServiceRule()],
            'staff_profile_id' => ['required', 'integer', $this->activeStaffProfileRule()],
            'preferred_staff_profile_id' => ['nullable', 'integer', $this->activeStaffProfileRule()],
            'requested_start_at' => ['required', 'date'],
            'scheduled_start_at' => ['required', 'date'],
            'status' => ['required', Rule::in(Appointment::CREATION_STATUSES)],
            'addon_codes' => ['nullable', 'array'],
            'addon_codes.*' => ['required', 'string', 'distinct'],
            ...$this->noteRules(),
        ];
    }
}
