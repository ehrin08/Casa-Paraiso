<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\StaffScheduleConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffWeeklyScheduleRequest;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Services\StaffScheduleConflictGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffWeeklyScheduleController extends Controller
{
    public function create(Request $request, StaffProfile $staff): View
    {
        $data = $request->validate([
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'ends_next_day' => ['nullable', 'boolean'],
        ]);

        return view('admin.staff.weekly-schedules.create', [
            'staffProfile' => $staff->load('user'),
            'weeklySchedule' => new StaffWeeklySchedule([
                'day_of_week' => $data['day_of_week'] ?? now()->dayOfWeek,
                'start_time' => $data['start_time'] ?? '13:00',
                'end_time' => $data['end_time'] ?? '14:00',
                'ends_next_day' => (bool) ($data['ends_next_day'] ?? false),
                'is_available' => true,
            ]),
        ]);
    }

    public function store(StaffWeeklyScheduleRequest $request, StaffProfile $staff, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $staff, $guard): void {
                $staff->weeklySchedules()->create([
                    ...$request->validated(),
                    'ends_next_day' => $request->boolean('ends_next_day'),
                    'is_available' => $request->boolean('is_available'),
                ]);
                $guard->assertFutureAppointmentsRemainCovered($staff);
            });
        } catch (StaffScheduleConflictException $exception) {
            return $this->conflictRedirect($exception);
        }

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'weekly-schedule-created');
    }

    public function edit(StaffProfile $staff, StaffWeeklySchedule $weeklySchedule): View
    {
        $this->assertScheduleBelongsToStaff($staff, $weeklySchedule);

        return view('admin.staff.weekly-schedules.edit', [
            'staffProfile' => $staff->load('user'),
            'weeklySchedule' => $weeklySchedule,
        ]);
    }

    public function update(StaffWeeklyScheduleRequest $request, StaffProfile $staff, StaffWeeklySchedule $weeklySchedule, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        $this->assertScheduleBelongsToStaff($staff, $weeklySchedule);

        try {
            DB::transaction(function () use ($request, $staff, $weeklySchedule, $guard): void {
                $weeklySchedule->update([
                    ...$request->validated(),
                    'ends_next_day' => $request->boolean('ends_next_day'),
                    'is_available' => $request->boolean('is_available'),
                ]);
                $guard->assertFutureAppointmentsRemainCovered($staff);
            });
        } catch (StaffScheduleConflictException $exception) {
            return $this->conflictRedirect($exception);
        }

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'weekly-schedule-updated');
    }

    public function destroy(StaffProfile $staff, StaffWeeklySchedule $weeklySchedule, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        $this->assertScheduleBelongsToStaff($staff, $weeklySchedule);

        try {
            DB::transaction(function () use ($staff, $weeklySchedule, $guard): void {
                $weeklySchedule->delete();
                $guard->assertFutureAppointmentsRemainCovered($staff);
            });
        } catch (StaffScheduleConflictException $exception) {
            return $this->conflictRedirect($exception, false);
        }

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'weekly-schedule-deleted');
    }

    private function assertScheduleBelongsToStaff(StaffProfile $staff, StaffWeeklySchedule $weeklySchedule): void
    {
        abort_unless($weeklySchedule->staff_profile_id === $staff->id, 404);
    }

    private function conflictRedirect(StaffScheduleConflictException $exception, bool $withInput = true): RedirectResponse
    {
        $response = back()->withErrors([
            'schedule' => __('Change blocked because it would conflict with a confirmed appointment. Reschedule or cancel the affected visit first.'),
        ])->with('schedule_conflicts', collect($exception->conflicts)->map(fn (array $conflict) => [
            ...$conflict,
            'url' => route('admin.appointments.show', $conflict['id']),
        ])->all());

        return $withInput ? $response->withInput() : $response;
    }
}
