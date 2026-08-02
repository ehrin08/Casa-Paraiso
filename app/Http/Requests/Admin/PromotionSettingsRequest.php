<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Addon;
use Illuminate\Validation\Validator;

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
            'groups' => ['required', 'array'],
            'groups.*.addon_code' => ['required', 'string', Rule::in(Addon::query()->where('is_active', true)->pluck('code')->all())],
            'groups.*.is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $expected = collect(config('casa.customer_rewards.presets', []))->pluck('key')->sort()->values()->all();
                $provided = collect(array_keys((array) $this->input('groups', [])))->sort()->values()->all();

                if ($expected !== $provided) {
                    $validator->errors()->add('groups', __('Every customer reward group must be submitted.'));
                }
            },
        ];
    }
}
