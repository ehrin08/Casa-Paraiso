<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Services\UserPhotoService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => UserPhotoService::RULES,
            'remove_photo' => ['sometimes', 'boolean'],
            'public_title' => ['nullable', 'string', 'max:80'],
            'public_bio' => ['nullable', 'string', 'max:280'],
            'show_on_org_chart' => ['sometimes', 'boolean'],
            'org_chart_tier' => ['nullable', 'required_if_accepted:show_on_org_chart', Rule::in(User::ORG_TIERS)],
            'org_chart_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'org_chart_tier.required_if_accepted' => __('Choose a chart level before showing this person on the website.'),
        ];
    }
}
