<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAppointmentCompletionRequest;
use App\Http\Requests\AdminAppointmentOutcomeRequest;
use App\Http\Requests\AdminAppointmentStoreRequest;
use App\Http\Requests\AdminAppointmentUpdateRequest;
use App\Models\ApplicationSetting;
use App\Models\Addon;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Services\AppointmentCompletion;
use App\Services\AppointmentManagement;
use App\Services\AppointmentWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $summary = Appointment::query()
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS confirmed', [Appointment::STATUS_CONFIRMED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [Appointment::STATUS_COMPLETED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', [Appointment::STATUS_CANCELLED])
            ->first();

        return view('reception.appointments.index', [
            'mode' => in_array($request->query('mode'), ['bookings', 'availability'], true) ? $request->query('mode') : 'bookings',
            'initialWeek' => now()->startOfWeek(Carbon::SUNDAY)->toDateString(),
            'summary' => ['confirmed' => (int) $summary?->confirmed, 'completed' => (int) $summary?->completed, 'cancelled' => (int) $summary?->cancelled],
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'staffProfiles' => StaffProfile::query()->with('user')->where('is_bookable', true)->whereHas('user', fn ($query) => $query->where('is_active', true))->get()->sortBy('user.name')->values(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = $request->validate([
            'customer_profile_id' => ['nullable', 'integer', 'exists:customer_profiles,id'],
            'staff_profile_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'scheduled_start_at' => ['nullable', 'date'],
        ]);
        return view('reception.appointments.form', [
            'appointment' => new Appointment([
                'scheduled_start_at' => filled($data['scheduled_start_at'] ?? null) ? Carbon::parse($data['scheduled_start_at']) : now()->addDay()->setTime(13, 0),
                'status' => Appointment::STATUS_CONFIRMED,
                'customer_profile_id' => $data['customer_profile_id'] ?? null,
                'staff_profile_id' => $data['staff_profile_id'] ?? null,
            ]),
            ...$this->selectors(),
        ]);
    }

    public function store(AdminAppointmentStoreRequest $request, AppointmentManagement $management): RedirectResponse
    {
        $appointment = $management->persist(new Appointment, $request->validated(), $request->user()->id);

        return redirect()->route('reception.appointments.show', $appointment)->with('status', 'appointment-created');
    }

    public function show(Appointment $appointment): View
    {
        $appointment->load(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'promotionSuggestion', 'addons', 'transactions.recorder', 'statusLogs.changedBy']);

        return view('reception.appointments.show', [
            'appointment' => $appointment,
            'transaction' => new Transaction([
                'appointment_id' => $appointment->id,
                'customer_profile_id' => $appointment->customer_profile_id,
                'service_id' => $appointment->service_id,
                'amount' => $appointment->expectedAmount(),
                'payment_status' => Transaction::PAYMENT_PAID,
                'payment_method' => ApplicationSetting::current()->default_payment_method,
                'paid_at' => now(),
            ]),
            ...$this->selectors(),
        ]);
    }

    public function edit(Appointment $appointment): View
    {
        $appointment->load(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'promotionSuggestion', 'addons']);

        return view('reception.appointments.form', ['appointment' => $appointment, ...$this->selectors($appointment)]);
    }

    public function update(AdminAppointmentUpdateRequest $request, Appointment $appointment, AppointmentManagement $management): RedirectResponse
    {
        $management->persist($appointment, $request->validated(), $request->user()->id);

        return redirect()->route('reception.appointments.show', $appointment)->with('status', 'appointment-updated');
    }

    public function complete(AdminAppointmentCompletionRequest $request, Appointment $appointment, AppointmentCompletion $completion): RedirectResponse
    {
        $transaction = $completion->complete($appointment, $request->validated(), $request->user()->id);

        return redirect()->route('reception.transactions.show', $transaction)->with('status', 'appointment-completed');
    }

    public function outcome(AdminAppointmentOutcomeRequest $request, Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
            throw ValidationException::withMessages(['status' => __('Only a confirmed appointment can receive this outcome.')]);
        }

        $data = $request->validated();
        $workflow->changeStatus($appointment, $data['status'], $request->user()->id, $data['reason'] ?? null);

        return redirect()->route('reception.appointments.show', $appointment)->with('status', 'appointment-updated');
    }

    public function availableTherapists(Request $request, AppointmentWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['service_id' => ['required', 'integer', 'exists:services,id'], 'starts_at' => ['required', 'date'], 'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'], 'addon_codes' => ['nullable', 'array'], 'addon_codes.*' => ['required', 'string', 'distinct']]);
        $appointment = ! empty($data['appointment_id']) ? Appointment::query()->findOrFail($data['appointment_id']) : null;
        $service = Service::query()->findOrFail($data['service_id']);
        $start = Carbon::parse($data['starts_at']);
        $addonCodes = $data['addon_codes'] ?? [];
        $workflow->assertBookableStart($start, $service, 'starts_at', true, $addonCodes);
        $end = $workflow->scheduledEnd($start, $service, $addonCodes);
        $therapists = StaffProfile::query()->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
            ->where('is_bookable', true)->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->whereHas('services', fn ($query) => $query->whereKey($service->id))->get()
            ->filter(fn (StaffProfile $staff) => $workflow->isStaffAvailable($staff, $service, $start, $end, $appointment))
            ->sortBy('user.name')->values()->map(fn (StaffProfile $staff) => ['id' => $staff->id, 'name' => $staff->user?->name, 'specialization' => $staff->specialization]);

        return response()->json(['therapists' => $therapists]);
    }

    private function selectors(?Appointment $appointment = null): array
    {
        $services = Service::query()->where('is_active', true)->orderBy('name')->get();
        if ($appointment?->service && ! $services->contains('id', $appointment->service_id)) {
            $services->push($appointment->service);
        }

        return [
            'customers' => CustomerProfile::query()->with('user')->get()->sortBy('user.name')->values(),
            'services' => $services,
            'staffProfiles' => StaffProfile::query()->with(['user', 'services'])->where('is_bookable', true)->whereHas('user', fn ($query) => $query->where('is_active', true))->get()->sortBy('user.name')->values(),
            'addons' => $this->addonSelectors($appointment),
        ];
    }

    private function addonSelectors(?Appointment $appointment = null)
    {
        $addons = Addon::query()->where('is_active', true)->orderBy('name')->get();
        foreach ($appointment?->addons ?? [] as $snapshot) {
            if (! $addons->contains('code', $snapshot->addon_code)) {
                $addons->push(new Addon(['code' => $snapshot->addon_code, 'name' => $snapshot->addon_name, 'price' => $snapshot->price, 'duration_minutes' => $snapshot->duration_minutes, 'is_active' => false]));
            }
        }
        return $addons->sortBy('name')->values();
    }
}
