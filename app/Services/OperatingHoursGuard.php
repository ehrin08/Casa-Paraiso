<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\StaffProfile;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Applies a change to the operating hours only when every confirmed appointment still fits. */
class OperatingHoursGuard
{
    public function __construct(private readonly ScheduleWindowResolver $windows) {}

    /**
     * Run $change, then check the confirmed appointments from $from to $to (null: every later date).
     * When any falls outside the new hours, the change is rolled back and those appointments are returned.
     *
     * @return Collection<int, Appointment>
     */
    public function attempt(Closure $change, string $from, ?string $to): Collection
    {
        $timezone = (string) config('casa.business_hours.timezone', 'Asia/Manila');
        $rangeStart = Carbon::createFromFormat('!Y-m-d', $from, $timezone);

        DB::beginTransaction();

        try {
            // Bookings lock therapist rows before checking the schedule, so locking them all first
            // means no booking can land between the check below and the commit.
            StaffProfile::query()->orderBy('id')->lockForUpdate()->pluck('id');

            $change();

            $resolver = $this->windows->freshForTransaction();
            $conflicts = Appointment::query()
                ->with(['customerProfile.user', 'staffProfile.user'])
                ->where('status', Appointment::STATUS_CONFIRMED)
                ->where('scheduled_start_at', '>=', $rangeStart)
                ->when($to, fn ($query) => $query->where('scheduled_start_at', '<', Carbon::createFromFormat('!Y-m-d', $to, $timezone)->addDay()))
                ->orderBy('scheduled_start_at')
                ->get()
                ->reject(fn (Appointment $appointment) => $appointment->scheduled_start_at && $appointment->scheduled_end_at
                    && $resolver->withinBusinessHours($appointment->scheduled_start_at, $appointment->scheduled_end_at))
                ->values();

            $conflicts->isEmpty() ? DB::commit() : DB::rollBack();

            return $conflicts;
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }
}
