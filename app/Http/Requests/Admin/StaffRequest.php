<?php

namespace App\Http\Requests\Admin;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->route('staff')) {
            return $this->user()?->isSuperAdmin() ?? false;
        }

        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $staffProfile = $this->route('staff');
        $staffUser = $staffProfile instanceof StaffProfile ? $staffProfile->user : null;
        $assignedServiceIds = $staffProfile instanceof StaffProfile
            ? $staffProfile->services()->pluck('services.id')->all()
            : [];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($staffUser?->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'staff_type' => ['sometimes', Rule::in(StaffProfile::TYPES)],
            'position' => ['nullable', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'hire_date' => ['nullable', 'date', 'before_or_equal:today'],
            'is_bookable' => ['sometimes', 'boolean'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => [
                'integer',
                Rule::exists('services', 'id')->where(function ($query) use ($assignedServiceIds): void {
                    $query->where('is_active', true);

                    if ($assignedServiceIds !== []) {
                        $query->orWhereIn('id', $assignedServiceIds);
                    }
                }),
            ],
        ];
    }
}
