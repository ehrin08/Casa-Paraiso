<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'promotion_voucher_validity_days' => [
                'nullable',
                'integer',
                Rule::in(config('casa.customer_rewards.validity_options', [])),
            ],
        ];
    }
}
