<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportIndexRequest;
use App\Models\Addon;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\Transaction;
use App\Services\ReportDashboard;
use App\Services\ReportDateRange;
use App\Services\TabularExporter;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use HandlesIndexSorting;

    public const TYPES = ['appointments', 'transactions', 'customers', 'promotions', 'feedback'];

    private const TITLES = ['appointments' => 'Appointments', 'transactions' => 'Transactions', 'customers' => 'Customers', 'promotions' => 'Rewards', 'feedback' => 'Feedback'];

    public function __construct(private readonly ReportDashboard $dashboard) {}

    public function index(ReportIndexRequest $request): View
    {
        $range = ReportDateRange::fromFilters($request->validated());
        $type = $this->type($request);
        $editingCustomDates = $range->key === 'custom' && ! $request->filled('date_from') && ! $request->filled('date_to');
        $request->query->add([...$range->query(), 'type' => $type]);
        $records = $this->query($type, $request, $range)
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.reports.index', [
            'type' => $type,
            'types' => self::TYPES,
            'records' => $records,
            'rewardNames' => $type === 'promotions' ? Addon::query()->whereIn('code', $records->pluck('addon_code')->filter())->pluck('name', 'code')->all() : [],
            'dashboard' => $this->dashboard->forRange($range),
            'range' => $range,
            'editingCustomDates' => $editingCustomDates,
            'category' => $request->query('category', 'all'),
            'filterQuery' => [...$request->safe()->except(['page']), ...$range->query(), 'type' => $type, 'category' => $request->query('category', 'all')],
            'filters' => $request->only(['q', 'date_from', 'date_to', 'status', 'payment_status', 'sentiment_label', 'sort', 'direction']),
        ]);
    }

    public function export(ReportIndexRequest $request, TabularExporter $exporter): Response|StreamedResponse
    {
        $format = $exporter->format($request);
        $range = ReportDateRange::fromFilters($request->validated());
        $type = $this->type($request);
        $filters = array_filter([
            filled($request->query('q')) ? __('Search: :q', ['q' => $request->query('q')]) : null,
            filled($request->query('status')) ? __('Status: :status', ['status' => $request->query('status')]) : null,
            filled($request->query('payment_status')) ? __('Payment status: :status', ['status' => $request->query('payment_status')]) : null,
            filled($request->query('sentiment_label')) ? __('Sentiment: :label', ['label' => $request->query('sentiment_label')]) : null,
        ]);

        return $exporter->download(new TabularExport(
            filename: 'casa-paraiso-'.$type,
            title: __(':report report', ['report' => self::TITLES[$type]]),
            columns: $this->exportColumns($type),
            query: $this->query($type, $request, $range),
            row: fn ($record) => $this->exportRow($type, $record),
            context: [$range->label(), ...($filters ? [implode(' · ', $filters)] : [])],
        ), $format);
    }

    protected function type(Request $request): string
    {
        if ($request->query('category', 'all') !== 'all') {
            return (string) $request->query('category');
        }

        $type = (string) $request->query('type', 'appointments');

        return in_array($type, self::TYPES, true) ? $type : 'appointments';
    }

    protected function query(string $type, Request $request, ReportDateRange $range): Builder
    {
        $query = match ($type) {
            'transactions' => $this->transactionQuery($request),
            'customers' => $this->customerQuery($request),
            'promotions' => $this->promotionQuery($request),
            'feedback' => $this->feedbackQuery($request),
            default => $this->appointmentQuery($request),
        };

        $column = match ($type) {
            'transactions' => 'transactions.paid_at',
            'customers' => 'customer_profiles.created_at',
            'promotions' => 'promotion_suggestions.created_at',
            'feedback' => 'feedback.submitted_at',
            default => DB::raw('COALESCE(appointments.scheduled_start_at, appointments.requested_start_at)'),
        };

        return $range->apply($query, $column);
    }

    private function appointmentQuery(Request $request): Builder
    {
        $sorts = [
            'number' => 'appointments.appointment_number',
            'customer' => 'customer_display_name',
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
            ->selectRaw(CustomerProfile::displayNameSql('report_appointment_customers', 'report_appointment_customer_profiles').' as customer_display_name')
            ->selectRaw('COALESCE(appointments.scheduled_start_at, appointments.requested_start_at) as report_start_at')
            ->when(
                in_array((string) $request->query('status'), Appointment::STATUSES, true),
                fn ($query) => $query->where('appointments.status', $request->query('status')),
            )
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('appointments.appointment_number', 'like', "%{$search}%")
                    ->orWhereHas('customerProfile', fn ($customer) => $customer->search($search))
                    ->orWhere('report_appointment_services.name', 'like', "%{$search}%");
            }))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('report_start_at')
            ->orderByDesc('appointments.id');
    }

    private function transactionQuery(Request $request): Builder
    {
        $sorts = [
            'number' => 'transactions.transaction_number',
            'customer' => 'customer_display_name',
            'service' => 'report_transaction_services.name',
            'amount' => 'transactions.amount',
            'status' => 'transactions.payment_status',
            'paid_at' => 'transactions.paid_at',
            'method' => 'transactions.payment_method',
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
            ->selectRaw(CustomerProfile::displayNameSql('report_transaction_customers', 'report_transaction_customer_profiles').' as customer_display_name')
            ->when(
                in_array((string) $request->query('payment_status'), Transaction::PAYMENT_STATUSES, true),
                fn ($query) => $query->where('transactions.payment_status', $request->query('payment_status')),
            )
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('transactions.transaction_number', 'like', "%{$search}%")
                    ->orWhereHas('customerProfile', fn ($customer) => $customer->search($search))
                    ->orWhere('report_transaction_services.name', 'like', "%{$search}%");
            }))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('transactions.paid_at')
            ->orderByDesc('transactions.created_at')
            ->orderByDesc('transactions.id');
    }

    private function customerQuery(Request $request): Builder
    {
        $sorts = [
            'name' => 'customer_display_name',
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
            ->withCount(['appointments', 'transactions', 'feedback' => fn ($query) => $query->identified(), 'promotionSuggestions'])
            ->leftJoin('users', 'users.id', '=', 'customer_profiles.user_id')
            ->selectRaw(CustomerProfile::displayNameSql().' as customer_display_name')
            ->when($search !== '', fn ($query) => $query->search($search))
            ->when(
                in_array((string) $request->query('status'), ['active', 'inactive'], true),
                fn ($query) => $query->where('users.is_active', $request->query('status') === 'active'),
            )
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('customer_display_name')
            ->orderBy('customer_profiles.id');
    }

    private function promotionQuery(Request $request): Builder
    {
        $sorts = [
            'customer' => 'customer_display_name',
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
            ->selectRaw(CustomerProfile::displayNameSql('report_promotion_customers', 'report_promotion_customer_profiles').' as customer_display_name')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->whereHas('customerProfile', fn ($customer) => $customer->search($search))
                    ->orWhere('report_rfm_segments.name', 'like', "%{$search}%")
                    ->orWhere('promotion_suggestions.suggested_offer', 'like', "%{$search}%");
            }))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('promotion_suggestions.created_at')
            ->orderByDesc('promotion_suggestions.id');

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
            'customer' => 'customer_display_name',
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
            ->selectRaw('CASE WHEN feedback.is_anonymous = 1 THEN NULL ELSE '.CustomerProfile::displayNameSql('report_feedback_customers', 'report_feedback_customer_profiles').' END as customer_display_name')
            ->when(
                in_array((string) $request->query('sentiment_label'), Feedback::SENTIMENT_LABELS, true),
                fn ($query) => $query->where('feedback.sentiment_label', $request->query('sentiment_label')),
            )
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where(fn ($query) => $query->identified()->whereHas('customerProfile', fn ($customer) => $customer->search($search)))
                    ->orWhere('report_feedback_services.name', 'like', "%{$search}%")
                    ->orWhere('feedback.comment', 'like', "%{$search}%");
            }))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('feedback.submitted_at')
            ->orderByDesc('feedback.id');
    }

    /**
     * @return array<string, string>
     */
    protected function exportColumns(string $type): array
    {
        $text = TabularExport::TEXT;
        $number = TabularExport::NUMBER;
        $money = TabularExport::MONEY;
        $datetime = TabularExport::DATETIME;

        return match ($type) {
            'transactions' => ['Number' => $text, 'Customer' => $text, 'Service' => $text, 'Amount' => $money, 'Status' => $text, 'Method' => $text, 'Paid At' => $datetime],
            'customers' => ['Code' => $text, 'Name' => $text, 'Email' => $text, 'Phone' => $text, 'Joined At' => $datetime, 'Status' => $text, 'Appointments' => $number, 'Transactions' => $number, 'Feedback' => $number, 'Rewards' => $number],
            'promotions' => ['Customer' => $text, 'Customer Group' => $text, 'Days Since Last Paid Visit' => $number, 'Paid Visits' => $number, 'Paid Total' => $money, 'Reward' => $text, 'Status' => $text, 'Expires At' => $datetime],
            'feedback' => ['Customer' => $text, 'Service' => $text, 'Rating' => $number, 'Sentiment' => $text, 'Submitted At' => $datetime, 'Comment' => $text, 'Anonymous' => $text],
            default => ['Number' => $text, 'Customer' => $text, 'Service' => $text, 'Staff' => $text, 'Appointment Date' => $datetime, 'Status' => $text],
        };
    }

    /**
     * @return array<int, mixed>
     */
    protected function exportRow(string $type, mixed $record): array
    {
        return match ($type) {
            'transactions' => [$record->transaction_number, $record->customerProfile?->display_name, $record->service?->name, $record->amount, $record->payment_status, $record->payment_method, $record->paid_at],
            'customers' => [$record->customer_code, $record->display_name, $record->display_email, $record->display_phone, $record->created_at, $record->is_guest ? 'guest' : ($record->user?->is_active ? 'active' : 'inactive'), $record->appointments_count, $record->transactions_count, $record->feedback_count, $record->promotion_suggestions_count],
            'promotions' => [$record->customerProfile?->display_name, $record->rfmSegment?->name, $record->recency_days, $record->frequency_count, $record->monetary_total, $record->addonName() ?: $record->suggested_offer, $record->lifecycle(), $record->expires_at],
            'feedback' => [$record->displayName(), $record->service?->name, $record->rating, $record->sentiment_label, $record->submitted_at, $record->comment, $record->is_anonymous ? 'yes' : 'no'],
            default => [$record->appointment_number, $record->customerProfile?->display_name, $record->service?->name, $record->staffProfile?->user?->name, $record->scheduled_start_at ?? $record->requested_start_at, $record->status],
        };
    }

    public function insights(Request $request): JsonResponse
    {
        $period = (string) $request->query('period', '30');
        if (! in_array($period, ['7', '30', '90', 'all'], true)) {
            $period = '30';
        }

        [$from, $to] = $this->insightsPeriodDates($period);

        // Revenue over time (daily)
        $revenueQuery = Transaction::query()
            ->where('payment_status', Transaction::PAYMENT_PAID)
            ->whereNotNull('paid_at')
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
            ->selectRaw('DATE(paid_at) as date, SUM(amount) as total')
            ->groupByRaw('DATE(paid_at)')
            ->orderByRaw('DATE(paid_at)')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'total' => (float) $r->total]);

        // Appointments by status
        $appointmentsByStatus = Appointment::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($r) => ['status' => $r->status, 'count' => (int) $r->count]);

        // Feedback sentiment breakdown
        $sentiment = Feedback::query()
            ->when($from, fn ($q) => $q->where('submitted_at', '>=', $from))
            ->selectRaw('sentiment_label as label, COUNT(*) as count')
            ->groupBy('sentiment_label')
            ->orderBy('sentiment_label')
            ->get()
            ->map(fn ($r) => ['label' => $r->label ?? 'unrated', 'count' => (int) $r->count]);

        // Top 7 services by appointment count
        $topServices = Appointment::query()
            ->when($from, fn ($q) => $q->where('appointments.created_at', '>=', $from))
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->selectRaw('services.name, COUNT(appointments.id) as count')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('count')
            ->limit(7)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count]);

        // RFM segment distribution
        $rfmSegments = PromotionSuggestion::query()
            ->when($from, fn ($q) => $q->where('promotion_suggestions.created_at', '>=', $from))
            ->leftJoin('rfm_segments', 'rfm_segments.id', '=', 'promotion_suggestions.rfm_segment_id')
            ->selectRaw('COALESCE(rfm_segments.name, "Unassigned") as name, COUNT(promotion_suggestions.id) as count')
            ->groupBy('rfm_segments.id', 'rfm_segments.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->count]);

        return response()->json([
            'period' => $period,
            'revenue' => $revenueQuery,
            'appointments_status' => $appointmentsByStatus,
            'sentiment' => $sentiment,
            'top_services' => $topServices,
            'rfm_segments' => $rfmSegments,
        ]);
    }

    /**
     * Return the Carbon start timestamp for the insights period, or null for all-time.
     *
     * @return array{0: Carbon|null, 1: Carbon}
     */
    private function insightsPeriodDates(string $period): array
    {
        $to = now();
        $from = match ($period) {
            '7' => $to->copy()->subDays(7)->startOfDay(),
            '90' => $to->copy()->subDays(90)->startOfDay(),
            'all' => null,
            default => $to->copy()->subDays(30)->startOfDay(),
        };

        return [$from, $to];
    }
}
