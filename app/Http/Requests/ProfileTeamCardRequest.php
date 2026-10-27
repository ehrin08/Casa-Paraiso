<?php

namespace App\Http\Requests;

use App\Services\UserPhotoService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Staff may edit their own photo, title and bio; visibility, level and order stay with admins. */
class ProfileTeamCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isCustomer();
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
        ];
    }
}
