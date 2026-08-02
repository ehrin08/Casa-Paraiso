<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AppointmentCalendar
{
    public function __construct(private readonly ScheduleWindowResolver $scheduleWindows) {}

    /**
     * @param  array{staff_profile_id?: int|null, service_id?: int|null, status?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function admin(Carbon $start, Carbon $end, string $mode, array $filters = [], string $audience = 'admin'): array
    {
        $staffProfiles = StaffProfile::query()
            ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
            ->where('is_bookable', true)
            ->whereHas('user', fn (Builder $query) => $query->where('is_active', true))
            ->when($filters['staff_profile_id'] ?? null, fn (Builder $query, int $id) => $query->whereKey($id))
            ->get()
            ->sortBy('user.name')
            ->values();

        $resources = $staffProfiles->map(fn (StaffProfile $staff) => $this->resource($staff));

        if ($mode === 'availability') {
            $events = $this->availabilityEvents($staffProfiles, $start, $end, $audience);
            $events = $events->concat($this->bookingBlockers($staffProfiles, $start, $end, $audience));
        } else {
            $query = $this->appointmentsInRange(Appointment::query(), $start, $end)
                ->with(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user'])
                ->whereNotNull('staff_profile_id')
                ->when($filters['service_id'] ?? null, fn (Builder $query, int $id) => $query->where('service_id', $id))
                ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($filters['staff_profile_id'] ?? null, fn (Builder $query, int $id) => $query->where('staff_profile_id', $id));

            $availability = $staffProfiles->flatMap(
                fn (StaffProfile $staff) => $this->effectiveAvailabilityEvents($staff, $start, $end),
            );
            $events = $availability->concat(
                $query->get()->map(fn (Appointment $appointment) => $this->appointmentEvent($appointment, $audience)),
            );
        }

        return $this->payload($start, $end, $resources, $events, $mode);
    }

    /**
     * @return array<string, mixed>
     */
    public function staff(StaffProfile $staffProfile, Carbon $start, Carbon $end, ?string $status = null): array
    {
        $staff = StaffProfile::query()
            ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
            ->findOrFail($staffProfile->id);
        $query = $this->appointmentsInRange(Appointment::query(), $start, $end)
            ->with(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user'])
            ->where('staff_profile_id', $staff->id)
            ->when($status, fn (Builder $query, string $value) => $query->where('status', $value));

        $resources = collect([$this->resource($staff)]);

        $events = $this->effectiveAvailabilityEvents($staff, $start, $end)
            ->concat($query->get()->map(fn (Appointment $appointment) => $this->appointmentEvent($appointment, 'staff')));

        return $this->payload($start, $end, $resources, $events, 'bookings');
    }

    /**
     * @return array<string, mixed>
     */
    public function customer(int $customerProfileId, Carbon $start, Carbon $end, ?string $status = null): array
    {
        $appointments = $this->appointmentsInRange(Appointment::query(), $start, $end)
            ->with(['service', 'staffProfile.user', 'preferredStaffProfile.user'])
            ->where('customer_profile_id', $customerProfileId)
            ->when($status, fn (Builder $query, string $value) => $query->where('status', $value))
            ->get();

        return $this->payload(
            $start,
            $end,
            collect(),
            $appointments->map(fn (Appointment $appointment) => $this->appointmentEvent($appointment, 'customer')),
            'month',
        );
    }

    private function appointmentsInRange(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query
            ->whereRaw('COALESCE(scheduled_start_at, requested_start_at) >= ?', [$start])
            ->whereRaw('COALESCE(scheduled_start_at, requested_start_at) < ?', [$end]);
    }

    /**
     * @param  Collection<int, StaffProfile>  $staffProfiles
     * @return Collection<int, array<string, mixed>>
     */
    private function availabilityEvents(Collection $staffProfiles, Carbon $start, Carbon $end, string $audience = 'admin'): Collection
    {
        $events = collect();

        for ($day = $start->copy()->startOfDay(); $day->lt($end); $day->addDay()) {
            foreach ($staffProfiles as $staff) {
                foreach ($this->scheduleWindows->effectiveWindows($staff, $day) as $index => $interval) {
                    $events->push($this->availabilityEvent($staff, 'availability', $interval, __('Available'), $audience !== 'admin', $index, null, $audience));
                }
            }
        }

        return $events;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function effectiveAvailabilityEvents(StaffProfile $staff, Carbon $start, Carbon $end): Collection
    {
        $events = collect();

        for ($day = $start->copy()->startOfDay(); $day->lt($end); $day->addDay()) {
            foreach ($this->scheduleWindows->effectiveWindows($staff, $day) as $index => $window) {
                $events->push($this->availabilityEvent($staff, 'availability', $window, __('Available'), true, $index));
            }
        }

        return $events;
    }

    /**
     * @param  Collection<int, StaffProfile>  $staffProfiles
     * @return Collection<int, array<string, mixed>>
     */
    private function bookingBlockers(Collection $staffProfiles, Carbon $start, Carbon $end, string $audience = 'admin'): Collection
    {
        return Appointment::query()
            ->with(['service', 'customerProfile.user', 'staffProfile.user'])
            ->whereIn('staff_profile_id', $staffProfiles->pluck('id'))
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('scheduled_start_at', '<', $end)
            ->where('scheduled_end_at', '>', $start)
            ->get()
            ->map(function (Appointment $appointment) use ($audience): array {
                $event = $this->appointmentEvent($appointment, $audience);
                $event['kind'] = 'booking_blocker';
                $event['read_only'] = true;

                return $event;
            });
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $interval
     * @return array<string, mixed>
     */
    private function availabilityEvent(
        StaffProfile $staff,
        string $kind,
        array $interval,
        string $title,
        bool $readOnly,
        int $sourceId,
        ?string $subtitle = null,
        string $audience = 'admin',
    ): array {
        $detailUrl = $audience === 'admin' ? match ($kind) {
            'weekly_availability' => route('admin.staff.weekly-schedules.edit', [$staff, $sourceId]),
            'available_exception', 'unavailable_exception' => route('admin.staff.schedule-exceptions.edit', [$staff, $sourceId]),
            default => null,
        } : null;

        return [
            'id' => $kind.'-'.$staff->id.'-'.$sourceId.'-'.$interval['start']->format('Ymd'),
            'kind' => $kind,
            'appointment_id' => null,
            'resource_id' => (string) $staff->id,
            'staff_profile_id' => $staff->id,
            'starts_at' => $interval['start']->toIso8601String(),
            'ends_at' => $interval['end']->toIso8601String(),
            'status' => $kind,
            'title' => $title,
            'subtitle' => $subtitle,
            'detail_url' => $detailUrl,
            'read_only' => $readOnly,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentEvent(Appointment $appointment, string $audience): array
    {
        $startsAt = $appointment->scheduled_start_at ?? $appointment->requested_start_at;
        $endsAt = $appointment->scheduled_end_at
            ?? $startsAt?->copy()->addMinutes($appointment->service?->duration_minutes ?? 60);
        $customerName = $appointment->customerProfile?->user?->name;
        $serviceName = $appointment->service?->name ?? __('Spa service');
        $preferredName = $appointment->preferredStaffProfile?->user?->name;
        $staffName = $appointment->staffProfile?->user?->name;

        return [
            'id' => 'appointment-'.$appointment->id,
            'kind' => 'appointment',
            'appointment_id' => $appointment->id,
            'resource_id' => (string) ($appointment->staff_profile_id ?? 'unassigned'),
            'staff_profile_id' => $appointment->staff_profile_id,
            'starts_at' => $startsAt?->toIso8601String(),
            'ends_at' => $endsAt?->toIso8601String(),
            'status' => $appointment->status,
            'title' => $audience === 'customer' ? $serviceName : ($customerName ?: $appointment->appointment_number),
            'subtitle' => $audience === 'customer'
                ? ($staffName ?: __('No therapist assigned'))
                : $serviceName,
            'appointment_number' => $appointment->appointment_number,
            'service_name' => $serviceName,
            'customer_name' => $audience === 'customer' ? null : $customerName,
            'preferred_staff_name' => $preferredName,
            'detail_url' => route($audience.'.appointments.show', $appointment),
            'read_only' => false,
        ];
    }

    /**
     * @return array{id: string, name: string, subtitle: string}
     */
    private function resource(StaffProfile $staff): array
    {
        return [
            'id' => (string) $staff->id,
            'name' => $staff->user?->name ?? __('Therapist'),
            'subtitle' => $staff->specialization ?: $staff->position ?: __('Spa therapist'),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $resources
     * @param  Collection<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function payload(Carbon $start, Carbon $end, Collection $resources, Collection $events, string $mode): array
    {
        return [
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'timezone' => config('casa.business_hours.timezone', config('app.timezone')),
            ],
            'mode' => $mode,
            'business_hours' => [
                'opens_at' => config('casa.business_hours.opens_at', '13:00'),
                'closes_at' => config('casa.business_hours.closes_at', '00:00'),
                'closes_next_day' => (bool) config('casa.business_hours.closes_next_day', true),
                'slot_interval_minutes' => (int) config('casa.business_hours.slot_interval_minutes', 30),
            ],
            'resources' => $resources->values()->all(),
            'events' => $events->filter(fn (array $event) => $event['starts_at'] && $event['ends_at'])->values()->all(),
        ];
    }
}
