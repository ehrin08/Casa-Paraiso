<?php

namespace App\Http\Requests\Admin;

use App\Models\MembershipTier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MembershipTierRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() ?? false; }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tier = $this->route('membership_tier');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('membership_tiers', 'name')->ignore($tier instanceof MembershipTier ? $tier->id : null)],
            'description' => ['nullable', 'string', 'max:1000'],
            'rank' => ['required', 'integer', 'min:0', 'max:1000'],
            'stars' => ['required', 'integer', 'min:0', 'max:5'],
            'service_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'addon_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'addon_discount_codes' => ['nullable', 'array'],
            'addon_discount_codes.*' => ['string', Rule::exists('addons', 'code')],
            'vip_room_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'birthday_treat_label' => ['nullable', 'string', 'max:100'],
            'birthday_treat_addon_code' => ['nullable', 'string', Rule::exists('addons', 'code')],
            'birthday_treat_service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'referral_reward_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'validity_months' => ['required', 'integer', 'min:1', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
