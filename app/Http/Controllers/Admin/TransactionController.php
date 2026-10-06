<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\TransactionWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    use HandlesIndexSorting;

    private const SORTS = [
        'number' => 'transactions.transaction_number',
        'customer' => 'customer_display_name',
        'service' => 'transaction_services.name',
        'amount' => 'transactions.amount',
        'status' => 'transactions.payment_status',
        'paid_at' => 'transactions.paid_at',
        'created' => 'transactions.created_at',
        'visit_date' => 'transaction_appointments.scheduled_start_at',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->transactionQuery($filters);
        $summary = (clone $query)->reorder()->toBase()
            ->select([])
            ->selectRaw('SUM(CASE WHEN transactions.payment_status = ? THEN transactions.amount ELSE 0 END) AS paid', [Transaction::PAYMENT_PAID])
            ->selectRaw('SUM(CASE WHEN transactions.payment_status = ? THEN transactions.amount ELSE 0 END) AS unpaid', [Transaction::PAYMENT_UNPAID])
            ->first();
        $transactions = $query
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.transactions.index', [
            'transactions' => $transactions,
            'status' => $filters['payment_status'],
            'search' => $filters['q'],
            'visitMonth' => $filters['visit_month'],
            'sort' => $filters['sort'],
            'direction' => $filters['direction'],
            'exportFilters' => array_filter($filters, fn ($value) => $value !== ''),
            'summary' => ['paid' => $summary?->paid ?? 0, 'unpaid' => $summary?->unpaid ?? 0],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $records = $this->transactionQuery($this->filters($request))->lazy(500);
        $filename = 'casa-paraiso-payments-'.now('Asia/Manila')->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($records): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['ID', 'Customer', 'Email', 'Visit date', 'Amount', 'Payment method', 'Status']);

            foreach ($records as $transaction) {
                $row = [
                    $transaction->transaction_number,
                    $transaction->customerProfile?->display_name,
                    $transaction->customerProfile?->display_email,
                    $transaction->appointment?->scheduled_start_at?->timezone('Asia/Manila')->format('Y-m-d H:i:s'),
                    $transaction->amount,
                    $transaction->payment_method,
                    $transaction->payment_status,
                ];

                fputcsv($handle, array_map($this->csvSafeValue(...), $row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array{q: string, payment_status: string, visit_month: string, sort: string, direction: string} */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'payment_status' => ['nullable', Rule::in(Transaction::PAYMENT_STATUSES)],
            'visit_month' => ['nullable', 'date_format:Y-m'],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        return [
            'q' => trim($validated['q'] ?? ''),
            'payment_status' => $validated['payment_status'] ?? '',
            'visit_month' => $validated['visit_month'] ?? '',
            'sort' => $this->indexSort($request, self::SORTS, 'created'),
            'direction' => $this->indexDirection($request, 'desc'),
        ];
    }

    /** @param array{q: string, payment_status: string, visit_month: string, sort: string, direction: string} $filters */
    private function transactionQuery(array $filters): Builder
    {
        $monthStart = $filters['visit_month'] !== ''
            ? CarbonImmutable::createFromFormat('!Y-m', $filters['visit_month'], 'Asia/Manila')
            : null;

        return Transaction::query()
            ->with(['customerProfile.user', 'appointment'])
            ->leftJoin('customer_profiles as transaction_customer_profiles', 'transaction_customer_profiles.id', '=', 'transactions.customer_profile_id')
            ->leftJoin('users as transaction_customers', 'transaction_customers.id', '=', 'transaction_customer_profiles.user_id')
            ->leftJoin('services as transaction_services', 'transaction_services.id', '=', 'transactions.service_id')
            ->leftJoin('appointments as transaction_appointments', 'transaction_appointments.id', '=', 'transactions.appointment_id')
            ->select('transactions.*')
            ->selectRaw(CustomerProfile::displayNameSql('transaction_customers', 'transaction_customer_profiles').' as customer_display_name')
            ->when($filters['payment_status'] !== '', fn ($query) => $query->where('transactions.payment_status', $filters['payment_status']))
            ->when($monthStart, fn ($query) => $query
                ->where('transaction_appointments.scheduled_start_at', '>=', $monthStart)
                ->where('transaction_appointments.scheduled_start_at', '<', $monthStart->addMonth()))
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $search = $filters['q'];
                $query->where(function ($query) use ($search): void {
                    $query->where('transactions.transaction_number', 'like', "%{$search}%")
                        ->orWhereHas('customerProfile', fn ($customer) => $customer->search($search))
                        ->orWhere('transaction_customers.email', 'like', "%{$search}%")
                        ->orWhere('transaction_services.name', 'like', "%{$search}%");
                });
            })
            ->orderBy(self::SORTS[$filters['sort']], $filters['direction'])
            ->orderByDesc('transactions.created_at')
            ->orderByDesc('transactions.id');
    }

    private function csvSafeValue(?string $value): ?string
    {
        return $value !== null && preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    public function create(Request $request): View
    {
        $transaction = new Transaction([
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => ApplicationSetting::current()->default_payment_method,
            'paid_at' => now(),
        ]);

        if ($request->integer('appointment_id')) {
            $appointment = Appointment::query()->with(['service', 'addons', 'customerProfile.user'])->find($request->integer('appointment_id'));

            if ($appointment) {
                $transaction->appointment_id = $appointment->id;
                $transaction->customer_profile_id = $appointment->customer_profile_id;
                $transaction->service_id = $appointment->service_id;
                $transaction->amount = $appointment->expectedAmount();
            }
        }

        return view('admin.transactions.create', $this->formData($transaction));
    }

    public function store(TransactionRequest $request, TransactionWorkflow $workflow): RedirectResponse
    {
        $transaction = $workflow->persist(new Transaction, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.transactions.show', $transaction)
            ->with('status', 'transaction-created');
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load(['customerProfile.user', 'service', 'appointment', 'recorder']);

        return view('admin.transactions.show', [
            'transaction' => $transaction,
        ]);
    }

    public function edit(Transaction $transaction): View
    {
        return view('admin.transactions.edit', $this->formData($transaction));
    }

    public function update(TransactionRequest $request, Transaction $transaction, TransactionWorkflow $workflow): RedirectResponse
    {
        $workflow->persist($transaction, $request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.transactions.show', $transaction)
            ->with('status', 'transaction-updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Transaction $transaction): array
    {
        return [
            'transaction' => $transaction,
            'customers' => CustomerProfile::query()->with('user')->get()->sortBy('display_name'),
            'services' => Service::query()->orderBy('name')->get(),
            'appointments' => Appointment::query()
                ->with(['customerProfile.user', 'service', 'addons'])
                ->whereIn('status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_COMPLETED])
                ->latest('scheduled_start_at')
                ->get(),
        ];
    }
}
