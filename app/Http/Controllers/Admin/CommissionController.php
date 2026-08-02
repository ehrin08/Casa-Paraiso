<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommissionPayoutRequest;
use App\Models\StaffProfile;
use App\Models\TherapistCommission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'staff_profile_id' => ['nullable', 'integer', Rule::exists('staff_profiles', 'id')],
            'status' => ['nullable', Rule::in(TherapistCommission::STATUSES)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', Rule::when($request->filled('date_from'), ['after_or_equal:date_from'])],
        ]);
        $staffProfileId = (int) ($filters['staff_profile_id'] ?? 0);
        $status = (string) ($filters['status'] ?? '');
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');

        $commissions = TherapistCommission::query()
            ->with(['staffProfile.user', 'appointment.service', 'transaction', 'paidBy'])
            ->when($staffProfileId, fn ($query) => $query->where('staff_profile_id', $staffProfileId))
            ->when(in_array($status, TherapistCommission::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('earned_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('earned_at', '<=', $dateTo))
            ->latest('earned_at')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.commissions.index', [
            'commissions' => $commissions,
            'staffProfiles' => StaffProfile::query()->with('user')->get()->sortBy('user.name')->values(),
            'staffProfileId' => $staffProfileId,
            'status' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'totals' => $this->totals(TherapistCommission::query()),
        ]);
    }

    public function show(TherapistCommission $commission): View
    {
        $commission->load(['staffProfile.user', 'appointment.service', 'transaction', 'adjustedCommission', 'adjustments', 'paidBy']);

        return view('admin.commissions.show', ['commission' => $commission]);
    }

    public function pay(CommissionPayoutRequest $request, TherapistCommission $commission): RedirectResponse
    {
        DB::transaction(function () use ($commission, $request): void {
            $locked = TherapistCommission::query()->lockForUpdate()->findOrFail($commission->id);

            if ($locked->status !== TherapistCommission::STATUS_PENDING) {
                throw ValidationException::withMessages(['status' => __('Paid commissions cannot be changed.')]);
            }

            if ((float) $locked->commission_amount === 0.0) {
                throw ValidationException::withMessages(['status' => __('A zero commission does not require payout recording.')]);
            }

            $locked->update([
                'status' => TherapistCommission::STATUS_PAID,
                'paid_at' => Carbon::parse($request->validated('paid_at'))->endOfDay(),
                'paid_by' => $request->user()->id,
                'notes' => filled($request->validated('notes')) ? trim($request->validated('notes')) : null,
            ]);
        }, 3);

        return redirect()->route('admin.commissions.show', $commission)->with('status', 'commission-paid');
    }

    private function totals($query): array
    {
        $summary = $query
            ->selectRaw('SUM(CASE WHEN status = ? THEN commission_amount ELSE 0 END) AS pending', [TherapistCommission::STATUS_PENDING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN commission_amount ELSE 0 END) AS paid', [TherapistCommission::STATUS_PAID])
            ->selectRaw('SUM(commission_amount) AS net')
            ->first();

        return ['pending' => $summary?->pending ?? 0, 'paid' => $summary?->paid ?? 0, 'net' => $summary?->net ?? 0];
    }
}
