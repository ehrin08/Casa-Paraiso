<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isReceptionist();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'customer_profile_id' => ['required', 'integer', Rule::exists('customer_profiles', 'id')->whereNull('deleted_at')],
            'membership_tier_id' => ['required', 'integer', Rule::exists('membership_tiers', 'id')->where('is_active', true)],
            'card_number' => ['required', 'string', 'max:40', 'unique:customer_memberships,card_number'],
            'issued_at' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'referred_by_customer_profile_id' => ['nullable', 'integer', 'different:customer_profile_id', Rule::exists('customer_profiles', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['referred_by_customer_profile_id.different' => __('A customer cannot refer themselves.')];
    }
}
