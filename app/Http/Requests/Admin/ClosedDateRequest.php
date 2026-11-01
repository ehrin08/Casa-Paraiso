<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClosedDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $today = now(config('casa.business_hours.timezone', 'Asia/Manila'))->toDateString();

        return [
            'closed_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today, Rule::unique('closed_dates', 'closed_on')],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'closed_on.after_or_equal' => __('Choose today or a later date.'),
            'closed_on.unique' => __('This date is already closed.'),
        ];
    }
}
