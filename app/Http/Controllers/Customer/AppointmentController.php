<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerAppointmentStoreRequest;
use App\Models\Appointment;
use App\Models\Addon;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Services\AppointmentAvailability;
use App\Services\AppointmentWorkflow;
use App\Services\RfmAddonVoucher;
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
        $customerProfileId = $request->user()->customerProfile?->id ?? 0;

        $summaryRow = Appointment::query()
            ->where('customer_profile_id', $customerProfileId)
            ->selectRaw('SUM(CASE WHEN status = ? AND scheduled_start_at >= ? THEN 1 ELSE 0 END) AS upcoming', [Appointment::STATUS_CONFIRMED, now()])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', [Appointment::STATUS_CANCELLED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [Appointment::STATUS_COMPLETED])
            ->first();
        $summary = ['upcoming' => (int) $summaryRow?->upcoming, 'cancelled' => (int) $summaryRow?->cancelled, 'completed' => (int) $summaryRow?->completed];

        return view('customer.appointments.index', [
            'summary' => $summary,
            'initialMonth' => now()->format('Y-m'),
        ]);
    }

    public function history(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Appointment::STATUSES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $status = $filters['status'] ?? null;
        $dateFrom = isset($filters['date_from']) ? Carbon::createFromFormat('Y-m-d', $filters['date_from'])->startOfDay() : null;
        $dateTo = isset($filters['date_to']) ? Carbon::createFromFormat('Y-m-d', $filters['date_to'])->endOfDay() : null;
        $appointmentDate = 'COALESCE(scheduled_start_at, requested_start_at)';
        $upcomingCondition = "status = '".Appointment::STATUS_CONFIRMED."' AND {$appointmentDate} >= ?";

        $appointments = Appointment::query()
            ->with(['service', 'staffProfile.user', 'preferredStaffProfile.user', 'promotionSuggestion', 'addons', 'feedback'])
            ->where('customer_profile_id', $request->user()->customerProfile?->id ?? 0)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($dateFrom, fn ($query) => $query->whereRaw("{$appointmentDate} >= ?", [$dateFrom]))
            ->when($dateTo, fn ($query) => $query->whereRaw("{$appointmentDate} <= ?", [$dateTo]))
            ->orderByRaw("CASE WHEN {$upcomingCondition} THEN 0 ELSE 1 END", [now()])
            ->orderByRaw("CASE WHEN {$upcomingCondition} THEN {$appointmentDate} END ASC", [now()])
            ->orderByRaw("CASE WHEN {$upcomingCondition} THEN NULL ELSE {$appointmentDate} END DESC", [now()])
            ->orderByDesc('id')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('customer.appointments.history', [
            'appointments' => $appointments,
            'appointmentStatus' => $status,
            'appointmentDateFrom' => $filters['date_from'] ?? null,
            'appointmentDateTo' => $filters['date_to'] ?? null,
        ]);
    }

    public function create(
        Request $request,
        RfmAddonVoucher $addonVouchers,
    ): View {
        $data = $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'requested_start_at' => ['nullable', 'date'],
        ]);
        $customerProfile = $request->user()->customerProfile;

        return view('customer.appointments.create', [
            'services' => Service::query()->with('staffProfiles.user')->where('is_active', true)->orderBy('name')->get(),
            'staffProfiles' => StaffProfile::query()
                ->with(['user', 'services'])
                ->where('is_bookable', true)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->get()
                ->sortBy('user.name'),
            'vouchers' => $customerProfile ? $addonVouchers->availableFor($customerProfile) : collect(),
            'addons' => Addon::query()->where('is_active', true)->orderBy('name')->get(),
            'preselectedServiceId' => isset($data['service_id']) ? (int) $data['service_id'] : null,
            'initialRequestedAt' => filled($data['requested_start_at'] ?? null) ? Carbon::parse($data['requested_start_at'])->format('Y-m-d\TH:i') : null,
        ]);
    }

    public function availability(Request $request, AppointmentAvailability $availability, RfmAddonVoucher $addonVouchers): array
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'preferred_staff_profile_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'promotion_suggestion_id' => ['nullable', 'integer', 'exists:promotion_suggestions,id'],
            'addon_codes' => ['nullable', 'array'],
            'addon_codes.*' => ['required', 'string', 'distinct'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $service = Service::query()
            ->where('is_active', true)
            ->findOrFail($data['service_id']);

        $addonCodes = $data['addon_codes'] ?? [];

        if (! empty($data['promotion_suggestion_id'])) {
            $voucher = $request->user()->customerProfile
                ? $addonVouchers->availableFor($request->user()->customerProfile)->firstWhere('id', (int) $data['promotion_suggestion_id'])
                : null;

            if (! $voucher) {
                throw ValidationException::withMessages([
                    'promotion_suggestion_id' => __('This add-on voucher is no longer available.'),
                ]);
            }

            $addonCodes[] = $voucher->addon_code;
        }

        return $availability->month(
            $service,
            $data['month'],
            isset($data['preferred_staff_profile_id']) ? (int) $data['preferred_staff_profile_id'] : null,
            $addonCodes,
        );
    }

    public function store(CustomerAppointmentStoreRequest $request, AppointmentWorkflow $workflow): RedirectResponse
    {
        $customerProfile = $request->user()->customerProfile;

        abort_unless($customerProfile, 403);

        $data = $request->validated();
        $service = Service::query()->findOrFail($data['service_id']);
        $requestedStart = Carbon::parse($data['requested_start_at']);
        $preferredStaffProfileId = ! empty($data['preferred_staff_profile_id']) ? (int) $data['preferred_staff_profile_id'] : null;

        $workflow->assertBookableStart(
            $requestedStart,
            $service,
            'requested_start_at',
            true,
            $data['addon_codes'] ?? [],
            (int) config('casa.business_hours.customer_booking_lead_time_minutes', 30),
        );

        if ($preferredStaffProfileId) {
            $preferredStaff = StaffProfile::query()->with('user')->findOrFail($preferredStaffProfileId);

            if (! $workflow->isStaffEligibleForService($preferredStaff, $service)) {
                return back()->withInput()->withErrors(['preferred_staff_profile_id' => __('Preferred staff must be eligible for the selected service.')]);
            }

        }

        $appointment = $workflow->autoBook([
            'customer_profile_id' => $customerProfile->id,
            'promotion_suggestion_id' => ! empty($data['promotion_suggestion_id']) ? (int) $data['promotion_suggestion_id'] : null,
            'addon_codes' => $data['addon_codes'] ?? [],
            'customer_notes' => filled($data['customer_notes'] ?? null) ? trim((string) $data['customer_notes']) : null,
            'created_by' => $request->user()->id,
        ], $service, $requestedStart, $preferredStaffProfileId, $request->user()->id);

        return redirect()
            ->route('customer.appointments.history')
            ->with('status', 'appointment-booked');
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $this->authorizeOwnAppointment($request, $appointment);

        $appointment->load(['service', 'staffProfile.user', 'preferredStaffProfile.user', 'promotionSuggestion', 'addons', 'feedback', 'transactions']);

        return view('customer.appointments.show', [
            'appointment' => $appointment,
        ]);
    }

    public function cancel(Request $request, Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        $this->authorizeOwnAppointment($request, $appointment);

        if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
            return back()->withErrors(['status' => __('Only an upcoming confirmed appointment can be cancelled online.')]);
        }

        if (! $appointment->scheduled_start_at || ! $appointment->scheduled_start_at->isFuture()) {
            return back()->withErrors(['status' => __('This appointment can no longer be cancelled online because its start time has passed.')]);
        }

        $workflow->changeStatus($appointment, Appointment::STATUS_CANCELLED, $request->user()->id, __('Cancelled by customer'));

        return redirect()
            ->route('customer.appointments.history')
            ->with('status', 'appointment-cancelled');
    }

    private function authorizeOwnAppointment(Request $request, Appointment $appointment): void
    {
        abort_unless((int) $appointment->customer_profile_id === (int) ($request->user()->customerProfile?->id ?? 0), 403);
    }
}
