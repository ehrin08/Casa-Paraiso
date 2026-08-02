<x-app-layout>
    @php
        $reportActiveFilters = collect(request()->only(['q', 'date_from', 'date_to', 'status', 'payment_status', 'sentiment_label']))
            ->filter(fn ($value) => filled($value))
            ->count();
    @endphp
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Reports') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Filter operational records and export CSV files for management review.') }}
            </p>
        </div>

        <a href="{{ route('admin.reports.export', request()->query()) }}" class="casa-button-primary" data-turbo="false">{{ __('Export CSV') }}</a>
    </x-slot>

    <div class="space-y-6">
        <section class="casa-metric-grid grid gap-3 sm:gap-4 md:grid-cols-4" data-metric-grid>
            <x-metric-card label="Appointments" :value="$summary['appointments']" meta="All booking records" tone="brown" />
            <x-metric-card label="Revenue" value="PHP {{ number_format((float) $summary['revenue'], 2) }}" meta="Paid transactions" tone="green" />
            <x-metric-card label="Customers" :value="$summary['customers']" meta="Profile records" tone="gold" />
            <x-metric-card label="Feedback" :value="$summary['feedback']" meta="Submitted reviews" tone="charcoal" />
        </section>

        <x-app-card x-data="{ filtersOpen: false }">
            <div class="flex flex-col gap-3 border-b border-casa-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="casa-section-label">{{ __('Filters') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Report controls') }}</h2>
                </div>
                <button
                    type="button"
                    class="casa-button-secondary justify-between lg:hidden"
                    aria-controls="report-filters"
                    x-bind:aria-expanded="filtersOpen"
                    x-on:click="filtersOpen = ! filtersOpen"
                >
                    <span>{{ __('Filters') }}</span>
                    @if ($reportActiveFilters > 0)
                        <span class="grid min-h-6 min-w-6 place-items-center rounded-full bg-casa-palm px-1.5 text-sm text-white">{{ $reportActiveFilters }}</span>
                    @endif
                </button>
            </div>

            @php
                $statusOptions = match ($type) {
                    'appointments' => \App\Models\Appointment::STATUSES,
                    'promotions' => ['available', 'reserved', 'used', 'dismissed', 'expired'],
                    'customers' => ['active', 'inactive'],
                    default => [],
                };
                $dateContext = match ($type) {
                    'appointments' => __('Appointment date and time.'),
                    'transactions' => __('Date payment was received.'),
                    'customers' => __('Date the customer profile was created.'),
                    'promotions' => __('Date the customer reward was issued.'),
                    'feedback' => __('Date feedback was submitted.'),
                };
            @endphp

            <form id="report-filters" method="GET" action="{{ route('admin.reports.index') }}" class="mt-4 hidden gap-3 lg:grid lg:grid-cols-6" x-bind:class="filtersOpen ? '!grid' : ''">
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? '' }}">
                <input type="hidden" name="direction" value="{{ $filters['direction'] ?? '' }}">
                <div>
                    <x-input-label for="type" :value="__('Report')" />
                    <select id="type" name="type" class="casa-input mt-2">
                        @foreach ($types as $option)
                            <option value="{{ $option }}" @selected($type === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <x-input-label for="q" :value="__('Search')" />
                    <x-text-input id="q" name="q" type="search" class="mt-2" :value="$filters['q'] ?? null" placeholder="{{ __('Customer, number, service, offer') }}" />
                </div>
                <div>
                    <x-input-label for="date_from" :value="__('From')" />
                    <x-text-input id="date_from" name="date_from" type="date" class="mt-2" :value="$filters['date_from'] ?? null" />
                </div>
                <div>
                    <x-input-label for="date_to" :value="__('To')" />
                    <x-text-input id="date_to" name="date_to" type="date" class="mt-2" :value="$filters['date_to'] ?? null" />
                </div>
                @if ($statusOptions !== [])
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <select id="status" name="status" class="casa-input mt-2">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ($statusOptions as $option)
                            <option value="{{ $option }}" @selected(($filters['status'] ?? '') === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                @if ($type === 'transactions')
                <div>
                    <x-input-label for="payment_status" :value="__('Payment')" />
                    <select id="payment_status" name="payment_status" class="casa-input mt-2">
                        <option value="">{{ __('Any') }}</option>
                        @foreach (\App\Models\Transaction::PAYMENT_STATUSES as $option)
                            <option value="{{ $option }}" @selected(($filters['payment_status'] ?? '') === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                @if ($type === 'feedback')
                <div>
                    <x-input-label for="sentiment_label" :value="__('Sentiment')" />
                    <select id="sentiment_label" name="sentiment_label" class="casa-input mt-2">
                        <option value="">{{ __('Any') }}</option>
                        @foreach (\App\Models\Feedback::SENTIMENT_LABELS as $option)
                            <option value="{{ $option }}" @selected(($filters['sentiment_label'] ?? '') === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <p class="text-xs leading-5 text-casa-muted lg:col-span-6">{{ $dateContext }}</p>
                <div class="lg:col-span-6">
                    <button type="submit" class="casa-button-secondary">{{ __('Apply filters') }}</button>
                </div>
            </form>
        </x-app-card>

        <x-app-card>
            <x-list-toolbar eyebrow="{{ __('Results') }}" title="{{ ucfirst($type) }}" :count="$records->total()" :reset-url="route('admin.reports.index', ['type' => $type])" :active-filters="collect(request()->only(['q', 'date_from', 'date_to', 'status', 'payment_status', 'sentiment_label']))->filter(fn ($value) => filled($value))->count()">
                <a href="{{ route('admin.reports.export', request()->query()) }}" class="casa-button-secondary" data-turbo="false">{{ __('Export filtered CSV') }}</a>
            </x-list-toolbar>

            <div class="mt-5">
                @if ($records->isEmpty())
                    <x-empty-state title="{{ __('No report rows') }}" description="{{ __('Try adjusting the date range or status filters.') }}" />
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                @if ($type === 'transactions')
                                    <x-sortable-th sort="number">{{ __('No.') }}</x-sortable-th>
                                    <x-sortable-th sort="customer">{{ __('Customer') }}</x-sortable-th>
                                    <x-sortable-th sort="service">{{ __('Service') }}</x-sortable-th>
                                    <x-sortable-th sort="amount">{{ __('Amount') }}</x-sortable-th>
                                    <x-sortable-th sort="status">{{ __('Status') }}</x-sortable-th>
                                    <x-sortable-th sort="paid_at">{{ __('Paid at') }}</x-sortable-th>
                                @elseif ($type === 'customers')
                                    <x-sortable-th sort="code">{{ __('Code') }}</x-sortable-th>
                                    <x-sortable-th sort="name">{{ __('Customer') }}</x-sortable-th>
                                    <th class="px-4 py-3">{{ __('Email') }}</th>
                                    <x-sortable-th sort="joined">{{ __('Joined') }}</x-sortable-th>
                                    <x-sortable-th sort="status">{{ __('Status') }}</x-sortable-th>
                                    <x-sortable-th sort="appointments">{{ __('Appointments') }}</x-sortable-th>
                                    <x-sortable-th sort="transactions">{{ __('Transactions') }}</x-sortable-th>
                                    <x-sortable-th sort="feedback">{{ __('Feedback') }}</x-sortable-th>
                                    <x-sortable-th sort="promotions">{{ __('Rewards') }}</x-sortable-th>
                                @elseif ($type === 'promotions')
                                    <x-sortable-th sort="customer">{{ __('Customer') }}</x-sortable-th>
                                    <x-sortable-th sort="segment">{{ __('Customer group') }}</x-sortable-th>
                                    <th class="px-4 py-3">{{ __('Reward') }}</th>
                                    <x-sortable-th sort="monetary">{{ __('Customer activity') }}</x-sortable-th>
                                    <x-sortable-th sort="status">{{ __('Status') }}</x-sortable-th>
                                @elseif ($type === 'feedback')
                                    <x-sortable-th sort="customer">{{ __('Customer') }}</x-sortable-th>
                                    <x-sortable-th sort="service">{{ __('Service') }}</x-sortable-th>
                                    <x-sortable-th sort="rating">{{ __('Rating') }}</x-sortable-th>
                                    <x-sortable-th sort="sentiment">{{ __('Sentiment') }}</x-sortable-th>
                                    <x-sortable-th sort="submitted">{{ __('Submitted') }}</x-sortable-th>
                                @else
                                    <x-sortable-th sort="number">{{ __('No.') }}</x-sortable-th>
                                    <x-sortable-th sort="customer">{{ __('Customer') }}</x-sortable-th>
                                    <x-sortable-th sort="service">{{ __('Service') }}</x-sortable-th>
                                    <x-sortable-th sort="schedule">{{ __('Appointment date') }}</x-sortable-th>
                                    <x-sortable-th sort="status">{{ __('Status') }}</x-sortable-th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($records as $record)
                                <tr class="casa-table-row">
                                    @if ($type === 'transactions')
                                        <td class="px-4 py-4 font-semibold text-casa-text">{{ $record->transaction_number }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $record->customerProfile?->user?->name }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $record->service?->name }}</td>
                                        <td class="px-4 py-4 font-semibold text-casa-text">PHP {{ number_format((float) $record->amount, 2) }}</td>
                                        <td class="px-4 py-4"><x-status-badge>{{ ucfirst($record->payment_status) }}</x-status-badge></td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $record->paid_at?->format('M d, Y g:i A') ?: __('Not paid') }}</td>
                                    @elseif ($type === 'customers')
                                        <td class="px-4 py-4 font-semibold text-casa-text">{{ $record->customer_code }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $record->user?->name }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $record->user?->email }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $record->created_at?->format('M d, Y') }}</td>
                                        <td class="px-4 py-4"><x-status-badge :tone="$record->user?->is_active ? 'success' : 'dark'">{{ $record->user?->is_active ? __('Active') : __('Inactive') }}</x-status-badge></td>
                                        <td class="px-4 py-4 text-casa-muted">{{ trans_choice(':count appointment|:count appointments', $record->appointments_count) }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ trans_choice(':count transaction|:count transactions', $record->transactions_count) }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ trans_choice(':count feedback|:count feedback', $record->feedback_count) }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ trans_choice(':count reward|:count rewards', $record->promotion_suggestions_count) }}</td>
                                    @elseif ($type === 'promotions')
                                        <td class="px-4 py-4 font-semibold text-casa-text">{{ $record->customerProfile?->user?->name }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $record->rfmSegment?->name }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $record->addonName() ?: $record->suggested_offer }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ __(':days days · :visits paid visits', ['days' => $record->recency_days ?? __('N/A'), 'visits' => $record->frequency_count ?? 0]) }}</td>
                                        <td class="px-4 py-4"><x-status-badge>{{ ucfirst($record->lifecycle()) }}</x-status-badge></td>
                                    @elseif ($type === 'feedback')
                                        <td class="px-4 py-4 font-semibold text-casa-text">{{ $record->customerProfile?->user?->name }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $record->service?->name }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $record->rating }}/5</td>
                                        <td class="px-4 py-4"><x-status-badge>{{ ucfirst($record->sentiment_label) }}</x-status-badge></td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $record->submitted_at?->format('M d, Y') }}</td>
                                    @else
                                        <td class="px-4 py-4 font-semibold text-casa-text">{{ $record->appointment_number }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $record->customerProfile?->user?->name }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $record->service?->name }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ ($record->scheduled_start_at ?? $record->requested_start_at)?->format('M d, Y g:i A') }}</td>
                                        <td class="px-4 py-4"><x-status-badge>{{ ucfirst(str_replace('_', ' ', $record->status)) }}</x-status-badge></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>

                    <div class="mt-5">{{ $records->links() }}</div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
