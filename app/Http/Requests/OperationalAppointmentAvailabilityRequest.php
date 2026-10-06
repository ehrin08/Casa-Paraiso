<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperationalAppointmentAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['super_admin', 'admin', 'receptionist'], true);
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'month' => ['required', 'date_format:Y-m'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'addon_codes' => ['nullable', 'array'],
            'addon_codes.*' => ['required', 'string', 'distinct'],
        ];
    }
}
