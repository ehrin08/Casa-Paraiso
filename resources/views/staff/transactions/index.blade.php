<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Therapist module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Transactions') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Review transactions linked to your assigned appointments.') }}</p>
        </div>

    </x-slot>

    <x-app-card>
        <x-list-toolbar eyebrow="{{ __('Payments') }}" title="{{ __('Recorded transactions') }}" :count="$transactions->total()" :reset-url="route('staff.transactions.index')" :active-filters="collect(request()->only(['q', 'payment_status']))->filter(fn ($value) => filled($value))->count()" :collapsible="true">
            <form method="GET" action="{{ route('staff.transactions.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(12rem,1fr)_auto_auto] lg:min-w-[42rem]">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search transaction, customer, service') }}" aria-label="{{ __('Search transactions') }}">
                <select name="payment_status" class="casa-input">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Models\Transaction::PAYMENT_STATUSES as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
            </form>
        </x-list-toolbar>

        <div class="mt-5">
        @if ($transactions->isEmpty())
            <x-empty-state title="{{ __('No transactions yet') }}" description="{{ __('Payments you record or payments linked to your appointments will appear here.') }}" />
        @else
            <x-table-shell>
                <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                    <tr>
                        <x-sortable-th sort="number">{{ __('No.') }}</x-sortable-th>
                        <x-sortable-th sort="customer">{{ __('Customer') }}</x-sortable-th>
                        <x-sortable-th sort="service">{{ __('Service') }}</x-sortable-th>
                        <x-sortable-th sort="amount">{{ __('Amount') }}</x-sortable-th>
                        <x-sortable-th sort="status">{{ __('Status') }}</x-sortable-th>
                        <th class="px-4 py-3">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-casa-border text-sm">
                    @foreach ($transactions as $transaction)
                        <tr class="casa-table-row">
                            <td class="px-4 py-4 font-semibold text-casa-text">{{ $transaction->transaction_number }}</td>
                            <td class="px-4 py-4 text-casa-muted">{{ $transaction->customerProfile?->user?->name }}</td>
                            <td class="px-4 py-4 text-casa-muted">{{ $transaction->service?->name }}</td>
                            <td class="px-4 py-4 font-semibold text-casa-text">PHP {{ number_format((float) $transaction->amount, 2) }}</td>
                            <td class="px-4 py-4"><x-status-badge>{{ ucfirst($transaction->payment_status) }}</x-status-badge></td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-3">
                                    <a href="{{ route('staff.transactions.show', $transaction) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table-shell>
            <div class="mt-5">{{ $transactions->links() }}</div>
        @endif
        </div>
    </x-app-card>

</x-app-layout>
