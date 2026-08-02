<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AppointmentManagement
{
    public function __construct(
        private readonly AppointmentWorkflow $workflow,
        private readonly AppointmentAddons $addons,
    ) {}

    /** @param array<string, mixed> $data */
    public function persist(Appointment $appointment, array $data, int $actorId): Appointment
    {
        $appointment->loadMissing(['addons', 'promotionSuggestion']);
        $service = Service::query()->findOrFail($data['service_id']);
        $status = $data['status'];
        $scheduledStart = ! empty($data['scheduled_start_at']) ? Carbon::parse($data['scheduled_start_at']) : null;
        $requestedStart = $scheduledStart?->copy() ?? Carbon::parse($data['requested_start_at']);
        $staffProfile = ! empty($data['staff_profile_id'])
            ? StaffProfile::withTrashed()->with('user')->findOrFail($data['staff_profile_id'])
            : null;
        $preferredStaffProfile = ! empty($data['preferred_staff_profile_id'])
            ? StaffProfile::withTrashed()->with(['user', 'services'])->findOrFail($data['preferred_staff_profile_id'])
            : null;
        $isNew = ! $appointment->exists;
        $originalStatus = $appointment->status;
        $originalCustomerProfileId = $appointment->customer_profile_id;
        $originalServiceId = $appointment->service_id;
        $originalStaffProfileId = $appointment->staff_profile_id;
        $originalPreferredStaffProfileId = $appointment->preferred_staff_profile_id;
        $originalRequestedStart = $appointment->requested_start_at?->copy();
        $originalScheduledStart = $appointment->scheduled_start_at?->copy();
        $addonCodes = $data['addon_codes'] ?? $appointment->addons->pluck('addon_code')->all();
        $paidAddons = $this->addons->selected($addonCodes, true);
        $this->addons->assertDoesNotDuplicateVoucher($paidAddons, $appointment->promotionSuggestion);
        $scheduleAddonCodes = [...$addonCodes, ...($appointment->promotionSuggestion?->addon_code ? [$appointment->promotionSuggestion->addon_code] : [])];
        $originalAddonCodes = $appointment->addons->pluck('addon_code')->sort()->values()->all();
        $addonsChanged = collect($addonCodes)->sort()->values()->all() !== $originalAddonCodes;

        $this->workflow->assertTransitionAllowed($appointment, $status);

        if ($appointment->exists && $appointment->status === Appointment::STATUS_CONFIRMED && $status === Appointment::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['status' => __('Finish the service from its completion form so the transaction is recorded with the outcome.')]);
        }

        $preferenceChanged = $isNew || (int) $appointment->preferred_staff_profile_id !== (int) $preferredStaffProfile?->id;

        if ($preferredStaffProfile && $preferenceChanged && ! $this->workflow->isStaffEligibleForService($preferredStaffProfile, $service)) {
            throw ValidationException::withMessages(['preferred_staff_profile_id' => __('The preferred therapist must be active, bookable, and eligible for this service.')]);
        }

        if ($isNew) {
            $this->workflow->assertBookableStart($requestedStart, $service, 'requested_start_at', true, $scheduleAddonCodes);
        }

        if ($status === Appointment::STATUS_CONFIRMED && (! $staffProfile || ! $scheduledStart)) {
            throw ValidationException::withMessages(['scheduled_start_at' => __('Confirmed appointments require staff and scheduled time.')]);
        }

        $scheduledEnd = $scheduledStart ? $this->workflow->scheduledEnd($scheduledStart, $service, $scheduleAddonCodes) : null;
        $scheduledStartChanged = ($originalScheduledStart === null) !== ($scheduledStart === null)
            || ($originalScheduledStart && $scheduledStart && ! $originalScheduledStart->equalTo($scheduledStart));
        $scheduleChanged = $isNew
            || (int) $originalServiceId !== (int) $service->id
            || (int) $originalStaffProfileId !== (int) $staffProfile?->id
            || $scheduledStartChanged
            || $addonsChanged;
        $terminalRecordChanged = (int) $originalCustomerProfileId !== (int) $data['customer_profile_id']
            || (int) $originalServiceId !== (int) $service->id
            || (int) $originalStaffProfileId !== (int) $staffProfile?->id
            || (int) $originalPreferredStaffProfileId !== (int) $preferredStaffProfile?->id
            || ! $originalRequestedStart?->equalTo($requestedStart)
            || $scheduledStartChanged
            || $addonsChanged;

        if (in_array($originalStatus, [Appointment::STATUS_COMPLETED, Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW], true) && $terminalRecordChanged) {
            throw ValidationException::withMessages(['status' => __('Completed, cancelled, and no-show appointment details are historical. Only notes may be edited.')]);
        }

        if ($originalStatus === Appointment::STATUS_CONFIRMED
            && in_array($status, [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW, Appointment::STATUS_CANCELLED], true)
            && $scheduleChanged) {
            throw ValidationException::withMessages(['status' => __('Save schedule or therapist changes before recording the appointment outcome.')]);
        }

        $appointment->fill([
            'customer_profile_id' => $data['customer_profile_id'],
            'service_id' => $service->id,
            'staff_profile_id' => $staffProfile?->id,
            'preferred_staff_profile_id' => $preferredStaffProfile?->id,
            'requested_start_at' => $requestedStart,
            'scheduled_start_at' => $scheduledStart,
            'scheduled_end_at' => $scheduledEnd,
            'customer_notes' => $data['customer_notes'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'created_by' => $appointment->created_by ?: $actorId,
            'updated_by' => $actorId,
        ]);

        if ($status === Appointment::STATUS_CONFIRMED) {
            if (! $scheduleChanged) {
                return $this->workflow->changeStatus($appointment, $status, $actorId, $data['reason'] ?? null);
            }

            return $this->workflow->schedule($appointment, $staffProfile, $service, $scheduledStart, $actorId, $data['reason'] ?? null, $addonCodes);
        }

        return $this->workflow->changeStatus($appointment, $status, $actorId, $data['reason'] ?? null);
    }
}
