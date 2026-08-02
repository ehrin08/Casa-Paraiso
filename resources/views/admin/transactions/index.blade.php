<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Payments') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Record manual payments, manage transaction status, and jump into revenue exports from one workspace.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.reports.index', ['type' => 'transactions']) }}" class="casa-button-secondary">{{ __('Revenue report') }}</a>
            <a href="{{ route('admin.transactions.create') }}" class="casa-button-primary">{{ __('Record payment') }}</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="casa-metric-grid grid gap-3 sm:gap-4 md:grid-cols-3" data-metric-grid>
            <x-metric-card label="Paid total" value="PHP {{ number_format((float) $summary['paid'], 2) }}" meta="Paid transactions" tone="green" />
            <x-metric-card label="Open balance" value="PHP {{ number_format((float) $summary['unpaid'], 2) }}" meta="Unpaid or partial" tone="gold" />
            <x-metric-card label="Records" :value="$summary['count']" meta="All transactions" tone="brown" />
        </section>

        <x-app-card>
            <x-list-toolbar eyebrow="{{ __('Payments') }}" title="{{ __('Transaction list') }}" :count="$transactions->total()" :reset-url="route('admin.transactions.index')" :active-filters="collect(request()->only(['q', 'payment_status']))->filter(fn ($value) => filled($value))->count()" :collapsible="true">
                <form method="GET" action="{{ route('admin.transactions.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(12rem,1fr)_auto_auto] lg:min-w-[42rem]">
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
                    <x-empty-state title="{{ __('No transactions yet') }}" description="{{ __('Manual payment records will appear here after Receptionist or Admin records a service payment.') }}" />
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
                                    <td class="px-4 py-4 text-casa-muted">{{ $transaction->service?->name ?: __('General service') }}</td>
                                    <td class="px-4 py-4 font-semibold text-casa-text">PHP {{ number_format((float) $transaction->amount, 2) }}</td>
                                    <td class="px-4 py-4"><x-status-badge>{{ ucfirst($transaction->payment_status) }}</x-status-badge></td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-3">
                                            <a href="{{ route('admin.transactions.show', $transaction) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a>
                                            <a href="{{ route('admin.transactions.edit', $transaction) }}" class="font-bold text-casa-muted hover:text-casa-primary">{{ __('Edit') }}</a>
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

        <section class="grid gap-4 md:grid-cols-2">
            <x-app-card>
                <p class="casa-section-label">{{ __('Exports') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Download payment records') }}</h2>
                <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('Use the filtered revenue report when management needs CSV records without database access.') }}</p>
                <a href="{{ route('admin.reports.export', ['type' => 'transactions']) }}" class="mt-5 casa-button-secondary w-full" data-turbo="false">{{ __('Export transactions CSV') }}</a>
            </x-app-card>

            <x-app-card>
                <p class="casa-section-label">{{ __('Manual payment flow') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Record, update, review') }}</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-casa-bg p-4 text-sm font-semibold text-casa-text">{{ __('Link appointment') }}</div>
                    <div class="rounded-2xl bg-casa-bg p-4 text-sm font-semibold text-casa-text">{{ __('Set status') }}</div>
                    <div class="rounded-2xl bg-casa-bg p-4 text-sm font-semibold text-casa-text">{{ __('Export report') }}</div>
                </div>
            </x-app-card>
        </section>
    </div>

</x-app-layout>
