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


        {{-- ── Insights section ──────────────────────────────────────────── --}}
        <section
            id="insights-section"
            x-data="casaInsights('{{ route('admin.reports.insights') }}')"
            x-init="init()"
            class="casa-insights-section"
        >
            {{-- Section header --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-xl border border-casa-border bg-casa-paper px-4 py-2 text-sm font-bold text-casa-text shadow-sm transition hover:bg-casa-sand"
                        x-on:click="toggleOpen()"
                        :aria-expanded="open"
                        aria-controls="insights-charts"
                    >
                        <svg class="h-4 w-4 text-casa-cacao transition-transform duration-300" :class="open ? 'rotate-0' : '-rotate-90'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        <span>{{ __('Insights') }}</span>
                    </button>

                    {{-- Period switcher --}}
                    <div class="flex rounded-xl border border-casa-border bg-casa-paper shadow-sm" role="group" aria-label="{{ __('Insights period') }}" x-show="open" x-transition>
                        <template x-for="p in periods" :key="p.value">
                            <button
                                type="button"
                                class="px-3 py-2 text-xs font-bold transition first:rounded-l-xl last:rounded-r-xl"
                                :class="period === p.value ? 'bg-casa-cacao text-white' : 'text-casa-muted hover:text-casa-text'"
                                x-on:click="setPeriod(p.value)"
                                x-text="p.label"
                                :aria-pressed="period === p.value"
                            ></button>
                        </template>
                    </div>
                </div>

                <p class="text-xs text-casa-muted" x-show="open && !loading" x-transition x-text="periodLabel"></p>
            </div>

            {{-- Charts grid --}}
            <div id="insights-charts" x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-4 space-y-4">

                {{-- Loading shimmer --}}
                <template x-if="loading">
                    <div class="grid gap-4 lg:grid-cols-1">
                        <div class="h-64 animate-pulse rounded-2xl bg-casa-sand/60"></div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="h-56 animate-pulse rounded-2xl bg-casa-sand/60"></div>
                            <div class="h-56 animate-pulse rounded-2xl bg-casa-sand/60"></div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="h-56 animate-pulse rounded-2xl bg-casa-sand/60"></div>
                            <div class="h-56 animate-pulse rounded-2xl bg-casa-sand/60"></div>
                        </div>
                    </div>
                </template>

                <template x-if="!loading">
                    <div class="space-y-4">

                        {{-- Revenue area chart (full width) --}}
                        <x-app-card>
                            <div class="mb-4 flex items-center justify-between">
                                <div>
                                    <p class="casa-section-label">{{ __('Revenue') }}</p>
                                    <h3 class="mt-1 font-display text-lg font-black text-casa-text">{{ __('Daily paid revenue') }}</h3>
                                </div>
                                <span class="font-display text-2xl font-black text-casa-cacao" x-text="'PHP ' + revenueTotal.toLocaleString('en-PH', {minimumFractionDigits:2})"></span>
                            </div>
                            <template x-if="revenueData.length > 0">
                                <div class="relative h-56">
                                    <canvas id="chart-revenue" aria-label="{{ __('Revenue over time chart') }}" role="img"></canvas>
                                </div>
                            </template>
                            <template x-if="revenueData.length === 0">
                                <p class="py-10 text-center text-sm text-casa-muted">{{ __('No paid transactions in this period.') }}</p>
                            </template>
                        </x-app-card>

                        {{-- Row 2: Appointments by status + Feedback sentiment --}}
                        <div class="grid gap-4 sm:grid-cols-2">

                            <x-app-card>
                                <p class="casa-section-label">{{ __('Appointments') }}</p>
                                <h3 class="mt-1 mb-4 font-display text-lg font-black text-casa-text">{{ __('Volume by status') }}</h3>
                                <template x-if="appointmentsData.length > 0">
                                    <div class="relative h-48">
                                        <canvas id="chart-appointments" aria-label="{{ __('Appointments by status chart') }}" role="img"></canvas>
                                    </div>
                                </template>
                                <template x-if="appointmentsData.length === 0">
                                    <p class="py-10 text-center text-sm text-casa-muted">{{ __('No appointment data in this period.') }}</p>
                                </template>
                            </x-app-card>

                            <x-app-card>
                                <p class="casa-section-label">{{ __('Feedback') }}</p>
                                <h3 class="mt-1 mb-4 font-display text-lg font-black text-casa-text">{{ __('Sentiment breakdown') }}</h3>
                                <template x-if="sentimentData.length > 0">
                                    <div class="relative h-48">
                                        <canvas id="chart-sentiment" aria-label="{{ __('Feedback sentiment chart') }}" role="img"></canvas>
                                    </div>
                                </template>
                                <template x-if="sentimentData.length === 0">
                                    <p class="py-10 text-center text-sm text-casa-muted">{{ __('No feedback in this period.') }}</p>
                                </template>
                            </x-app-card>
                        </div>

                        {{-- Row 3: Top services + RFM segments --}}
                        <div class="grid gap-4 sm:grid-cols-2">

                            <x-app-card>
                                <p class="casa-section-label">{{ __('Services') }}</p>
                                <h3 class="mt-1 mb-4 font-display text-lg font-black text-casa-text">{{ __('Top by booking count') }}</h3>
                                <template x-if="servicesData.length > 0">
                                    <div class="relative h-48">
                                        <canvas id="chart-services" aria-label="{{ __('Top services chart') }}" role="img"></canvas>
                                    </div>
                                </template>
                                <template x-if="servicesData.length === 0">
                                    <p class="py-10 text-center text-sm text-casa-muted">{{ __('No service booking data in this period.') }}</p>
                                </template>
                            </x-app-card>

                            <x-app-card>
                                <p class="casa-section-label">{{ __('Rewards') }}</p>
                                <h3 class="mt-1 mb-4 font-display text-lg font-black text-casa-text">{{ __('RFM segment distribution') }}</h3>
                                <template x-if="rfmData.length > 0">
                                    <div class="relative h-48">
                                        <canvas id="chart-rfm" aria-label="{{ __('RFM segment distribution chart') }}" role="img"></canvas>
                                    </div>
                                </template>
                                <template x-if="rfmData.length === 0">
                                    <p class="py-10 text-center text-sm text-casa-muted">{{ __('No rewards data in this period.') }}</p>
                                </template>
                            </x-app-card>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <x-app-card x-data="{ filtersOpen: {{ $reportActiveFilters > 0 ? 'true' : 'false' }} }">

            <div class="flex flex-col gap-3 border-b border-casa-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="casa-section-label">{{ __('Filters') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Report controls') }}</h2>
                    @if ($reportActiveFilters > 0)
                        <a href="{{ route('admin.reports.index', ['type' => $type]) }}" class="casa-filter-chip mt-2 hover:border-casa-gold hover:text-casa-primary">{{ __('Clear filters') }}</a>
                    @endif
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

            <form id="report-filters" method="GET" action="{{ route('admin.reports.index') }}" class="casa-filter-form casa-report-filter-form mt-4 hidden lg:flex" x-bind:class="filtersOpen ? '!flex' : ''">
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
                <div class="casa-report-filter-search">
                    <x-input-label for="q" :value="__('Search')" />
                    <x-filter-search input-id="q" class="mt-2" :value="$filters['q'] ?? ''" placeholder="{{ __('Customer, number, service, offer') }}" label="{{ __('Search reports') }}" />
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
                <p class="text-xs leading-5 text-casa-muted casa-report-filter-note">{{ $dateContext }}</p>
                <div class="casa-report-filter-actions">
                    <button type="submit" class="casa-button-primary">{{ __('Apply filters') }}</button>
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
// ── Brand palette (module-level, available immediately for Alpine) ─────────
    const COLORS = {
        cacao:      '#5C3D2E',
        cacaoLight: '#7A5240',
        gold:       '#C9A84C',
        goldLight:  '#E2C882',
        sand:       '#F5EFE6',
        palm:       '#4A7C59',
        palmLight:  '#6A9E78',
        muted:      '#9B8E82',
        dusty:      '#B5A99E',
        warm1:      '#8B6E5A',
        warm2:      '#A68B72',
        warm3:      '#C4A882',
    };

    const STATUS_COLORS = {
        confirmed:  COLORS.gold,
        completed:  COLORS.palm,
        cancelled:  COLORS.muted,
        pending:    COLORS.warm2,
        rejected:   COLORS.cacao,
        no_show:    COLORS.dusty,
    };

    const SENTIMENT_COLORS = {
        positive: COLORS.palm,
        neutral:  COLORS.gold,
        negative: COLORS.cacao,
        unrated:  COLORS.dusty,
    };

    const RFM_PALETTE = [
        COLORS.cacao, COLORS.gold, COLORS.palm,
        COLORS.warm1, COLORS.warm2, COLORS.warm3,
        COLORS.goldLight, COLORS.palmLight, COLORS.muted,
    ];


    // ── Alpine component ───────────────────────────────────────────────────
    window.casaInsights = function (endpoint) {
        return {
            endpoint,
            open:    true,
            loading: false,
            period:  '30',
            periods: [
                { value: '7',   label: '7d'  },
                { value: '30',  label: '30d' },
                { value: '90',  label: '90d' },
                { value: 'all', label: 'All' },
            ],

            revenueData:      [],
            revenueTotal:     0,
            appointmentsData: [],
            sentimentData:    [],
            servicesData:     [],
            rfmData:          [],

            charts: {},

            get periodLabel() {
                const map = {
                    '7':   'Last 7 days',
                    '30':  'Last 30 days',
                    '90':  'Last 90 days',
                    'all': 'All time',
                };
                return map[this.period] ?? '';
            },

            init() {
                // Restore state from localStorage
                try {
                    const saved = localStorage.getItem('casa.insights.open');
                    if (saved !== null) this.open = saved === 'true';
                    const savedPeriod = localStorage.getItem('casa.insights.period');
                    if (savedPeriod && ['7','30','90','all'].includes(savedPeriod)) {
                        this.period = savedPeriod;
                    }
                } catch {}
                if (this.open) this.fetchData();
            },

            toggleOpen() {
                this.open = !this.open;
                try { localStorage.setItem('casa.insights.open', this.open); } catch {}
                if (this.open && this.revenueData.length === 0 && !this.loading) {
                    this.fetchData();
                }
            },

            setPeriod(val) {
                if (this.period === val) return;
                this.period = val;
                try { localStorage.setItem('casa.insights.period', val); } catch {}
                this.fetchData();
            },

            async fetchData() {
                this.loading = true;
                this.destroyCharts();
                try {
                    const res  = await fetch(`${this.endpoint}?period=${this.period}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    this.revenueData      = data.revenue              ?? [];
                    this.revenueTotal     = this.revenueData.reduce((s, d) => s + d.total, 0);
                    this.appointmentsData = data.appointments_status  ?? [];
                    this.sentimentData    = data.sentiment             ?? [];
                    this.servicesData     = data.top_services          ?? [];
                    this.rfmData          = data.rfm_segments          ?? [];
                } catch (e) {
                    console.error('[casaInsights] fetch error', e);
                } finally {
                    this.loading = false;
                    // Wait for Alpine to render canvases then build charts
                    this.$nextTick(() => this.buildCharts());
                }
            },

            buildCharts() {
                // Set Chart.js defaults lazily (Chart.js is a sync script above)
                if (typeof Chart !== 'undefined') {
                    Chart.defaults.font.family = "'Manrope', 'Inter', sans-serif";
                    Chart.defaults.font.size   = 12;
                    Chart.defaults.color       = COLORS.muted;
                }
                this.buildRevenue();
                this.buildAppointments();
                this.buildSentiment();
                this.buildServices();
                this.buildRfm();
            },

            destroyCharts() {
                Object.values(this.charts).forEach(c => { try { c.destroy(); } catch {} });
                this.charts = {};
            },

            getCanvas(id) {
                return document.getElementById(id);
            },

            buildRevenue() {
                const canvas = this.getCanvas('chart-revenue');
                if (!canvas || this.revenueData.length === 0) return;
                const ctx = canvas.getContext('2d');

                // Gradient fill
                const gradient = ctx.createLinearGradient(0, 0, 0, 220);
                gradient.addColorStop(0,   'rgba(92,61,46,0.28)');
                gradient.addColorStop(1,   'rgba(92,61,46,0.02)');

                this.charts.revenue = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels:   this.revenueData.map(d => d.date),
                        datasets: [{
                            label:           'Revenue (PHP)',
                            data:            this.revenueData.map(d => d.total),
                            borderColor:     COLORS.cacao,
                            backgroundColor: gradient,
                            borderWidth:     2.5,
                            pointRadius:     3,
                            pointBackgroundColor: COLORS.cacao,
                            tension:         0.4,
                            fill:            true,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: ctx => ' PHP ' + Number(ctx.parsed.y).toLocaleString('en-PH', { minimumFractionDigits: 2 }),
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid:  { color: 'rgba(0,0,0,0.04)' },
                                ticks: { maxTicksLimit: 10 },
                            },
                            y: {
                                grid:  { color: 'rgba(0,0,0,0.04)' },
                                ticks: {
                                    callback: v => 'PHP ' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 0 }),
                                },
                                beginAtZero: true,
                            },
                        },
                    },
                });
            },

            buildAppointments() {
                const canvas = this.getCanvas('chart-appointments');
                if (!canvas || this.appointmentsData.length === 0) return;
                const ctx = canvas.getContext('2d');

                this.charts.appointments = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels:   this.appointmentsData.map(d => d.status.replace('_', ' ')),
                        datasets: [{
                            label:           'Appointments',
                            data:            this.appointmentsData.map(d => d.count),
                            backgroundColor: this.appointmentsData.map(d => STATUS_COLORS[d.status] ?? COLORS.warm2),
                            borderRadius:    6,
                            borderSkipped:   false,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: { grid: { color: 'rgba(0,0,0,0.04)' }, beginAtZero: true, ticks: { stepSize: 1 } },
                        },
                    },
                });
            },

            buildSentiment() {
                const canvas = this.getCanvas('chart-sentiment');
                if (!canvas || this.sentimentData.length === 0) return;
                const ctx = canvas.getContext('2d');

                this.charts.sentiment = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels:   this.sentimentData.map(d => d.label),
                        datasets: [{
                            data:            this.sentimentData.map(d => d.count),
                            backgroundColor: this.sentimentData.map(d => SENTIMENT_COLORS[d.label] ?? COLORS.muted),
                            borderWidth:     0,
                            hoverOffset:     6,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '65%',
                        plugins: {
                            legend: { position: 'right', labels: { boxWidth: 12, padding: 16 } },
                        },
                    },
                });
            },

            buildServices() {
                const canvas = this.getCanvas('chart-services');
                if (!canvas || this.servicesData.length === 0) return;
                const ctx = canvas.getContext('2d');

                this.charts.services = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels:   this.servicesData.map(d => d.name.length > 24 ? d.name.slice(0, 22) + '…' : d.name),
                        datasets: [{
                            label:           'Bookings',
                            data:            this.servicesData.map(d => d.count),
                            backgroundColor: COLORS.gold,
                            borderRadius:    5,
                            borderSkipped:   false,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { color: 'rgba(0,0,0,0.04)' }, beginAtZero: true, ticks: { stepSize: 1 } },
                            y: { grid: { display: false } },
                        },
                    },
                });
            },

            buildRfm() {
                const canvas = this.getCanvas('chart-rfm');
                if (!canvas || this.rfmData.length === 0) return;
                const ctx = canvas.getContext('2d');

                this.charts.rfm = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels:   this.rfmData.map(d => d.name),
                        datasets: [{
                            data:            this.rfmData.map(d => d.count),
                            backgroundColor: this.rfmData.map((_, i) => RFM_PALETTE[i % RFM_PALETTE.length]),
                            borderWidth:     0,
                            hoverOffset:     6,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '65%',
                        plugins: {
                            legend: { position: 'right', labels: { boxWidth: 12, padding: 14 } },
                        },
                    },
                });
            },
        };
    };
</script>
@endpush
