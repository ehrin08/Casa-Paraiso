<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TransactionImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel', 'extensions:csv', 'max:'.(int) config('casa.imports.max_kilobytes', 2048)],
        ];
    }

    public function messages(): array
    {
        return [
            'file.extensions' => __('Choose a .csv file.'),
            'file.mimetypes' => __('Choose a .csv file.'),
        ];
    }
}
