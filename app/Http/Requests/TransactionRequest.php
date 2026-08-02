<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isStaff() || $this->user()?->isReceptionist();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $staffTransaction = $this->user()?->isStaff() ?? false;

        return [
            'customer_profile_id' => [$staffTransaction ? 'nullable' : 'required', 'integer', Rule::exists('customer_profiles', 'id')],
            'appointment_id' => [$staffTransaction ? 'required' : 'nullable', 'integer', Rule::exists('appointments', 'id')],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'payment_status' => ['required', Rule::in(Transaction::PAYMENT_STATUSES)],
            'payment_method' => ['nullable', Rule::in(Transaction::PAYMENT_METHODS)],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $status = (string) $this->input('payment_status');

                if (! in_array($status, Transaction::PAYMENT_RECEIVED_STATUSES, true)) {
                    return;
                }

                if (! $this->filled('payment_method')) {
                    $validator->errors()->add('payment_method', __('Select the method used to receive this payment.'));
                }

                if (! $this->filled('paid_at')) {
                    $validator->errors()->add(
                        'paid_at',
                        $status === Transaction::PAYMENT_REFUNDED
                            ? __('Keep the original payment date for a refunded transaction.')
                            : __('Enter the date this payment was received.'),
                    );
                }
            },
        ];
    }
}
