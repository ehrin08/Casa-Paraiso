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
        foreach (['customer_name', 'customer_phone'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
        if ($this->boolean('_booking_addons_present')) {
            $this->merge(['addon_codes' => $this->input('addon_codes', [])]);
        }
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

    protected function operationalCustomerRules(?Appointment $appointment = null): array
    {
        return [
            'customer_profile_id' => ['nullable', 'required_without:customer_name', 'integer', $appointment ? $this->customerProfileRuleForUpdate($appointment) : $this->customerProfileRule()],
            'customer_name' => ['nullable', 'required_without:customer_profile_id', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
        ];
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
