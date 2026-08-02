<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use HandlesIndexSorting;

    public const TYPES = ['appointments', 'transactions', 'customers', 'promotions', 'feedback'];

    public function index(Request $request)
    {
        $this->validateFilters($request);

        $type = $this->type($request);
        $records = $this->query($type, $request)
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.reports.index', [
            'type' => $type,
            'types' => self::TYPES,
            'records' => $records,
            'summary' => $this->summary(),
            'filters' => $request->only(['q', 'date_from', 'date_to', 'status', 'payment_status', 'sentiment_label', 'sort', 'direction']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->validateFilters($request);

        $type = $this->type($request);
        $records = $this->query($type, $request)->lazy(500);
        $filename = 'casa-paraiso-'.$type.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($type, $records): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $this->csvHeader($type));

            foreach ($records as $record) {
                fputcsv($handle, $this->csvRow($type, $record));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function type(Request $request): string
    {
        $type = (string) $request->query('type', 'appointments');

        return in_array($type, self::TYPES, true) ? $type : 'appointments';
    }

    protected function query(string $type, Request $request): Builder
    {
        return match ($type) {
            'transactions' => $this->transactionQuery($request),
            'customers' => $this->customerQuery($request),
            'promotions' => $this->promotionQuery($request),
            'feedback' => $this->feedbackQuery($request),
            default => $this->appointmentQuery($request),
        };
    }

    private function appointmentQuery(Request $request): Builder
    {
        $sorts = [
            'number' => 'appointments.appointment_number',
            'customer' => 'report_appointment_customers.name',
            'service' => 'report_appointment_services.name',
            'schedule' => 'report_start_at',
            'status' => 'appointments.status',
        ];
        $sort = $this->indexSort($request, $sorts, 'schedule');
        $direction = $this->indexDirection($request, 'desc');
        $search = trim((string) $request->query('q'));

        return Appointment::query()
            ->with(['customerProfile.user', 'service', 'staffProfile.user'])
            ->leftJoin('customer_profiles as report_appointment_customer_profiles', 'report_appointment_customer_profiles.id', '=', 'appointments.customer_profile_id')
            ->leftJoin('users as report_appointment_customers', 'report_appointment_customers.id', '=', 'report_appointment_customer_profiles.user_id')
            ->leftJoin('services as report_appointment_services', 'report_appointment_services.id', '=', 'appointments.service_id')
            ->select('appointments.*')
            ->selectRaw('COALESCE(appointments.scheduled_start_at, appointments.requested_start_at) as report_start_at')
            ->when(
                in_array((string) $request->query('status'), Appointment::STATUSES, true),
                fn ($query) => $query->where('appointments.status', $request->query('status')),
            )
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('appointments.appointment_number', 'like', "%{$search}%")
                    ->orWhere('report_appointment_customers.name', 'like', "%{$search}%")
                    ->orWhere('report_appointment_services.name', 'like', "%{$search}%");
            }))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate(DB::raw('COALESCE(appointments.scheduled_start_at, appointments.requested_start_at)'), '>=', Carbon::parse($request->query('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate(DB::raw('COALESCE(appointments.scheduled_start_at, appointments.requested_start_at)'), '<=', Carbon::parse($request->query('date_to'))))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('report_start_at');
    }

    private function transactionQuery(Request $request): Builder
    {
        $sorts = [
            'number' => 'transactions.transaction_number',
            'customer' => 'report_transaction_customers.name',
            'service' => 'report_transaction_services.name',
            'amount' => 'transactions.amount',
            'status' => 'transactions.payment_status',
            'paid_at' => 'transactions.paid_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'paid_at');
        $direction = $this->indexDirection($request, 'desc');
        $search = trim((string) $request->query('q'));

        return Transaction::query()
            ->with(['customerProfile.user', 'service', 'appointment'])
            ->leftJoin('customer_profiles as report_transaction_customer_profiles', 'report_transaction_customer_profiles.id', '=', 'transactions.customer_profile_id')
            ->leftJoin('users as report_transaction_customers', 'report_transaction_customers.id', '=', 'report_transaction_customer_profiles.user_id')
            ->leftJoin('services as report_transaction_services', 'report_transaction_services.id', '=', 'transactions.service_id')
            ->select('transactions.*')
            ->when(
                in_array((string) $request->query('payment_status'), Transaction::PAYMENT_STATUSES, true),
                fn ($query) => $query->where('transactions.payment_status', $request->query('payment_status')),
            )
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('transactions.transaction_number', 'like', "%{$search}%")
                    ->orWhere('report_transaction_customers.name', 'like', "%{$search}%")
                    ->orWhere('report_transaction_services.name', 'like', "%{$search}%");
            }))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('transactions.paid_at', '>=', Carbon::parse($request->query('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('transactions.paid_at', '<=', Carbon::parse($request->query('date_to'))))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('transactions.paid_at')
            ->orderByDesc('transactions.created_at');
    }

    private function customerQuery(Request $request): Builder
    {
        $sorts = [
            'name' => 'users.name',
            'code' => 'customer_profiles.customer_code',
            'joined' => 'customer_profiles.created_at',
            'status' => 'users.is_active',
            'appointments' => 'appointments_count',
            'transactions' => 'transactions_count',
            'feedback' => 'feedback_count',
            'promotions' => 'promotion_suggestions_count',
        ];
        $sort = $this->indexSort($request, $sorts, 'name');
        $direction = $this->indexDirection($request);
        $search = trim((string) $request->query('q'));

        return CustomerProfile::query()
            ->select('customer_profiles.*')
            ->with('user')
            ->withCount(['appointments', 'transactions', 'feedback', 'promotionSuggestions'])
            ->join('users', 'users.id', '=', 'customer_profiles.user_id')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('customer_profiles.customer_code', 'like', "%{$search}%");
            }))
            ->when(
                in_array((string) $request->query('status'), ['active', 'inactive'], true),
                fn ($query) => $query->where('users.is_active', $request->query('status') === 'active'),
            )
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('customer_profiles.created_at', '>=', Carbon::parse($request->query('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('customer_profiles.created_at', '<=', Carbon::parse($request->query('date_to'))))
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('users.name');
    }

    private function promotionQuery(Request $request): Builder
    {
        $sorts = [
            'customer' => 'report_promotion_customers.name',
            'segment' => 'report_rfm_segments.name',
            'monetary' => 'promotion_suggestions.monetary_total',
            'status' => 'promotion_suggestions.status',
            'created' => 'promotion_suggestions.created_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'created');
        $direction = $this->indexDirection($request, 'desc');
        $search = trim((string) $request->query('q'));

        $status = (string) $request->query('status');
        $query = PromotionSuggestion::query()
            ->with(['customerProfile.user', 'rfmSegment', 'appointments:id,promotion_suggestion_id,status'])
            ->leftJoin('customer_profiles as report_promotion_customer_profiles', 'report_promotion_customer_profiles.id', '=', 'promotion_suggestions.customer_profile_id')
            ->leftJoin('users as report_promotion_customers', 'report_promotion_customers.id', '=', 'report_promotion_customer_profiles.user_id')
            ->leftJoin('rfm_segments as report_rfm_segments', 'report_rfm_segments.id', '=', 'promotion_suggestions.rfm_segment_id')
            ->select('promotion_suggestions.*')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('report_promotion_customers.name', 'like', "%{$search}%")
                    ->orWhere('report_rfm_segments.name', 'like', "%{$search}%")
                    ->orWhere('promotion_suggestions.suggested_offer', 'like', "%{$search}%");
            }))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('promotion_suggestions.created_at', '>=', Carbon::parse($request->query('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('promotion_suggestions.created_at', '<=', Carbon::parse($request->query('date_to'))))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('promotion_suggestions.created_at');

        return match ($status) {
            'available' => $query->where('promotion_suggestions.status', PromotionSuggestion::STATUS_SUGGESTED)
                ->where(fn ($expiry) => $expiry->whereNull('promotion_suggestions.expires_at')->orWhere('promotion_suggestions.expires_at', '>', now())),
            'expired' => $query->where('promotion_suggestions.status', PromotionSuggestion::STATUS_SUGGESTED)
                ->whereNotNull('promotion_suggestions.expires_at')
                ->where('promotion_suggestions.expires_at', '<=', now()),
            'reserved' => $query->where('promotion_suggestions.status', PromotionSuggestion::STATUS_APPLIED)
                ->whereHas('appointments', fn ($appointments) => $appointments->where('status', Appointment::STATUS_CONFIRMED)),
            'used' => $query->where('promotion_suggestions.status', PromotionSuggestion::STATUS_APPLIED)
                ->whereDoesntHave('appointments', fn ($appointments) => $appointments->where('status', Appointment::STATUS_CONFIRMED)),
            'dismissed' => $query->where('promotion_suggestions.status', PromotionSuggestion::STATUS_DISMISSED),
            default => $query,
        };
    }

    private function feedbackQuery(Request $request): Builder
    {
        $sorts = [
            'customer' => 'report_feedback_customers.name',
            'service' => 'report_feedback_services.name',
            'rating' => 'feedback.rating',
            'sentiment' => 'feedback.sentiment_label',
            'submitted' => 'feedback.submitted_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'submitted');
        $direction = $this->indexDirection($request, 'desc');
        $search = trim((string) $request->query('q'));

        return Feedback::query()
            ->with(['customerProfile.user', 'service'])
            ->leftJoin('customer_profiles as report_feedback_customer_profiles', 'report_feedback_customer_profiles.id', '=', 'feedback.customer_profile_id')
            ->leftJoin('users as report_feedback_customers', 'report_feedback_customers.id', '=', 'report_feedback_customer_profiles.user_id')
            ->leftJoin('services as report_feedback_services', 'report_feedback_services.id', '=', 'feedback.service_id')
            ->select('feedback.*')
            ->when(
                in_array((string) $request->query('sentiment_label'), Feedback::SENTIMENT_LABELS, true),
                fn ($query) => $query->where('feedback.sentiment_label', $request->query('sentiment_label')),
            )
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('report_feedback_customers.name', 'like', "%{$search}%")
                    ->orWhere('report_feedback_services.name', 'like', "%{$search}%")
                    ->orWhere('feedback.comment', 'like', "%{$search}%");
            }))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('feedback.submitted_at', '>=', Carbon::parse($request->query('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('feedback.submitted_at', '<=', Carbon::parse($request->query('date_to'))))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('feedback.submitted_at');
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(): array
    {
        return [
            'appointments' => Appointment::query()->count(),
            'revenue' => Transaction::query()->where('payment_status', Transaction::PAYMENT_PAID)->sum('amount'),
            'customers' => CustomerProfile::query()->count(),
            'feedback' => Feedback::query()->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function csvHeader(string $type): array
    {
        return match ($type) {
            'transactions' => ['Number', 'Customer', 'Service', 'Amount', 'Status', 'Method', 'Paid At'],
            'customers' => ['Code', 'Name', 'Email', 'Phone', 'Joined At', 'Status', 'Appointments', 'Transactions', 'Feedback', 'Rewards'],
            'promotions' => ['Customer', 'Customer Group', 'Days Since Last Paid Visit', 'Paid Visits', 'Paid Total', 'Reward', 'Status', 'Expires At'],
            'feedback' => ['Customer', 'Service', 'Rating', 'Sentiment', 'Submitted At', 'Comment'],
            default => ['Number', 'Customer', 'Service', 'Staff', 'Appointment Date', 'Status'],
        };
    }

    /**
     * @return array<int, string|int|float|null>
     */
    protected function csvRow(string $type, mixed $record): array
    {
        $row = match ($type) {
            'transactions' => [$record->transaction_number, $record->customerProfile?->user?->name, $record->service?->name, $record->amount, $record->payment_status, $record->payment_method, $record->paid_at?->toDateTimeString()],
            'customers' => [$record->customer_code, $record->user?->name, $record->user?->email, $record->user?->phone, $record->created_at?->toDateTimeString(), $record->user?->is_active ? 'active' : 'inactive', $record->appointments_count, $record->transactions_count, $record->feedback_count, $record->promotion_suggestions_count],
            'promotions' => [$record->customerProfile?->user?->name, $record->rfmSegment?->name, $record->recency_days, $record->frequency_count, $record->monetary_total, $record->addonName() ?: $record->suggested_offer, $record->lifecycle(), $record->expires_at?->toDateTimeString()],
            'feedback' => [$record->customerProfile?->user?->name, $record->service?->name, $record->rating, $record->sentiment_label, $record->submitted_at?->toDateTimeString(), $record->comment],
            default => [$record->appointment_number, $record->customerProfile?->user?->name, $record->service?->name, $record->staffProfile?->user?->name, ($record->scheduled_start_at ?? $record->requested_start_at)?->toDateTimeString(), $record->status],
        };

        return array_map($this->csvSafeValue(...), $row);
    }

    private function csvSafeValue(string|int|float|null $value): string|int|float|null
    {
        if (is_string($value) && preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }

    protected function validateFilters(Request $request): void
    {
        $request->validate([
            'type' => ['sometimes', Rule::in(self::TYPES)],
            'q' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in([
                ...Appointment::STATUSES,
                'available',
                'reserved',
                'used',
                'dismissed',
                'expired',
                'active',
                'inactive',
            ])],
            'payment_status' => ['nullable', Rule::in(Transaction::PAYMENT_STATUSES)],
            'sentiment_label' => ['nullable', Rule::in(Feedback::SENTIMENT_LABELS)],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
    }
}
