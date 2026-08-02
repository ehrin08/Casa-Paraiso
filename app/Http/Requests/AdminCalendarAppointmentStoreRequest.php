<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;

class AdminCalendarAppointmentStoreRequest extends AppointmentRequest
{
    protected function prepareForValidation(): void
    {
        $this->synchronizeAppointmentTime();

        if (! $this->has('status')) {
            $this->merge(['status' => Appointment::STATUS_CONFIRMED]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_profile_id' => ['required', 'integer', $this->customerProfileRule()],
            'service_id' => ['required', 'integer', $this->activeServiceRule()],
            'staff_profile_id' => ['required', 'integer', $this->activeStaffProfileRule()],
            'preferred_staff_profile_id' => ['nullable', 'integer', $this->activeStaffProfileRule()],
            'requested_start_at' => ['required', 'date'],
            'scheduled_start_at' => ['required', 'date'],
            'status' => ['required', 'in:confirmed'],
            'addon_codes' => ['nullable', 'array'],
            'addon_codes.*' => ['required', 'string', 'distinct'],
            ...$this->noteRules(),
        ];
    }
}
