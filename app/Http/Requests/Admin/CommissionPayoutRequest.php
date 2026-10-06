<?php

namespace App\Http\Requests\Admin;

use App\Models\TherapistCommission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommissionPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'return_to' => ['nullable', Rule::in(['index'])],
            'return_filters' => ['nullable', 'array:staff_profile_id,status,date_from,date_to,page'],
            'return_filters.staff_profile_id' => ['nullable', 'integer', Rule::exists('staff_profiles', 'id')],
            'return_filters.status' => ['nullable', Rule::in(TherapistCommission::STATUSES)],
            'return_filters.date_from' => ['nullable', 'date'],
            'return_filters.date_to' => ['nullable', 'date', Rule::when($this->filled('return_filters.date_from'), ['after_or_equal:return_filters.date_from'])],
            'return_filters.page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
