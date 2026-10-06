<?php

namespace App\Http\Requests\Admin;

use App\Models\StaffProfile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return self::filterRules(hasDateFrom: $this->filled('date_from')) + [
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public static function filterRules(string $prefix = '', bool $hasDateFrom = false): array
    {
        return [
            $prefix.'date' => ['nullable', 'date_format:Y-m-d'],
            $prefix.'all_dates' => ['nullable', 'boolean'],
            $prefix.'staff_profile_id' => ['nullable', 'integer', Rule::exists('staff_profiles', 'id')->where('staff_type', StaffProfile::TYPE_THERAPIST)],
            $prefix.'status' => ['nullable', Rule::in(['open', 'closed'])],
            $prefix.'date_from' => ['nullable', 'date_format:Y-m-d'],
            $prefix.'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($hasDateFrom, ['after_or_equal:'.$prefix.'date_from'])],
        ];
    }

    public static function normalizeFilters(array $data): array
    {
        $filters = array_filter([
            'staff_profile_id' => $data['staff_profile_id'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($value) => filled($value));

        if ($data['all_dates'] ?? false) {
            return $filters + ['all_dates' => 1];
        }

        if (filled($data['date'] ?? null)) {
            return $filters + ['date' => $data['date']];
        }

        $range = array_filter([
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
        ], fn ($value) => filled($value));

        return $filters + ($range ?: ['date' => CarbonImmutable::now('Asia/Manila')->toDateString()]);
    }
}
