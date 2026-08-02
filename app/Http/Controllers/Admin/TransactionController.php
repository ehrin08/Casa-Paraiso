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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $status = (string) $request->query('payment_status');
        $search = trim((string) $request->query('q'));
        $sorts = [
            'number' => 'transactions.transaction_number',
            'customer' => 'transaction_customers.name',
            'service' => 'transaction_services.name',
            'amount' => 'transactions.amount',
            'status' => 'transactions.payment_status',
            'paid_at' => 'transactions.paid_at',
            'created' => 'transactions.created_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'created');
        $direction = $this->indexDirection($request, 'desc');

        $transactions = Transaction::query()
            ->with(['customerProfile.user', 'service', 'appointment', 'recorder'])
            ->leftJoin('customer_profiles as transaction_customer_profiles', 'transaction_customer_profiles.id', '=', 'transactions.customer_profile_id')
            ->leftJoin('users as transaction_customers', 'transaction_customers.id', '=', 'transaction_customer_profiles.user_id')
            ->leftJoin('services as transaction_services', 'transaction_services.id', '=', 'transactions.service_id')
            ->select('transactions.*')
            ->when(in_array($status, Transaction::PAYMENT_STATUSES, true), fn ($query) => $query->where('transactions.payment_status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('transactions.transaction_number', 'like', "%{$search}%")
                        ->orWhere('transaction_customers.name', 'like', "%{$search}%")
                        ->orWhere('transaction_services.name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('transactions.created_at')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();
        $summary = Transaction::query()
            ->selectRaw('SUM(CASE WHEN payment_status = ? THEN amount ELSE 0 END) AS paid', [Transaction::PAYMENT_PAID])
            ->selectRaw('SUM(CASE WHEN payment_status IN (?, ?) THEN amount ELSE 0 END) AS unpaid', [Transaction::PAYMENT_UNPAID, Transaction::PAYMENT_PARTIAL])
            ->selectRaw('COUNT(*) AS transaction_count')
            ->first();

        return view('admin.transactions.index', [
            'transactions' => $transactions,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'summary' => ['paid' => $summary?->paid ?? 0, 'unpaid' => $summary?->unpaid ?? 0, 'count' => (int) $summary?->transaction_count],
        ]);
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
            'customers' => CustomerProfile::query()->with('user')->get()->sortBy('user.name'),
            'services' => Service::query()->orderBy('name')->get(),
            'appointments' => Appointment::query()
                ->with(['customerProfile.user', 'service', 'addons'])
                ->whereIn('status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_COMPLETED])
                ->latest('scheduled_start_at')
                ->get(),
        ];
    }
}
