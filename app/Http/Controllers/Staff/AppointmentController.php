<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffAppointmentUpdateRequest;
use App\Models\Appointment;
use App\Models\Transaction;
use App\Services\AppointmentWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $staffProfile = $request->user()->staffProfile;

        return view('staff.appointments.index', [
            'staffProfile' => $staffProfile,
            'initialWeek' => now()->startOfWeek(Carbon::SUNDAY)->toDateString(),
        ]);
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $this->authorizeOperationalAccess($request, $appointment);

        $appointment->load(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'promotionSuggestion', 'addons', 'transactions', 'feedback']);

        return view('staff.appointments.show', [
            'appointment' => $appointment,
            'transaction' => new Transaction([
                'appointment_id' => $appointment->id,
                'customer_profile_id' => $appointment->customer_profile_id,
                'service_id' => $appointment->service_id,
                'amount' => $appointment->expectedAmount(),
                'payment_status' => Transaction::PAYMENT_PAID,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now(),
            ]),
            'appointments' => collect([$appointment]),
        ]);
    }

    public function update(StaffAppointmentUpdateRequest $request, Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        $this->authorizeOperationalAccess($request, $appointment);

        $data = $request->validated();
        $staffProfile = $request->user()->staffProfile;
        $status = $data['status'] ?? $appointment->status;

        if ($status === Appointment::STATUS_CONFIRMED) {
            abort_unless($staffProfile, 403);

            $scheduledStart = Carbon::parse($data['scheduled_start_at']);
            $scheduleChanged = $appointment->status !== Appointment::STATUS_CONFIRMED
                || (int) $appointment->staff_profile_id !== (int) $staffProfile->id
                || ! $appointment->scheduled_start_at?->equalTo($scheduledStart);

            $appointment->fill([
                'internal_notes' => $data['internal_notes'] ?? $appointment->internal_notes,
                'updated_by' => $request->user()->id,
            ]);

            if (! $scheduleChanged) {
                $workflow->changeStatus(
                    $appointment,
                    Appointment::STATUS_CONFIRMED,
                    $request->user()->id,
                    $data['reason'] ?? null,
                );

                return redirect()
                    ->route('staff.appointments.show', $appointment)
                    ->with('status', 'appointment-updated');
            }

            $workflow->schedule(
                $appointment,
                $staffProfile,
                $appointment->service,
                $scheduledStart,
                $request->user()->id,
                $data['reason'] ?? null,
            );

            return redirect()
                ->route('staff.appointments.show', $appointment)
                ->with('status', 'appointment-updated');
        }

        if (in_array($status, [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW, Appointment::STATUS_CANCELLED], true)
            && (int) $appointment->staff_profile_id !== (int) $staffProfile?->id) {
            abort(403);
        }

        $appointment->fill([
            'internal_notes' => $data['internal_notes'] ?? $appointment->internal_notes,
            'updated_by' => $request->user()->id,
        ]);

        $workflow->changeStatus($appointment, $status, $request->user()->id, $data['reason'] ?? null);

        return redirect()
            ->route('staff.appointments.show', $appointment)
            ->with('status', 'appointment-updated');
    }

    private function authorizeOperationalAccess(Request $request, Appointment $appointment): void
    {
        $staffProfile = $request->user()->staffProfile;
        $allowed = (int) $appointment->staff_profile_id === (int) ($staffProfile?->id ?? 0);

        abort_unless($allowed, 403);
    }
}
