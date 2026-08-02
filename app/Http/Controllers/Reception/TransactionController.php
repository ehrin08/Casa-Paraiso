<?php

namespace App\Http\Controllers\Reception;

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
    public function index(Request $request): View
    {
        $status = (string) $request->query('payment_status');
        $search = trim((string) $request->query('q'));
        $transactions = Transaction::query()->with(['customerProfile.user', 'service', 'appointment', 'recorder'])
            ->when(in_array($status, Transaction::PAYMENT_STATUSES, true), fn ($query) => $query->where('payment_status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('transaction_number', 'like', "%{$search}%")->orWhereHas('customerProfile.user', fn ($user) => $user->where('name', 'like', "%{$search}%"))->orWhereHas('service', fn ($service) => $service->where('name', 'like', "%{$search}%"))))
            ->latest()->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();

        return view('reception.transactions.index', [
            'transactions' => $transactions,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('reception.transactions.form', ['transaction' => new Transaction(['payment_status' => Transaction::PAYMENT_PAID, 'payment_method' => ApplicationSetting::current()->default_payment_method, 'paid_at' => now()]), ...$this->selectors()]);
    }

    public function store(TransactionRequest $request, TransactionWorkflow $workflow): RedirectResponse
    {
        $transaction = $workflow->persist(new Transaction, $request->validated(), $request->user()->id);

        return redirect()->route('reception.transactions.show', $transaction)->with('status', 'transaction-created');
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load(['customerProfile.user', 'service', 'appointment', 'recorder']);

        return view('reception.transactions.show', ['transaction' => $transaction]);
    }

    public function edit(Transaction $transaction): View
    {
        return view('reception.transactions.form', ['transaction' => $transaction, ...$this->selectors()]);
    }

    public function update(TransactionRequest $request, Transaction $transaction, TransactionWorkflow $workflow): RedirectResponse
    {
        $workflow->persist($transaction, $request->validated(), $request->user()->id);

        return redirect()->route('reception.transactions.show', $transaction)->with('status', 'transaction-updated');
    }

    private function selectors(): array
    {
        return [
            'customers' => CustomerProfile::query()->with('user')->get()->sortBy('user.name')->values(),
            'services' => Service::query()->orderBy('name')->get(),
            'appointments' => Appointment::query()->with(['customerProfile.user', 'service', 'addons'])->whereIn('status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_COMPLETED])->latest('scheduled_start_at')->get(),
        ];
    }
}
