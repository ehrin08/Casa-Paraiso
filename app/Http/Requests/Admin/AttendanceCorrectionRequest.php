<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AttendanceCorrectionRequest extends FormRequest
{
    public const REASONS = [
        'missed_time_in' => 'Missed time-in',
        'missed_time_out' => 'Missed time-out',
        'incorrect_scan_time' => 'Incorrect scan time',
        'other' => 'Other',
    ];

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['_attendance_id' => $this->route('attendance')?->id]);
        foreach (['reason', 'details'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['time_in', 'time_out'])],
            'occurred_at' => ['required', 'date'],
            'reason_code' => ['nullable', 'required_without:reason', Rule::in(array_keys(self::REASONS))],
            'reason' => ['nullable', 'required_without:reason_code', 'string', 'min:3', 'max:1000'],
            'details' => ['nullable', Rule::requiredIf(fn () => $this->input('reason_code') === 'other'), 'string', 'max:300'],
        ] + $this->contextRules();
    }

    private function contextRules(): array
    {
        return [
            'return_to' => ['nullable', Rule::in(['index'])],
            'return_filters' => ['nullable', 'array:date,all_dates,staff_profile_id,status,date_from,date_to,page'],
            'return_filters.page' => ['nullable', 'integer', 'min:1'],
        ] + AttendanceIndexRequest::filterRules('return_filters.', $this->filled('return_filters.date_from'));
    }

    public function reasonText(): string
    {
        $data = $this->validated();
        if (! filled($data['reason_code'] ?? null)) {
            return $data['reason'];
        }

        return self::REASONS[$data['reason_code']].(filled($data['details'] ?? null) ? ': '.$data['details'] : '');
    }

    public function returnQuery(): array
    {
        // Validate context separately so even failed forms have a safe local destination.
        $context = Validator::make($this->only(['return_to', 'return_filters']), $this->contextRules());
        $data = $context->passes() ? ($context->validated()['return_filters'] ?? []) : [];
        $filters = AttendanceIndexRequest::normalizeFilters($data ?: [
            'date' => $this->route('attendance')->attendance_date->toDateString(),
        ]);

        return $filters + ['page' => (int) ($data['page'] ?? 1), 'selected' => $this->route('attendance')->id];
    }

    public function attendanceUrl(): string
    {
        return route('admin.attendance.index', $this->returnQuery()).'#attendance-correction';
    }

    protected function getRedirectUrl(): string
    {
        return $this->attendanceUrl();
    }
}
