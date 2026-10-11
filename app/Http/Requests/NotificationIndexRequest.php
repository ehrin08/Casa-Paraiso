<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isAdmin() || $user->isReceptionist() || $user->isStaff());
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['all', 'unread'])],
            'selected' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
