<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

abstract class AppointmentRequest extends FormRequest
{
    protected function synchronizeAppointmentTime(): void
    {
        if ($this->filled('scheduled_start_at')) {
            $this->merge(['requested_start_at' => $this->input('scheduled_start_at')]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function activeServiceRule(): Exists
    {
        return Rule::exists('services', 'id')
            ->where('is_active', true)
            ->whereNull('deleted_at');
    }

    protected function serviceRuleForUpdate(Appointment $appointment): Exists
    {
        return Rule::exists('services', 'id')
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhere('id', $appointment->service_id));
    }

    protected function customerProfileRuleForUpdate(Appointment $appointment): Exists
    {
        return Rule::exists('customer_profiles', 'id')
            ->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->orWhere('id', $appointment->customer_profile_id));
    }

    protected function customerProfileRule(): Exists
    {
        return Rule::exists('customer_profiles', 'id')->whereNull('deleted_at');
    }

    protected function activeStaffProfileRule(): Exists
    {
        return Rule::exists('staff_profiles', 'id')->whereNull('deleted_at');
    }

    protected function staffProfileRuleForUpdate(Appointment $appointment, string $column): Exists
    {
        $currentId = $appointment->getAttribute($column);

        return Rule::exists('staff_profiles', 'id')
            ->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->when($currentId, fn ($query) => $query->orWhere('id', $currentId)));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function noteRules(): array
    {
        return [
            'customer_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
