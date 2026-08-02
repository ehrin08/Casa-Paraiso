<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\StaffScheduleConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffScheduleExceptionRequest;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Services\StaffScheduleConflictGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffScheduleExceptionController extends Controller
{
    public function create(Request $request, StaffProfile $staff): View
    {
        $data = $request->validate([
            'exception_date' => ['nullable', 'date'],
            'exception_type' => ['nullable', 'in:available,unavailable'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'ends_next_day' => ['nullable', 'boolean'],
        ]);

        return view('admin.staff.schedule-exceptions.create', [
            'staffProfile' => $staff->load('user'),
            'scheduleException' => new StaffScheduleException([
                'exception_date' => $data['exception_date'] ?? now()->toDateString(),
                'exception_type' => $data['exception_type'] ?? StaffScheduleException::TYPE_UNAVAILABLE,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'ends_next_day' => (bool) ($data['ends_next_day'] ?? false),
            ]),
        ]);
    }

    public function store(StaffScheduleExceptionRequest $request, StaffProfile $staff, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $staff, $guard): void {
                $staff->scheduleExceptions()->create([
                    ...$this->exceptionData($request),
                    'created_by' => $request->user()->id,
                ]);
                $guard->assertFutureAppointmentsRemainCovered($staff);
            });
        } catch (StaffScheduleConflictException $exception) {
            return $this->conflictRedirect($exception);
        }

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'schedule-exception-created');
    }

    public function edit(StaffProfile $staff, StaffScheduleException $scheduleException): View
    {
        $this->assertExceptionBelongsToStaff($staff, $scheduleException);

        return view('admin.staff.schedule-exceptions.edit', [
            'staffProfile' => $staff->load('user'),
            'scheduleException' => $scheduleException,
        ]);
    }

    public function update(StaffScheduleExceptionRequest $request, StaffProfile $staff, StaffScheduleException $scheduleException, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        $this->assertExceptionBelongsToStaff($staff, $scheduleException);

        try {
            DB::transaction(function () use ($request, $staff, $scheduleException, $guard): void {
                $scheduleException->update($this->exceptionData($request));
                $guard->assertFutureAppointmentsRemainCovered($staff);
            });
        } catch (StaffScheduleConflictException $exception) {
            return $this->conflictRedirect($exception);
        }

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'schedule-exception-updated');
    }

    public function destroy(StaffProfile $staff, StaffScheduleException $scheduleException, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        $this->assertExceptionBelongsToStaff($staff, $scheduleException);

        try {
            DB::transaction(function () use ($staff, $scheduleException, $guard): void {
                $scheduleException->delete();
                $guard->assertFutureAppointmentsRemainCovered($staff);
            });
        } catch (StaffScheduleConflictException $exception) {
            return $this->conflictRedirect($exception, false);
        }

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'schedule-exception-deleted');
    }

    private function exceptionData(Request $request): array
    {
        $data = $request->validated();

        if ($data['exception_type'] === StaffScheduleException::TYPE_UNAVAILABLE && empty($data['start_time']) && empty($data['end_time'])) {
            $data['start_time'] = null;
            $data['end_time'] = null;
            $data['ends_next_day'] = false;
        } else {
            $data['ends_next_day'] = $request->boolean('ends_next_day');
        }

        return $data;
    }

    private function assertExceptionBelongsToStaff(StaffProfile $staff, StaffScheduleException $scheduleException): void
    {
        abort_unless($scheduleException->staff_profile_id === $staff->id, 404);
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
