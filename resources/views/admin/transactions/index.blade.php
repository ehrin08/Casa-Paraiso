<x-app-layout>
    <x-slot name="header">
        <div class="payments-heading">
            <h1>{{ __('Payments') }}</h1>
            <p>{{ __('Manage client payments and record transactions.') }}</p>
        </div>

        <div class="payments-header-actions">
            <a href="{{ route('admin.transactions.create') }}" class="casa-button-primary">
                <x-payment-icon name="plus" />
                {{ __('Record payment') }}
            </a>
            <a href="{{ route('admin.transactions.export', $exportFilters) }}" class="casa-button-secondary" data-turbo="false">
                <x-payment-icon name="download" />
                {{ __('Export CSV') }}
            </a>
        </div>
    </x-slot>

    @php
        $activeFilters = collect([$search, $status, $visitMonth])->filter(fn ($value) => filled($value))->count();
        $money = fn ($amount) => '₱'.preg_replace('/\.00$/', '', number_format((float) $amount, 2));
    @endphp

    <div class="payments-workspace" data-payments-workspace>
        <section class="payments-metrics" data-metric-grid aria-label="{{ __('Filtered payment totals') }}">
            @foreach ([
                ['key' => 'paid', 'label' => __('Paid total'), 'meta' => __('Fully paid transactions'), 'icon' => 'coins'],
                ['key' => 'unpaid', 'label' => __('Unpaid total'), 'meta' => __('Unpaid transaction amounts'), 'icon' => 'wallet'],
            ] as $metric)
                <article class="payments-metric" data-metric-card aria-labelledby="payment-metric-{{ $metric['key'] }}">
                    <span class="payments-metric-icon"><x-payment-icon :name="$metric['icon']" /></span>
                    <div class="payments-metric-content">
                        <h2 id="payment-metric-{{ $metric['key'] }}">{{ $metric['label'] }}</h2>
                        <p class="payments-metric-value" data-payment-total="{{ $metric['key'] }}">{{ $money($summary[$metric['key']]) }}</p>
                        <p class="payments-metric-meta" data-metric-meta>{{ $metric['meta'] }}</p>
                    </div>
                </article>
            @endforeach
        </section>

        <x-list-toolbar :active-filters="$activeFilters" :collapsible="true" class="payments-toolbar casa-list-toolbar--filters">
            <form method="GET" action="{{ route('admin.transactions.index') }}" class="payments-filter-form" x-data>
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <div class="payments-search">
                    <label for="payment-search" class="sr-only">{{ __('Search payments by transaction ID, customer, email, or service') }}</label>
                    <input id="payment-search" type="search" name="q" value="{{ $search }}" maxlength="255" class="casa-input" placeholder="{{ __('Search customer, email, transaction ID, or service…') }}">
                    <button type="submit" aria-label="{{ __('Search payments') }}"><x-payment-icon name="search" /></button>
                    <x-input-error :messages="$errors->get('q')" />
                </div>
                <div class="payments-filter-field">
                    <label for="payment-status">{{ __('Status') }}</label>
                    <select id="payment-status" name="payment_status" class="casa-input" x-on:change="$el.form.requestSubmit()">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (\App\Models\Transaction::PAYMENT_STATUSES as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('payment_status')" />
                </div>
                <div class="payments-filter-field">
                    <label for="payment-month">{{ __('Visit month') }}</label>
                    <input id="payment-month" type="month" name="visit_month" value="{{ $visitMonth }}" class="casa-input" aria-describedby="payment-month-help" x-on:change="$el.form.requestSubmit()">
                    <span id="payment-month-help" class="payments-filter-help">{{ $visitMonth === '' ? __('All dates') : __('Asia/Manila visit dates') }}</span>
                    <x-input-error :messages="$errors->get('visit_month')" />
                </div>
                @if ($activeFilters > 0)
                    <a href="{{ route('admin.transactions.index', ['sort' => $sort, 'direction' => $direction]) }}" class="casa-button-secondary payments-clear">{{ __('Clear filters') }}</a>
                @endif
                <noscript><button type="submit" class="casa-button-primary">{{ __('Apply filters') }}</button></noscript>
            </form>
        </x-list-toolbar>
        <noscript><style>.payments-toolbar [data-filter-panel] { display: block; } .payments-toolbar [data-filter-toggle] { display: none; }</style></noscript>

        <section class="payments-transactions" aria-labelledby="payment-transactions-heading">
            <div class="payments-table-heading">
                <div>
                    <h2 id="payment-transactions-heading">{{ __('Transactions') }}</h2>
                    <p>{{ __('Showing :shown of :total transactions', ['shown' => $transactions->count(), 'total' => $transactions->total()]) }}</p>
                </div>
                <a href="{{ route('admin.reports.index', ['type' => 'transactions']) }}" class="payments-report-link">{{ __('Revenue report') }} <span aria-hidden="true">→</span></a>
            </div>

            @if ($transactions->isEmpty())
                <x-empty-state
                    :title="$activeFilters > 0 ? __('No transactions found') : __('No transactions yet')"
                    :description="$activeFilters > 0 ? __('Try another search or clear the filters to find a payment.') : __('Record the first service payment to start your transaction history.')"
                >
                    <x-slot name="action">
                        @if ($activeFilters > 0)
                            <a href="{{ route('admin.transactions.index') }}" class="casa-button-secondary">{{ __('Clear filters') }}</a>
                        @else
                            <a href="{{ route('admin.transactions.create') }}" class="casa-button-primary">{{ __('Record payment') }}</a>
                        @endif
                    </x-slot>
                </x-empty-state>
            @else
                <x-table-shell class="payments-table" label="{{ __('Scrollable payment records') }}">
                    <thead>
                        <tr>
                            @foreach (['number' => __('ID'), 'customer' => __('Customer'), 'visit_date' => __('Visit date'), 'amount' => __('Amount')] as $column => $label)
                                <th scope="col" aria-sort="{{ $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                    <a href="{{ route('admin.transactions.index', array_merge($exportFilters, ['sort' => $column, 'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc'])) }}">
                                        {{ $label }}
                                        @if ($sort === $column)
                                            <svg class="payments-sort-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="{{ $direction === 'asc' ? 'm6 12 4-4 4 4' : 'm6 8 4 4 4-4' }}" /></svg>
                                        @endif
                                    </a>
                                </th>
                            @endforeach
                            <th scope="col">{{ __('Payment method') }}</th>
                            <th scope="col" aria-sort="{{ $sort === 'status' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a href="{{ route('admin.transactions.index', array_merge($exportFilters, ['sort' => 'status', 'direction' => $sort === 'status' && $direction === 'asc' ? 'desc' : 'asc'])) }}">{{ __('Status') }}</a>
                            </th>
                            <th scope="col">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $transaction)
                            @php
                                $visit = $transaction->appointment?->scheduled_start_at?->timezone('Asia/Manila');
                                $methodIcon = match ($transaction->payment_method) {
                                    \App\Models\Transaction::METHOD_CASH => 'cash',
                                    \App\Models\Transaction::METHOD_GCASH => 'phone',
                                    \App\Models\Transaction::METHOD_BANK_TRANSFER => 'bank',
                                    default => 'wallet',
                                };
                            @endphp
                            <tr>
                                <td class="payments-id">{{ $transaction->transaction_number }}</td>
                                <td class="payments-customer">
                                    <span>{{ $transaction->customerProfile?->display_name ?: __('Customer unavailable') }}</span>
                                    @if (filled($transaction->customerProfile?->display_email))
                                        <p>{{ $transaction->customerProfile->display_email }}</p>
                                    @endif
                                </td>
                                <td class="payments-visit">
                                    @if ($visit)
                                        <time datetime="{{ $visit->toIso8601String() }}">{{ $visit->format('M j, Y') }}<span>{{ $visit->format('g:i A') }}</span></time>
                                    @else
                                        {{ __('No linked visit') }}
                                    @endif
                                </td>
                                <td class="payments-amount">{{ $money($transaction->amount) }}</td>
                                <td><span class="payments-method"><x-payment-icon :name="$methodIcon" />{{ $transaction->payment_method ?: __('Not set') }}</span></td>
                                <td><span class="payments-status payments-status--{{ $transaction->payment_status }}">{{ ucfirst($transaction->payment_status) }}</span></td>
                                <td>
                                    <div class="payments-row-actions">
                                        <a href="{{ route('admin.transactions.show', $transaction) }}" aria-label="{{ __('View payment :number', ['number' => $transaction->transaction_number]) }}" title="{{ __('View payment') }}"><x-payment-icon name="eye" /></a>
                                        <a href="{{ route('admin.transactions.edit', $transaction) }}" aria-label="{{ __('Edit payment :number', ['number' => $transaction->transaction_number]) }}" title="{{ __('Edit payment') }}"><x-payment-icon name="pencil" /></a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table-shell>
                <div class="payments-pagination">{{ $transactions->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
