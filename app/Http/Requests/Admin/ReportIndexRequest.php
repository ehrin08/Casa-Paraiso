<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\ReportController;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\Transaction;
use App\Services\ReportDateRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(ReportController::TYPES)],
            'category' => ['sometimes', Rule::in(['all', ...ReportController::TYPES])],
            'range' => ['sometimes', Rule::in(array_keys(ReportDateRange::OPTIONS))],
            'q' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), ['after_or_equal:date_from'])],
            'status' => ['nullable', Rule::in([...Appointment::STATUSES, 'available', 'reserved', 'used', 'dismissed', 'expired', 'active', 'inactive'])],
            'payment_status' => ['nullable', Rule::in(Transaction::PAYMENT_STATUSES)],
            'sentiment_label' => ['nullable', Rule::in(Feedback::SENTIMENT_LABELS)],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
