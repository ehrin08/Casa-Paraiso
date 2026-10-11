<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerNoteRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:5000'],
        ] + $this->contextRules();
    }

    private function contextRules(): array
    {
        return [
            'return_to' => ['nullable', Rule::in(['index'])],
            'return_filters' => ['nullable', 'array:q,status,sort,direction,page'],
            'return_filters.q' => ['nullable', 'string', 'max:255'],
            'return_filters.status' => ['nullable', Rule::in(['active', 'inactive'])],
            'return_filters.sort' => ['nullable', Rule::in(['name', 'code', 'appointments', 'transactions', 'feedback', 'preference', 'status'])],
            'return_filters.direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'return_filters.page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    public function returnQuery(): array
    {
        // Failed note forms still need a validated, local return destination.
        $context = Validator::make($this->only(['return_to', 'return_filters']), $this->contextRules());

        return $context->passes() ? ($context->validated()['return_filters'] ?? []) : [];
    }

    protected function getRedirectUrl(): string
    {
        if ($this->input('return_to') !== 'index') {
            return parent::getRedirectUrl();
        }

        $this->session()->flash('customer_modal_id', $this->route('customer')->id);

        return route('admin.customers.index', $this->returnQuery());
    }
}
