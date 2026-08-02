<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentStatusLog;
use App\Models\Service;
use App\Models\StaffProfile;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentWorkflow
{
    private const NUMBER_ALLOCATION_ATTEMPTS = 5;

    public function __construct(
        private readonly ScheduleWindowResolver $scheduleWindows,
        private readonly AppointmentNumberGenerator $numberGenerator,
        private readonly RfmAddonVoucher $addonVouchers,
        private readonly AppointmentAddons $addons,
    ) {}

    public function nextAppointmentNumber(): string
    {
        return $this->numberGenerator->next();
    }

    /** @param array<int, string> $addonCodes */
    public function scheduledEnd(CarbonInterface $start, Service $service, array $addonCodes = []): CarbonInterface
    {
        return $start->copy()->addMinutes($service->duration_minutes + $this->addons->durationMinutes($this->addons->selected($addonCodes)));
    }

    public function assertBookableStart(
        CarbonInterface $start,
        Service $service,
        string $field = 'scheduled_start_at',
        bool $mustBeFuture = true,
        array $addonCodes = [],
        int $minimumLeadTimeMinutes = 0,
    ): void {
        $timezone = (string) config('casa.business_hours.timezone', config('app.timezone'));
        $candidate = Carbon::instance($start)->setTimezone($timezone);
        $interval = (int) config('casa.business_hours.slot_interval_minutes', 30);
        $messages = [];

        if ($mustBeFuture && $candidate->lte(now($timezone))) {
            $messages[] = __('Choose a future appointment time.');
        }

        if ($minimumLeadTimeMinutes > 0 && $candidate->lt(now($timezone)->addMinutes($minimumLeadTimeMinutes))) {
            $messages[] = __('Customer bookings must be made at least :minutes minutes before the appointment.', [
                'minutes' => $minimumLeadTimeMinutes,
            ]);
        }

        if ($interval < 1 || $candidate->minute % $interval !== 0 || $candidate->second !== 0) {
            $messages[] = __('Appointment times must start on a :minutes-minute interval.', [
                'minutes' => max(1, $interval),
            ]);
        }

        if (! $this->scheduleWindows->withinBusinessHours($candidate, $this->scheduledEnd($candidate, $service, $addonCodes))) {
            $messages[] = __('The full service must fit inside business hours (1:00 PM to 12:00 midnight).');
        }

        if ($messages !== []) {
            throw ValidationException::withMessages([$field => $messages]);
        }
    }

    public function isStaffEligibleForService(StaffProfile $staffProfile, Service $service): bool
    {
        $performsService = $staffProfile->relationLoaded('services')
            ? $staffProfile->services->contains('id', $service->id)
            : $staffProfile->services()->whereKey($service->id)->exists();

        return ! $staffProfile->trashed()
            && $staffProfile->is_bookable
            && $staffProfile->user?->is_active
            && $performsService;
    }

    public function isStaffAvailable(
        StaffProfile $staffProfile,
        Service $service,
        CarbonInterface $start,
        ?CarbonInterface $end = null,
        ?Appointment $ignoreAppointment = null,
        array $addonCodes = [],
    ): bool {
        $end ??= $this->scheduledEnd($start, $service, $addonCodes);

        if (! $this->isStaffEligibleForService($staffProfile, $service)) {
            return false;
        }

        if (! $this->scheduleWindows->covers($staffProfile, $start, $end)) {
            return false;
        }

        return ! $this->hasConfirmedOverlap($staffProfile, $start, $end, $ignoreAppointment);
    }

    public function hasConfirmedOverlap(
        StaffProfile $staffProfile,
        CarbonInterface $start,
        CarbonInterface $end,
        ?Appointment $ignoreAppointment = null,
    ): bool {
        return Appointment::query()
            ->where('staff_profile_id', $staffProfile->id)
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->when($ignoreAppointment, fn ($query) => $query->whereKeyNot($ignoreAppointment->getKey()))
            ->where('scheduled_start_at', '<', $end)
            ->where('scheduled_end_at', '>', $start)
            ->exists();
    }

    /**
     * Atomically assign and confirm a customer-selected slot.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function autoBook(
        array $attributes,
        Service $service,
        CarbonInterface $start,
        ?int $preferredStaffProfileId = null,
        ?int $changedBy = null,
    ): Appointment {
        $addonCodes = is_array($attributes['addon_codes'] ?? null) ? $attributes['addon_codes'] : [];
        $paidAddons = $this->addons->selected($addonCodes);
        $minimumLeadTimeMinutes = (int) config('casa.business_hours.customer_booking_lead_time_minutes', 30);
        $this->assertBookableStart($start, $service, 'requested_start_at', true, $addonCodes, $minimumLeadTimeMinutes);

        return $this->withAppointmentNumberRetry(function (string $number) use ($attributes, $service, $start, $preferredStaffProfileId, $changedBy, $addonCodes, $paidAddons, $minimumLeadTimeMinutes): Appointment {
            return DB::transaction(function () use ($attributes, $service, $start, $preferredStaffProfileId, $changedBy, $number, $addonCodes, $paidAddons, $minimumLeadTimeMinutes): Appointment {
                $promotionSuggestionId = isset($attributes['promotion_suggestion_id'])
                    ? (int) $attributes['promotion_suggestion_id']
                    : null;

                $voucher = $promotionSuggestionId
                    ? $this->addonVouchers->reserve($promotionSuggestionId, (int) $attributes['customer_profile_id'])
                    : null;
                $this->addons->assertDoesNotDuplicateVoucher($paidAddons, $voucher);
                $scheduleAddonCodes = [...$addonCodes, ...($voucher?->addon_code ? [$voucher->addon_code] : [])];
                $this->assertBookableStart($start, $service, 'requested_start_at', true, $scheduleAddonCodes, $minimumLeadTimeMinutes);

                $candidateIds = StaffProfile::query()
                    ->where('is_bookable', true)
                    ->whereHas('user', fn ($query) => $query->where('is_active', true))
                    ->whereHas('services', fn ($query) => $query->whereKey($service->id))
                    ->orderBy('id')
                    ->pluck('id');

                $staffProfiles = StaffProfile::query()
                    ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
                    ->whereKey($candidateIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $futureBookingCounts = Appointment::query()
                    ->selectRaw('staff_profile_id, COUNT(*) as aggregate')
                    ->whereIn('staff_profile_id', $candidateIds)
                    ->where('status', Appointment::STATUS_CONFIRMED)
                    ->where('scheduled_start_at', '>=', now())
                    ->groupBy('staff_profile_id')
                    ->pluck('aggregate', 'staff_profile_id');

                $available = $staffProfiles
                    ->filter(fn (StaffProfile $staff) => $this->isStaffAvailable($staff, $service, $start, null, null, $scheduleAddonCodes))
                    ->sortBy(fn (StaffProfile $staff) => sprintf(
                        '%012d-%012d',
                        (int) ($futureBookingCounts[$staff->id] ?? 0),
                        $staff->id,
                    ));

                $assignedStaff = $preferredStaffProfileId
                    ? $available->firstWhere('id', $preferredStaffProfileId)
                    : null;
                $assignedStaff ??= $available->first();

                if (! $assignedStaff) {
                    throw ValidationException::withMessages([
                        'requested_start_at' => __('Selected calendar slot is no longer available. Choose another date or time.'),
                    ]);
                }

                $end = $this->scheduledEnd($start, $service, $scheduleAddonCodes);
                $appointment = new Appointment;
                $appointment->fill([
                    ...$attributes,
                    'appointment_number' => $number,
                    'service_id' => $service->id,
                    'staff_profile_id' => $assignedStaff->id,
                    'preferred_staff_profile_id' => $preferredStaffProfileId,
                    'promotion_suggestion_id' => $promotionSuggestionId,
                    'requested_start_at' => $start,
                    'scheduled_start_at' => $start,
                    'scheduled_end_at' => $end,
                    'updated_by' => $changedBy,
                ]);

                $appointment = $this->applyStatus($appointment, Appointment::STATUS_CONFIRMED, $changedBy, __('Automatically confirmed from customer booking'));
                $this->addons->sync($appointment, $paidAddons);

                return $appointment;
            }, 3);
        });
    }

    public function schedule(
        Appointment $appointment,
        StaffProfile $staffProfile,
        Service $service,
        CarbonInterface $start,
        ?int $changedBy = null,
        ?string $reason = null,
        ?array $addonCodes = null,
    ): Appointment {
        $addonCodes ??= $appointment->exists
            ? $appointment->addons()->pluck('addon_code')->all()
            : [];
        $this->assertBookableStart($start, $service, 'scheduled_start_at', true, $addonCodes);

        if ($appointment->exists) {
            return $this->scheduleTransaction($appointment, $staffProfile, $service, $start, $changedBy, $reason, $addonCodes);
        }

        $attributes = $appointment->getAttributes();
        unset(
            $attributes[$appointment->getKeyName()],
            $attributes['appointment_number'],
            $attributes['status'],
            $attributes['confirmed_at'],
            $attributes['completed_at'],
            $attributes['cancelled_at'],
            $attributes['cancelled_by'],
        );

        return $this->withAppointmentNumberRetry(function (string $number) use ($attributes, $staffProfile, $service, $start, $changedBy, $reason, $addonCodes): Appointment {
            $candidate = new Appointment;
            $candidate->fill([
                ...$attributes,
                'appointment_number' => $number,
            ]);

            return $this->scheduleTransaction($candidate, $staffProfile, $service, $start, $changedBy, $reason, $addonCodes);
        });
    }

    public function changeStatus(Appointment $appointment, string $status, ?int $changedBy = null, ?string $reason = null): Appointment
    {
        $dirtyAttributes = $appointment->getDirty();
        unset($dirtyAttributes['status']);

        return DB::transaction(function () use ($appointment, $status, $changedBy, $reason, $dirtyAttributes): Appointment {
            $target = $appointment->exists
                ? Appointment::query()->lockForUpdate()->findOrFail($appointment->id)
                : $appointment;

            if ($appointment->exists) {
                $target->fill($dirtyAttributes);
            }

            return $this->applyStatus($target, $status, $changedBy, $reason);
        }, 3);
    }

    public function assertTransitionAllowed(Appointment $appointment, string $status): void
    {
        $allowed = $appointment->exists
            ? (Appointment::STATUS_TRANSITIONS[$appointment->status] ?? [])
            : Appointment::CREATION_STATUSES;

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('An appointment cannot move from :from to :to.', [
                    'from' => $appointment->status ?: __('a new record'),
                    'to' => $status,
                ]),
            ]);
        }
    }

    private function scheduleTransaction(
        Appointment $appointment,
        StaffProfile $staffProfile,
        Service $service,
        CarbonInterface $start,
        ?int $changedBy,
        ?string $reason,
        array $addonCodes,
    ): Appointment {
        $dirtyAttributes = $appointment->getDirty();

        return DB::transaction(function () use ($appointment, $staffProfile, $service, $start, $changedBy, $reason, $dirtyAttributes, $addonCodes): Appointment {
            $lockedStaff = StaffProfile::query()
                ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
                ->lockForUpdate()
                ->findOrFail($staffProfile->id);

            $target = $appointment->exists
                ? Appointment::query()->lockForUpdate()->findOrFail($appointment->id)
                : $appointment;

            if ($appointment->exists) {
                $target->fill($dirtyAttributes);
            }

            $paidAddons = $this->addons->selected($addonCodes);
            $voucherCode = $target->promotionSuggestion?->addon_code;
            $this->addons->assertDoesNotDuplicateVoucher($paidAddons, $target->promotionSuggestion);
            $scheduleAddonCodes = [...$addonCodes, ...($voucherCode ? [$voucherCode] : [])];
            $this->assertBookableStart($start, $service, 'scheduled_start_at', true, $scheduleAddonCodes);
            $end = $this->scheduledEnd($start, $service, $scheduleAddonCodes);

            if (! $this->isStaffAvailable($lockedStaff, $service, $start, $end, $target->exists ? $target : null)) {
                throw ValidationException::withMessages([
                    'scheduled_start_at' => __('Selected therapist is not available for this schedule.'),
                ]);
            }

            $target->fill([
                'service_id' => $service->id,
                'staff_profile_id' => $lockedStaff->id,
                'requested_start_at' => $start,
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $end,
                'updated_by' => $changedBy,
            ]);

            $target = $this->applyStatus($target, Appointment::STATUS_CONFIRMED, $changedBy, $reason);
            $this->addons->sync($target, $paidAddons);

            return $target;
        }, 3);
    }

    private function applyStatus(Appointment $appointment, string $status, ?int $changedBy, ?string $reason): Appointment
    {
        $this->assertTransitionAllowed($appointment, $status);

        if (in_array($status, [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW], true)
            && (! $appointment->staff_profile_id || ! $appointment->scheduled_start_at || ! $appointment->scheduled_end_at)) {
            throw ValidationException::withMessages([
                'status' => __('Completed and no-show outcomes require a confirmed therapist and schedule.'),
            ]);
        }

        $fromStatus = $appointment->status;
        $now = now();
        $metadata = match ($status) {
            Appointment::STATUS_CONFIRMED => [
                'confirmed_at' => $appointment->confirmed_at ?? $now,
                'completed_at' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
            ],
            Appointment::STATUS_COMPLETED => [
                'completed_at' => $appointment->completed_at ?? $now,
                'cancelled_at' => null,
                'cancelled_by' => null,
            ],
            Appointment::STATUS_CANCELLED => [
                'completed_at' => null,
                'cancelled_at' => $appointment->cancelled_at ?? $now,
                'cancelled_by' => $appointment->cancelled_by ?? $changedBy,
            ],
            Appointment::STATUS_NO_SHOW => [
                'completed_at' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
            ],
        };

        $appointment->forceFill([
            ...$metadata,
            'status' => $status,
            'updated_by' => $changedBy,
        ])->save();

        if (in_array($status, [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW], true)
            && $appointment->promotion_suggestion_id) {
            $appointment->loadMissing('promotionSuggestion');

            if ($appointment->promotionSuggestion) {
                $this->addonVouchers->release($appointment->promotionSuggestion);
            }
        }

        if ($fromStatus !== $status) {
            AppointmentStatusLog::query()->create([
                'appointment_id' => $appointment->id,
                'from_status' => $fromStatus,
                'to_status' => $status,
                'changed_by' => $changedBy,
                'reason' => $reason,
            ]);
        }

        return $appointment;
    }

    /**
     * @param  Closure(string): Appointment  $callback
     */
    private function withAppointmentNumberRetry(Closure $callback): Appointment
    {
        for ($attempt = 1; $attempt <= self::NUMBER_ALLOCATION_ATTEMPTS; $attempt++) {
            try {
                return $callback($this->nextAppointmentNumber());
            } catch (QueryException $exception) {
                if (! $this->isAppointmentNumberCollision($exception)) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'appointment_number' => __('The appointment number could not be allocated. Please submit the appointment again.'),
        ]);
    }

    private function isAppointmentNumberCollision(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'appointment_number')
            && (str_contains($message, 'unique') || str_contains($message, 'duplicate'));
    }
}
