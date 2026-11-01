<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommissionPayoutRequest;
use App\Models\StaffProfile;
use App\Models\TherapistCommission;
use App\Services\TabularExporter;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommissionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->commissionQuery($filters);
        $totals = $this->totals(clone $query);
        $commissions = $query
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->appends(array_filter($filters, fn ($value) => filled($value) && $value !== 0));
        $selectedId = filter_var($request->query('selected'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $selectedCommission = $commissions->getCollection()->firstWhere('id', $selectedId ?: null)
            ?? $commissions->getCollection()->first();
        $selectedCommission?->loadMissing(['adjustedCommission', 'adjustments']);

        return view('admin.commissions.index', [
            'commissions' => $commissions,
            'staffProfiles' => StaffProfile::withTrashed()->with('user')->get()->sortBy('user.name')->values(),
            'staffProfileId' => $filters['staff_profile_id'],
            'status' => $filters['status'],
            'dateFrom' => $filters['date_from'],
            'dateTo' => $filters['date_to'],
            'filters' => array_filter($filters, fn ($value) => filled($value) && $value !== 0),
            'selectedCommission' => $selectedCommission,
            'totals' => $totals,
        ]);
    }

    public function export(Request $request, TabularExporter $exporter): Response|StreamedResponse
    {
        $format = $exporter->format($request);
        $filters = $this->filters($request);
        $therapist = $filters['staff_profile_id'] ? StaffProfile::withTrashed()->with('user')->find($filters['staff_profile_id'])?->user?->name : null;
        $context = array_filter([
            $therapist ? __('Therapist: :name', ['name' => $therapist]) : __('All therapists'),
            $filters['status'] !== '' ? __('Status: :status', ['status' => $filters['status']]) : null,
            $filters['date_from'] !== '' || $filters['date_to'] !== ''
                ? __('Earned: :from – :to', ['from' => $filters['date_from'] ?: __('Beginning'), 'to' => $filters['date_to'] ?: __('Present')])
                : __('All earned dates'),
        ]);

        return $exporter->download(new TabularExport(
            filename: 'casa-paraiso-commissions',
            title: __('Therapist commissions'),
            columns: [
                'ID' => TabularExport::NUMBER,
                'Therapist' => TabularExport::TEXT,
                'Email' => TabularExport::TEXT,
                'Earned date' => TabularExport::DATETIME,
                'Service' => TabularExport::TEXT,
                'Appointment' => TabularExport::TEXT,
                'Transaction' => TabularExport::TEXT,
                'Type' => TabularExport::TEXT,
                'Basis amount' => TabularExport::MONEY,
                'Rate (%)' => TabularExport::NUMBER,
                'Commission' => TabularExport::MONEY,
                'Status' => TabularExport::TEXT,
                'Payout date' => TabularExport::DATE,
                'Recorded by' => TabularExport::TEXT,
                'Notes' => TabularExport::TEXT,
                'Adjusts commission ID' => TabularExport::NUMBER,
            ],
            query: $this->commissionQuery($filters),
            row: fn (TherapistCommission $commission) => [
                $commission->id,
                $commission->staffProfile?->user?->name,
                $commission->staffProfile?->user?->email,
                $commission->earned_at,
                $commission->appointment?->service?->name,
                $commission->appointment?->appointment_number,
                $commission->transaction?->transaction_number,
                $commission->commission_type,
                $commission->basis_amount,
                number_format((float) $commission->commission_rate * 100, 2, '.', ''),
                $commission->commission_amount,
                $commission->status,
                $commission->paid_at,
                $commission->paidBy?->name,
                $commission->notes,
                $commission->adjusts_commission_id,
            ],
            context: [implode(' · ', $context)],
        ), $format);
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

        if ($request->validated('return_to') === 'index') {
            $filters = $request->validated('return_filters') ?? [];
            $page = (int) ($filters['page'] ?? 1);
            unset($filters['page']);
            $lastPage = max(1, (int) ceil($this->commissionQuery($filters)->count() / (int) config('casa.pagination.per_page', 15)));

            return redirect()->route('admin.commissions.index', array_merge($filters, [
                'page' => min($page, $lastPage),
                'selected' => $commission->id,
            ]))->with('status', 'commission-paid');
        }

        return redirect()->route('admin.commissions.show', $commission)->with('status', 'commission-paid');
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'staff_profile_id' => ['nullable', 'integer', Rule::exists('staff_profiles', 'id')],
            'status' => ['nullable', Rule::in(TherapistCommission::STATUSES)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', Rule::when($request->filled('date_from'), ['after_or_equal:date_from'])],
        ]);

        return [
            'staff_profile_id' => (int) ($validated['staff_profile_id'] ?? 0),
            'status' => (string) ($validated['status'] ?? ''),
            'date_from' => (string) ($validated['date_from'] ?? ''),
            'date_to' => (string) ($validated['date_to'] ?? ''),
        ];
    }

    private function commissionQuery(array $filters): Builder
    {
        return TherapistCommission::query()
            ->with(['staffProfile.user', 'appointment.service', 'transaction', 'paidBy'])
            ->when($filters['staff_profile_id'] ?? 0, fn ($query, $id) => $query->where('staff_profile_id', $id))
            ->when($filters['status'] ?? '', fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? '', fn ($query, $date) => $query->whereDate('earned_at', '>=', $date))
            ->when($filters['date_to'] ?? '', fn ($query, $date) => $query->whereDate('earned_at', '<=', $date))
            ->orderByDesc('earned_at')
            ->orderByDesc('id');
    }

    private function totals(Builder $query): array
    {
        $summary = $query->reorder()->toBase()
            ->selectRaw('SUM(CASE WHEN status = ? THEN commission_amount ELSE 0 END) AS pending', [TherapistCommission::STATUS_PENDING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN commission_amount ELSE 0 END) AS paid', [TherapistCommission::STATUS_PAID])
            ->selectRaw('SUM(commission_amount) AS net')
            ->first();

        return ['pending' => $summary?->pending ?? 0, 'paid' => $summary?->paid ?? 0, 'net' => $summary?->net ?? 0];
    }
}
