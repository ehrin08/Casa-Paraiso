<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RosterIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'week' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:100'],
            'staff_profile_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'therapist' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'conflicts_page' => ['nullable', 'integer', 'min:1'],
            'view' => ['nullable', Rule::in(['draft', 'live'])],
            'schedule_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
