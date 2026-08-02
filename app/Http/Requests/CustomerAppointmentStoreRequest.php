<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class CustomerAppointmentStoreRequest extends AppointmentRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', $this->activeServiceRule()],
            'preferred_staff_profile_id' => ['nullable', 'integer', $this->activeStaffProfileRule()],
            'promotion_suggestion_id' => ['nullable', 'integer', 'exists:promotion_suggestions,id'],
            'addon_codes' => ['nullable', 'array'],
            'addon_codes.*' => ['required', 'string', 'distinct'],
            'requested_start_at' => ['required', 'date'],
            'customer_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
