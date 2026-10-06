<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAppointmentCompletionRequest;
use App\Http\Requests\AdminAppointmentOutcomeRequest;
use App\Http\Requests\AdminAppointmentStoreRequest;
use App\Http\Requests\AdminAppointmentUpdateRequest;
use App\Http\Requests\AdminCalendarAppointmentStoreRequest;
use App\Models\Addon;
use App\Models\ApplicationSetting;
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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $today = now('Asia/Manila')->startOfDay();
        $requestedMode = $request->old('return_to') === 'index'
            ? $request->old('mode', $request->query('mode'))
            : $request->query('mode');
        $mode = in_array($requestedMode, ['bookings', 'availability'], true)
            ? (string) $requestedMode
            : 'bookings';

        $summary = Appointment::query()
            ->where('scheduled_start_at', '>=', $today)
            ->where('scheduled_start_at', '<', $today->copy()->addDay())
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS confirmed', [Appointment::STATUS_CONFIRMED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [Appointment::STATUS_COMPLETED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', [Appointment::STATUS_CANCELLED])
            ->first();

        $serviceQueue = Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user', 'addons'])
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('scheduled_start_at', '<', $today->copy()->addDay())
            ->orderBy('scheduled_start_at')->orderBy('id')
            ->paginate((int) config('casa.pagination.per_page', 15), ['*'], 'queue_page')
            ->withQueryString()->fragment('service-queue');
        $completionAppointments = $serviceQueue->getCollection()
            ->filter(fn (Appointment $appointment) => $appointment->scheduled_start_at?->lte(now()));

        // Keep validation recovery available if concurrent queue changes move the row off this page.
        if (preg_match('/^admin-appointment-completion-(\d+)$/', (string) $request->old('_modal'), $matches)
            && ! $completionAppointments->contains('id', (int) $matches[1])) {
            $recoveryAppointment = Appointment::query()
                ->with(['customerProfile.user', 'service', 'addons'])->find((int) $matches[1]);
            if ($recoveryAppointment) {
                $completionAppointments->push($recoveryAppointment);
            }
        }

        return view('admin.appointments.index', [
            'mode' => $mode,
            'initialWeek' => $today->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'today' => $today,
            'transaction' => new Transaction(['payment_method' => ApplicationSetting::current()->default_payment_method]),
            'summary' => ['confirmed' => (int) $summary?->confirmed, 'completed' => (int) $summary?->completed, 'cancelled' => (int) $summary?->cancelled],
            'services' => Service::query()->where('is_active', true)->orderBy('name')->get(),
            'staffProfiles' => StaffProfile::query()
                ->with('user')
                ->where('is_bookable', true)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->get()
                ->sortBy('user.name'),
            'serviceQueue' => $serviceQueue,
            'completionAppointments' => $completionAppointments,
        ]);
    }

    public function records(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
            'staff_profile_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $search = trim($filters['q'] ?? '');
        $appointments = Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('appointment_number', 'like', '%'.$search.'%')
                    ->orWhereHas('customerProfile', fn ($query) => $query->search($search));
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['staff_profile_id'] ?? null, fn ($query, $id) => $query->where('staff_profile_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->where('scheduled_start_at', '>=', Carbon::parse($date, 'Asia/Manila')->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->where('scheduled_start_at', '<', Carbon::parse($date, 'Asia/Manila')->addDay()->startOfDay()))
            ->orderByDesc('scheduled_start_at')->orderByDesc('id')
            ->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        return view('admin.appointments.records', [
            'appointments' => $appointments,
            'filters' => $filters,
            'staffProfiles' => StaffProfile::query()->with('user')->get()->sortBy('user.name'),
        ]);
    }

    public function create(Request $request): View
    {
        $data = $request->validate([
            'customer_profile_id' => ['nullable', 'integer', 'exists:customer_profiles,id'],
            'staff_profile_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'scheduled_start_at' => ['nullable', 'date'],
        ]);
        $scheduledStart = ! empty($data['scheduled_start_at']) ? Carbon::parse($data['scheduled_start_at']) : null;
        $scheduledStart ??= now()->addDay()->setTime(13, 0);

        return view('admin.appointments.create', $this->formData(new Appointment([
            'scheduled_start_at' => $scheduledStart,
            'status' => Appointment::STATUS_CONFIRMED,
            'customer_profile_id' => $data['customer_profile_id'] ?? null,
            'staff_profile_id' => $data['staff_profile_id'] ?? null,
        ])));
    }

    public function store(AdminAppointmentStoreRequest $request, AppointmentManagement $management): RedirectResponse
    {
        $appointment = $management->persist(new Appointment, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.appointments.show', $appointment)
            ->with('status', 'appointment-created');
    }

    public function storeFromCalendar(AdminCalendarAppointmentStoreRequest $request, AppointmentManagement $management): RedirectResponse
    {
        $management->persist(new Appointment, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.appointments.index')
            ->with('status', 'appointment-created');
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $appointment->load([
            'customerProfile.user',
            'service',
            'staffProfile.user',
            'preferredStaffProfile.user',
            'promotionSuggestion',
            'addons',
            'transactions.recorder',
            'feedback',
            'statusLogs.changedBy',
            'creator',
        ]);

        $activity = $appointment->statusLogs->map(fn ($log) => [
            'label' => __('Appointment :status', ['status' => str_replace('_', '-', $log->to_status)]),
            'actor' => $log->changedBy?->name ?: __('System'),
            'at' => $log->created_at,
            'order' => $log->id,
            'status' => $log->to_status,
        ])->push([
            'label' => __('Booking created'),
            'actor' => $appointment->creator?->name ?: __('System'),
            'at' => $appointment->created_at,
            'order' => 0,
            'status' => null,
        ])->sortBy([['at', 'desc'], ['order', 'desc']])->values();

        return view('admin.appointments.show', [
            'isPreview' => $request->header('X-Casa-Panel') === '1' && $request->query('preview') === '1',
            'appointment' => $appointment,
            'activity' => $activity,
            'latestTransaction' => $appointment->transactions->sortByDesc('id')->first(),
            'voucherName' => $appointment->promotionSuggestion?->addonName(),
            'transaction' => new Transaction([
                'payment_method' => ApplicationSetting::current()->default_payment_method,
            ]),
        ]);
    }

    public function edit(Appointment $appointment): View
    {
        $appointment->load(['customerProfile.user', 'service', 'staffProfile.user', 'preferredStaffProfile.user', 'promotionSuggestion', 'addons']);

        return view('admin.appointments.edit', $this->formData($appointment));
    }

    public function update(AdminAppointmentUpdateRequest $request, Appointment $appointment, AppointmentManagement $management): RedirectResponse
    {
        $management->persist($appointment, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.appointments.show', $appointment)
            ->with('status', 'appointment-updated');
    }

    public function complete(
        AdminAppointmentCompletionRequest $request,
        Appointment $appointment,
        AppointmentCompletion $completion,
    ): RedirectResponse {
        $data = $request->validated();
        $transaction = $completion->complete($appointment, $data, $request->user()->id);

        if (($data['return_to'] ?? null) === 'index') {
            return $this->redirectToQueue($data, 'appointment-completed');
        }

        return redirect()
            ->route('admin.transactions.show', $transaction)
            ->with('status', 'appointment-completed');
    }

    public function outcome(
        AdminAppointmentOutcomeRequest $request,
        Appointment $appointment,
        AppointmentWorkflow $workflow,
    ): RedirectResponse {
        $data = $request->validated();

        if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
            throw ValidationException::withMessages(['status' => __('Only a confirmed appointment can receive this outcome.')]);
        }

        if ($data['status'] === Appointment::STATUS_NO_SHOW
            && (! $appointment->scheduled_start_at || $appointment->scheduled_start_at->isFuture())) {
            throw ValidationException::withMessages(['status' => __('A no-show can be recorded once the scheduled start time is reached.')]);
        }

        $workflow->changeStatus($appointment, $data['status'], $request->user()->id, $data['reason'] ?? null);

        if (($data['return_to'] ?? null) === 'index') {
            return $this->redirectToQueue($data, 'appointment-updated');
        }

        return redirect()->route('admin.appointments.index')->with('status', 'appointment-updated');
    }

    private function redirectToQueue(array $context, string $status): RedirectResponse
    {
        $remaining = Appointment::query()
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('scheduled_start_at', '<', now('Asia/Manila')->startOfDay()->addDay())
            ->count();
        $lastPage = max(1, (int) ceil($remaining / (int) config('casa.pagination.per_page', 15)));
        $parameters = [
            'queue_page' => min((int) ($context['queue_page'] ?? 1), $lastPage),
            'mode' => $context['mode'] ?? 'bookings',
        ];

        return redirect()->to(route('admin.appointments.index', $parameters).'#service-queue')->with('status', $status);
    }

    public function availableTherapists(Request $request, AppointmentWorkflow $workflow): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'addon_codes' => ['nullable', 'array'],
            'addon_codes.*' => ['required', 'string', 'distinct'],
        ]);
        $appointment = ! empty($data['appointment_id']) ? Appointment::query()->findOrFail($data['appointment_id']) : null;
        $service = Service::query()->findOrFail($data['service_id']);

        if (! $service->is_active && (int) $appointment?->service_id !== (int) $service->id) {
            throw ValidationException::withMessages([
                'service_id' => __('Select an active service.'),
            ]);
        }

        $start = Carbon::parse($data['starts_at']);
        $addonCodes = $data['addon_codes'] ?? [];
        if ($appointment?->promotionSuggestion?->addon_code) {
            $addonCodes = array_values(array_unique([...$addonCodes, $appointment->promotionSuggestion->addon_code]));
        }
        $workflow->assertBookableStart($start, $service, 'starts_at', true, $addonCodes);
        $end = $workflow->scheduledEnd($start, $service, $addonCodes);

        $therapists = StaffProfile::query()
            ->with(['user', 'services', 'weeklySchedules', 'scheduleExceptions'])
            ->where('is_bookable', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->whereHas('services', fn ($query) => $query->whereKey($service->id))
            ->get()
            ->filter(fn (StaffProfile $staff) => $workflow->isStaffAvailable($staff, $service, $start, $end, $appointment))
            ->sortBy('user.name')
            ->values()
            ->map(fn (StaffProfile $staff) => [
                'id' => $staff->id,
                'name' => $staff->user?->name,
                'specialization' => $staff->specialization,
            ]);

        return response()->json(['therapists' => $therapists]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Appointment $appointment): array
    {
        $customers = CustomerProfile::query()->with('user')->whereKey((int) old('customer_profile_id', $appointment->customer_profile_id))->get();
        $services = Service::query()->where('is_active', true)->orderBy('name')->get();
        $staffProfiles = StaffProfile::query()
            ->with(['user', 'services'])
            ->where('is_bookable', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->get();

        if ($appointment->customerProfile && ! $customers->contains('id', $appointment->customerProfile->id)) {
            $customers->push($appointment->customerProfile);
        }

        if ($appointment->service && ! $services->contains('id', $appointment->service->id)) {
            $services->push($appointment->service);
        }

        foreach ([$appointment->staffProfile, $appointment->preferredStaffProfile] as $historicalStaff) {
            if ($historicalStaff && ! $staffProfiles->contains('id', $historicalStaff->id)) {
                $historicalStaff->loadMissing(['user', 'services']);
                $staffProfiles->push($historicalStaff);
            }
        }

        return [
            'appointment' => $appointment,
            'customers' => $customers->sortBy('display_name')->values(),
            'services' => $services->sortBy('name')->values(),
            'staffProfiles' => $staffProfiles->sortBy('user.name')->values(),
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
