<?php

namespace App\Http\Requests\Admin;

use App\Models\StaffScheduleShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WeeklyRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'week' => [$this->route('scheduleWeek') ? 'nullable' : 'required', 'date_format:Y-m-d'],
            'expected_revision' => ['required', 'integer', 'min:0'],
            'source_signature' => [$this->routeIs('admin.staff-roster.copy') ? 'required' : 'nullable', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            'return_filters' => ['nullable', 'array:q,staff_profile_id,page,view'],
            'return_filters.q' => ['nullable', 'string', 'max:100'],
            'return_filters.staff_profile_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'return_filters.page' => ['nullable', 'integer', 'min:1'],
            'return_filters.view' => ['nullable', Rule::in(['draft', 'live'])],
        ];
    }

    public function context(): array
    {
        $raw = $this->input('return_filters', []);
        $raw = is_array($raw) ? $raw : [];
        $week = $this->route('scheduleWeek')?->week_start_date?->toDateString() ?? $this->input('week');
        $context = ['week' => is_string($week) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) ? $week : now('Asia/Manila')->toDateString(), 'view' => 'draft'];
        if (is_string($raw['q'] ?? null)) {
            $context['q'] = mb_substr($raw['q'], 0, 100);
        }
        foreach (['staff_profile_id', 'page'] as $key) {
            if (is_scalar($raw[$key] ?? null) && ctype_digit((string) $raw[$key]) && (int) $raw[$key] > 0) {
                $context[$key] = (int) $raw[$key];
            }
        }

        return $context;
    }

    protected function getRedirectUrl(): string
    {
        $week = $this->route('scheduleWeek');
        $query = $this->context();
        if ($this->routeIs('admin.staff-roster.shifts.update')) {
            if (! $week->shifts()->whereKey($this->route('shift'))->where('version', StaffScheduleShift::VERSION_DRAFT)->exists()) {
                return route('admin.staff-roster.shifts.create', [$week, ...$query]);
            }

            return route('admin.staff-roster.shifts.edit', [$week, $this->route('shift'), ...$query]);
        }
        if ($this->routeIs('admin.staff-roster.shifts.store')) {
            return route('admin.staff-roster.shifts.create', [$week, ...$query]);
        }
        if ($this->routeIs('admin.staff-roster.bulk.*')) {
            return route('admin.staff-roster.bulk.edit', [$week, ...$query]);
        }
        if ($this->routeIs('admin.staff-roster.copy')) {
            return route('admin.staff-roster.copy.review', $query);
        }
        if ($this->routeIs('admin.staff-roster.publish')) {
            return route('admin.staff-roster.publish.review', [$week, ...$query]);
        }

        return route('admin.staff-roster.index', $query);
    }

    public function recoveryUrl(): string
    {
        return $this->getRedirectUrl();
    }
}
