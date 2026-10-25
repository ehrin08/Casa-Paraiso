<?php

namespace App\Services;

use App\Exceptions\StaffScheduleConflictException;
use App\Models\Appointment;
use App\Models\StaffProfile;
use App\Models\StaffScheduleShift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StaffScheduleConflictGuard
{
    public function __construct(private readonly ScheduleWindowResolver $scheduleWindows) {}

    public function assertFutureAppointmentsRemainCovered(StaffProfile $staffProfile): void
    {
        $staff = StaffProfile::query()->with([
            'user',
            'weeklySchedules' => fn ($query) => $query->sharedLock(),
            'scheduleExceptions' => fn ($query) => $query->sharedLock(),
        ])->findOrFail($staffProfile->id);
        $resolver = $this->scheduleWindows->freshForTransaction();

        try {
            $conflicts = $this->futureConfirmedAppointments($staff)->with('service')->get()
                ->reject(fn (Appointment $appointment) => $appointment->scheduled_start_at
                    && $appointment->scheduled_end_at
                    && $resolver->covers($staff, $appointment->scheduled_start_at, $appointment->scheduled_end_at));
            $this->throwIfConflicts($conflicts->map(fn (Appointment $appointment) => $this->describe($appointment, $staff))->all());
        } finally {
            $this->scheduleWindows->clearCache();
        }
    }

    /** Includes removed therapists and bookings in later weeks inheriting this roster. */
    public function rosterConflicts(Carbon $weekStart, ?Carbon $until, Collection $shifts, bool $lock = false): array
    {
        $query = $this->protectedAppointments()
            ->where('scheduled_start_at', '>=', $weekStart)
            ->when($until, fn ($query) => $query->where('scheduled_start_at', '<', $until))
            ->with([
                'service',
                'staffProfile' => fn ($query) => $query->withTrashed()->with([
                    'user',
                    'scheduleExceptions' => fn ($query) => $query->when($lock, fn ($query) => $query->sharedLock()),
                ]),
            ])->orderBy('scheduled_start_at')->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $byDay = $shifts->map(fn (array $shift) => new StaffScheduleShift($shift))
            ->groupBy(fn (StaffScheduleShift $shift) => $shift->staff_profile_id.':'.$shift->schedule_date->dayOfWeek);
        $resolver = $lock ? $this->scheduleWindows->freshForTransaction() : $this->scheduleWindows;

        return $query->get()->filter(function (Appointment $appointment) use ($resolver, $byDay): bool {
            $staff = $appointment->staffProfile;
            if (! $staff || ! $appointment->scheduled_start_at || ! $appointment->scheduled_end_at) {
                return true;
            }
            $candidate = $byDay->get($staff->id.':'.$appointment->scheduled_start_at->dayOfWeek, collect());

            return ! $resolver->covers($staff, $appointment->scheduled_start_at, $appointment->scheduled_end_at, $candidate);
        })->map(fn (Appointment $appointment) => $this->describe($appointment, $appointment->staffProfile))->values()->all();
    }

    public function assertCanMakeUnavailable(StaffProfile $staffProfile): void
    {
        $this->throwIfConflicts($this->futureConfirmedAppointments($staffProfile)->get()
            ->map(fn (Appointment $appointment) => $this->describe($appointment, $staffProfile))->all());
    }

    public function assertCanRemoveServices(StaffProfile $staffProfile, array $removedServiceIds): void
    {
        if ($removedServiceIds !== []) {
            $this->throwIfConflicts($this->futureConfirmedAppointments($staffProfile)->whereIn('service_id', $removedServiceIds)->get()
                ->map(fn (Appointment $appointment) => $this->describe($appointment, $staffProfile))->all());
        }
    }

    public function throwIfConflicts(array $conflicts): void
    {
        if ($conflicts !== []) {
            throw new StaffScheduleConflictException(array_values($conflicts));
        }
    }

    private function protectedAppointments(): Builder
    {
        return Appointment::query()->where('status', Appointment::STATUS_CONFIRMED)
            ->where(function (Builder $query): void {
                $query->where('scheduled_end_at', '>', now())
                    ->orWhere(fn (Builder $query) => $query->whereNull('scheduled_end_at')->where('scheduled_start_at', '>=', now()));
            });
    }

    private function futureConfirmedAppointments(StaffProfile $staffProfile): Builder
    {
        return $this->protectedAppointments()->where('staff_profile_id', $staffProfile->id)
            ->orderBy('scheduled_start_at')->orderBy('id')->lockForUpdate();
    }

    private function describe(Appointment $appointment, ?StaffProfile $staff): array
    {
        return [
            'id' => $appointment->id,
            'number' => $appointment->appointment_number,
            'staff_profile_id' => $appointment->staff_profile_id,
            'therapist' => $staff?->user?->name ?? __('Unavailable therapist'),
            'starts_at' => $appointment->scheduled_start_at?->copy()->timezone('Asia/Manila')->toDateTimeString() ?? '',
            'ends_at' => $appointment->scheduled_end_at?->copy()->timezone('Asia/Manila')->toDateTimeString() ?? '',
            'reason' => __('The confirmed visit would fall outside working hours or inside a date exception.'),
            'url' => route('admin.appointments.show', $appointment->id),
        ];
    }
}
