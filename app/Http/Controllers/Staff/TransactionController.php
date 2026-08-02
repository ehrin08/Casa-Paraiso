<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Models\Appointment;
use App\Models\Transaction;
use App\Services\TransactionNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransactionController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $staffProfile = $request->user()->staffProfile;
        $status = (string) $request->query('payment_status');
        $search = trim((string) $request->query('q'));
        $sorts = [
            'number' => 'transactions.transaction_number',
            'customer' => 'transaction_customers.name',
            'service' => 'transaction_services.name',
            'amount' => 'transactions.amount',
            'status' => 'transactions.payment_status',
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
            ->where(function ($query) use ($request, $staffProfile): void {
                $query->where('transactions.recorded_by', $request->user()->id)
                    ->orWhereHas('appointment', fn ($appointmentQuery) => $appointmentQuery->where('staff_profile_id', $staffProfile?->id ?? 0));
            })
            ->when(in_array($status, Transaction::PAYMENT_STATUSES, true), fn ($query) => $query->where('transactions.payment_status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('transactions.transaction_number', 'like', "%{$search}%")
                    ->orWhere('transaction_customers.name', 'like', "%{$search}%")
                    ->orWhere('transaction_services.name', 'like', "%{$search}%");
            }))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('transactions.created_at')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('staff.transactions.index', [
            'transactions' => $transactions,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'transaction' => new Transaction([
                'payment_status' => Transaction::PAYMENT_PAID,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now(),
            ]),
            'appointments' => $this->eligibleAppointments($staffProfile?->id ?? 0),
        ]);
    }

    public function create(Request $request): View
    {
        $staffProfile = $request->user()->staffProfile;
        $appointments = $this->eligibleAppointments($staffProfile?->id ?? 0);
        $transaction = new Transaction([
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => now(),
        ]);

        if ($request->integer('appointment_id')) {
            $appointment = $appointments->firstWhere('id', $request->integer('appointment_id'));

            if ($appointment) {
                $transaction->appointment_id = $appointment->id;
                $transaction->customer_profile_id = $appointment->customer_profile_id;
                $transaction->service_id = $appointment->service_id;
                $appointment->loadMissing('addons');
                $transaction->amount = $appointment->expectedAmount();
            }
        }

        return view('staff.transactions.create', [
            'transaction' => $transaction,
            'appointments' => $appointments,
        ]);
    }

    public function store(TransactionRequest $request, TransactionNumber $numbers): RedirectResponse
    {
        $transaction = $this->persistStaffTransaction(new Transaction, $request, $numbers);

        return redirect()
            ->route('staff.transactions.show', $transaction)
            ->with('status', 'transaction-created');
    }

    public function show(Request $request, Transaction $transaction): View
    {
        $this->authorizeTransaction($request, $transaction);

        $transaction->load(['customerProfile.user', 'service', 'appointment', 'recorder']);

        return view('staff.transactions.show', [
            'transaction' => $transaction,
        ]);
    }

    public function edit(Request $request, Transaction $transaction): View
    {
        $this->authorizeTransaction($request, $transaction);

        return view('staff.transactions.edit', [
            'transaction' => $transaction,
            'appointments' => $this->eligibleAppointments($request->user()->staffProfile?->id ?? 0),
        ]);
    }

    public function update(TransactionRequest $request, Transaction $transaction, TransactionNumber $numbers): RedirectResponse
    {
        $this->authorizeTransaction($request, $transaction);
        $this->persistStaffTransaction($transaction, $request, $numbers);

        return redirect()
            ->route('staff.transactions.show', $transaction)
            ->with('status', 'transaction-updated');
    }

    private function persistStaffTransaction(Transaction $transaction, TransactionRequest $request, TransactionNumber $numbers): Transaction
    {
        $data = $request->validated();

        if (empty($data['appointment_id'])) {
            throw ValidationException::withMessages(['appointment_id' => __('Staff transactions must be linked to an assigned appointment.')]);
        }

        $appointment = $this->eligibleAppointments($request->user()->staffProfile?->id ?? 0)
            ->firstWhere('id', (int) $data['appointment_id']);

        if (! $appointment) {
            throw ValidationException::withMessages(['appointment_id' => __('Select one of your confirmed or completed appointments.')]);
        }

        $paymentReceived = in_array($data['payment_status'], Transaction::PAYMENT_RECEIVED_STATUSES, true);
        $attributes = [
            'customer_profile_id' => $appointment->customer_profile_id,
            'appointment_id' => $appointment->id,
            'service_id' => $appointment->service_id,
            'amount' => $data['amount'],
            'payment_status' => $data['payment_status'],
            'payment_method' => $paymentReceived ? $data['payment_method'] : null,
            'paid_at' => $paymentReceived ? Carbon::parse($data['paid_at']) : null,
            'recorded_by' => $transaction->recorded_by ?: $request->user()->id,
            'notes' => $data['notes'] ?? null,
        ];

        if (! $transaction->exists) {
            return $numbers->create($attributes);
        }

        $transaction->update($attributes);

        return $transaction;
    }

    private function authorizeTransaction(Request $request, Transaction $transaction): void
    {
        $staffProfileId = $request->user()->staffProfile?->id ?? 0;

        $allowed = (int) $transaction->recorded_by === (int) $request->user()->id
            || $transaction->appointment?->staff_profile_id === $staffProfileId;

        abort_unless($allowed, 403);
    }

    private function eligibleAppointments(int $staffProfileId)
    {
        return Appointment::query()
            ->with(['customerProfile.user', 'service', 'addons'])
            ->where('staff_profile_id', $staffProfileId)
            ->whereIn('status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_COMPLETED])
            ->latest('scheduled_start_at')
            ->get();
    }
}
