<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminAppointmentCompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isAdmin() || $this->user()?->isReceptionist()) ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'payment_status' => ['required', Rule::in([
                Transaction::PAYMENT_UNPAID,
                Transaction::PAYMENT_PARTIAL,
                Transaction::PAYMENT_PAID,
            ])],
            'payment_method' => ['nullable', Rule::in(Transaction::PAYMENT_METHODS)],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! in_array($this->input('payment_status'), Transaction::PAYMENT_RECEIVED_STATUSES, true)) {
                return;
            }

            if (! $this->filled('payment_method')) {
                $validator->errors()->add('payment_method', __('Select the method used to receive this payment.'));
            }

            if (! $this->filled('paid_at')) {
                $validator->errors()->add('paid_at', __('Enter the date this payment was received.'));
            }
        }];
    }
}
