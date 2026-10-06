<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AppointmentAvailability
{
    public function __construct(
        private readonly AppointmentWorkflow $workflow,
        private readonly ScheduleWindowResolver $scheduleWindows,
        private readonly AppointmentAddons $addons,
    ) {}

    /**
     * @return array{
     *     month: string,
     *     service_id: int,
     *     preferred_staff_profile_id: int|null,
     *     dates: array<string, array<int, array{starts_at: string, ends_at: string, time: string, label: string, staff_count: int}>>
     * }
     */
    public function month(Service $service, string $month, ?int $preferredStaffProfileId = null, array $addonCodes = []): array
    {
        return $this->monthAvailability($service, $month, $preferredStaffProfileId, $addonCodes, $this->customerBookingCutoff());
    }

    /** @param array<int, string> $addonCodes */
    public function forOperations(Service $service, string $month, array $addonCodes = [], ?Appointment $appointment = null): array
    {
        $appointment?->loadMissing(['promotionSuggestion', 'addons']);
        $selectedAddons = $this->addons->selected($addonCodes, true);
        $this->addons->assertDoesNotDuplicateVoucher($selectedAddons, $appointment?->promotionSuggestion);
        $allowedCodes = $this->addons->catalog()->pluck('code')->merge($appointment?->addons->pluck('addon_code') ?? collect());
        if ($selectedAddons->contains(fn (array $addon) => ! $allowedCodes->contains($addon['code']))) {
            throw ValidationException::withMessages(['addon_codes' => __('Select active add-ons.')]);
        }
        if ($appointment?->promotionSuggestion?->addon_code) {
            $addonCodes[] = $appointment->promotionSuggestion->addon_code;
        }

        $timezone = (string) config('casa.business_hours.timezone', config('app.timezone'));

        return $this->monthAvailability($service, $month, null, array_values(array_unique($addonCodes)), now($timezone)->addSecond(), $appointment);
    }

    private function monthAvailability(Service $service, string $month, ?int $preferredStaffProfileId, array $addonCodes, Carbon $bookingCutoff, ?Appointment $ignoreAppointment = null): array
    {
        $timezone = (string) config('casa.business_hours.timezone', config('app.timezone'));
        $monthStart = Carbon::createFromFormat('!Y-m', $month, $timezone)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $staffProfiles = $this->eligibleStaff($service, $preferredStaffProfileId);
        $durationMinutes = $service->duration_minutes + $this->addons->durationMinutes($this->addons->selected($addonCodes, $ignoreAppointment !== null));
        $dates = [];
        $storeDates = [];
        $today = now($timezone)->startOfDay();

        $confirmedByStaff = Appointment::query()
            ->whereIn('staff_profile_id', $staffProfiles->pluck('id'))
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->when($ignoreAppointment, fn ($query) => $query->whereKeyNot($ignoreAppointment->getKey()))
            ->where('scheduled_start_at', '<', $monthEnd->copy()->addDay()->startOfDay())
            ->where('scheduled_end_at', '>', $monthStart)
            ->get()
            ->groupBy('staff_profile_id');

        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            if ($day->lt($today)) {
                continue;
            }

            $business = $this->scheduleWindows->businessWindow($day);
            $storeSlots = $this->slotsForDate($staffProfiles, $confirmedByStaff, $day, $durationMinutes, $bookingCutoff);
            $storeDates[$day->toDateString()] = [
                'opens_at' => $business['start']->toDateTimeString(),
                'closes_at' => $business['end']->toDateTimeString(),
                'slots' => $storeSlots,
            ];
            $daySlots = array_values(array_filter($storeSlots, fn (array $slot) => $slot['staff_count'] > 0));

            if ($daySlots !== []) {
                $dates[$day->toDateString()] = $daySlots;
            }
        }

        return [
            'month' => $monthStart->format('Y-m'),
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $preferredStaffProfileId,
            'dates' => $dates,
            'store_dates' => $storeDates,
        ];
    }

    /** @param array<int, string> $addonCodes */
    public function hasAvailableSlot(Service $service, Carbon $start, ?int $preferredStaffProfileId = null, array $addonCodes = []): bool
    {
        if ($start->lt($this->customerBookingCutoff())) {
            return false;
        }

        return $this->eligibleStaff($service, $preferredStaffProfileId)
            ->contains(fn (StaffProfile $staffProfile) => $this->workflow->isStaffAvailable($staffProfile, $service, $start, null, null, $addonCodes));
    }

    /**
     * @return Collection<int, StaffProfile>
     */
    private function eligibleStaff(Service $service, ?int $preferredStaffProfileId = null): Collection
    {
        return StaffProfile::query()
            ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
            ->where('is_bookable', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->whereHas('services', fn ($query) => $query->whereKey($service->id))
            ->when($preferredStaffProfileId, fn ($query) => $query->whereKey($preferredStaffProfileId))
            ->get();
    }

    /**
     * @param  Collection<int, StaffProfile>  $staffProfiles
     * @param  Collection<int, Collection<int, Appointment>>  $confirmedByStaff
     * @return array<int, array{starts_at: string, ends_at: string, time: string, label: string, staff_count: int}>
     */
    private function slotsForDate(
        Collection $staffProfiles,
        Collection $confirmedByStaff,
        Carbon $day,
        int $durationMinutes,
        Carbon $bookingCutoff,
    ): array {
        $slots = [];
        $business = $this->scheduleWindows->businessWindow($day);

        // Resolve each therapist's windows once per date. Previously this happened
        // for every 30-minute slot, repeatedly querying dated roster coverage.
        $windowsByStaff = $staffProfiles->mapWithKeys(fn (StaffProfile $staffProfile) => [
            $staffProfile->id => $this->scheduleWindows->effectiveWindows($staffProfile, $day),
        ]);
        $slot = $business['start']->copy();
        $interval = (int) config('casa.business_hours.slot_interval_minutes', 30);

        while ($slot->lt($business['end'])) {
            $slotEnd = $slot->copy()->addMinutes($durationMinutes);

            $availableStaffCount = 0;
            if ($slot->gte($bookingCutoff) && $slotEnd->lte($business['end'])) {
                $availableStaffCount = $staffProfiles
                    ->filter(function (StaffProfile $staffProfile) use ($slot, $slotEnd, $confirmedByStaff, $windowsByStaff): bool {
                        if (! $windowsByStaff->get($staffProfile->id, collect())
                            ->contains(fn (array $window) => $window['start']->lte($slot) && $window['end']->gte($slotEnd))) {
                            return false;
                        }

                        return ! ($confirmedByStaff->get($staffProfile->id, collect()))
                            ->contains(fn (Appointment $appointment) => $appointment->scheduled_start_at?->lt($slotEnd)
                                && $appointment->scheduled_end_at?->gt($slot));
                    })
                    ->count();

            }

            $slots[] = [
                'starts_at' => $slot->toDateTimeString(),
                'ends_at' => $slotEnd->toDateTimeString(),
                'time' => $slot->format('H:i'),
                'label' => $slot->format('g:i A'),
                'staff_count' => $availableStaffCount,
            ];

            $slot->addMinutes($interval);
        }

        return $slots;
    }

    private function customerBookingCutoff(): Carbon
    {
        $timezone = (string) config('casa.business_hours.timezone', config('app.timezone'));
        $leadTimeMinutes = max(0, (int) config('casa.business_hours.customer_booking_lead_time_minutes', 30));

        return now($timezone)->addMinutes($leadTimeMinutes);
    }
}
