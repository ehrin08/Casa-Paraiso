<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminAppointmentOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isAdmin() || $this->user()?->isReceptionist()) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([Appointment::STATUS_NO_SHOW, Appointment::STATUS_CANCELLED])],
            'reason' => ['nullable', 'string', 'max:500'],
            'return_to' => ['nullable', Rule::in(['index'])],
            'queue_page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'mode' => ['nullable', Rule::in(['bookings', 'availability'])],
        ];
    }
}
