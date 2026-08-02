<?php

namespace App\Http\Requests\Reception;

use App\Models\CustomerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isReceptionist() || $this->user()?->isAdmin()) ?? false;
    }

    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_preference' => ['nullable', Rule::in(array_keys(CustomerProfile::CONTACT_PREFERENCES))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
