<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TransactionImportConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'uuid'],
            'commissions_paid' => ['nullable', 'boolean'],
            'commissions_paid_on' => ['nullable', 'required_if_accepted:commissions_paid', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
