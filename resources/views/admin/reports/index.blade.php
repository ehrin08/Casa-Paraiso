<x-app-layout>
    <x-slot name="header">
        <div class="reports-heading"><h1>{{ __('Reports') }}</h1><p>{{ __('Insights on your spa’s performance, customers and services.') }}</p></div>
    </x-slot>
    @php
        $labels = ['appointments' => __('Appointments'), 'transactions' => __('Transactions'), 'customers' => __('Customers'), 'promotions' => __('Rewards'), 'feedback' => __('Feedback')];
        $visible = match ($category) {
            'appointments' => ['completed', 'customers', 'appointments'],
            'transactions' => ['revenue', 'services'],
            'customers' => ['customers', 'rfm'],
            'promotions' => ['rfm'],
            'feedback' => ['sentiment'],
            default => ['revenue', 'completed', 'customers', 'appointments', 'sentiment', 'services', 'rfm'],
        };
        $context = [...$range->query(), 'category' => $category, 'type' => $type];
        $recordFilters = collect($filters)->only(['q', 'status', 'payment_status', 'sentiment_label'])->filter(fn ($value) => filled($value))->count();
        $statusOptions = match ($type) {
            'appointments' => \App\Models\Appointment::STATUSES,
            'promotions' => ['available', 'reserved', 'used', 'dismissed', 'expired'],
            'customers' => ['active', 'inactive'],
            default => [],
        };
        $dateContext = match ($type) {
            'appointments' => __('Appointment dates'), 'transactions' => __('Payment dates'), 'customers' => __('Profile creation dates'), 'promotions' => __('Reward issue dates'), 'feedback' => __('Feedback submission dates'),
        };
    @endphp
    <div class="reports-workspace" data-reports-workspace data-report-category="{{ $category }}">
        <section class="reports-controls" aria-label="{{ __('Report controls') }}">
            <div class="reports-field reports-range-field">
                <span id="reports-range-label">{{ __('Date range') }}</span>
                <details class="reports-date-picker" @if ($editingCustomDates || $errors->hasAny(['range', 'date_from', 'date_to'])) open @endif>
                    <summary aria-labelledby="reports-range-label reports-range-value"><x-nav-icon name="calendar" /><span id="reports-range-value">{{ $range->label() }}</span><span class="reports-chevron" aria-hidden="true">⌄</span></summary>
                    <div class="reports-date-menu">
                        <form method="GET" action="{{ route('admin.reports.index') }}" x-data>
                            <input type="hidden" name="category" value="{{ $category }}"><input type="hidden" name="type" value="{{ $type }}">
                            @foreach (collect($filters)->except(['date_from', 'date_to'])->filter(fn ($value) => filled($value)) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                            <label for="reports-range">{{ __('Choose a range') }}</label>
                            <select id="reports-range" name="range" class="casa-input" x-on:change="$el.form.requestSubmit()">
                                @foreach (\App\Services\ReportDateRange::OPTIONS as $value => $label)<option value="{{ $value }}" @selected($range->key === $value)>{{ __($label) }}</option>@endforeach
                            </select>
                            <noscript><button type="submit" class="casa-button-secondary">{{ __('Apply range') }}</button></noscript>
                        </form>
                        <form method="GET" action="{{ route('admin.reports.index') }}" class="reports-custom-dates">
                            <input type="hidden" name="category" value="{{ $category }}"><input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="range" value="custom">
                            @foreach (collect($filters)->except(['date_from', 'date_to'])->filter(fn ($value) => filled($value)) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                            <div class="reports-field"><label for="reports-date-from">{{ __('From') }}</label><input id="reports-date-from" name="date_from" type="date" class="casa-input" value="{{ old('date_from', $range->from?->toDateString()) }}"><x-input-error :messages="$errors->get('date_from')" /></div>
                            <div class="reports-field"><label for="reports-date-to">{{ __('To') }}</label><input id="reports-date-to" name="date_to" type="date" class="casa-input" value="{{ old('date_to', $range->to?->toDateString()) }}"><x-input-error :messages="$errors->get('date_to')" /></div>
                            <p>{{ __('Inclusive dates · Asia/Manila') }}</p><button type="submit" class="casa-button-primary">{{ __('Apply dates') }}</button>
                        </form>
                        <x-input-error :messages="$errors->get('range')" />
                    </div>
                </details>
            </div>
            <form method="GET" action="{{ route('admin.reports.index') }}" class="reports-field reports-category" x-data>
                @foreach ([...$range->query(), 'type' => $type] as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                <label for="reports-category">{{ __('Report category') }}</label>
                <select id="reports-category" name="category" class="casa-input" x-on:change="$el.form.requestSubmit()"><option value="all" @selected($category === 'all')>{{ __('All reports') }}</option>@foreach ($labels as $value => $label)<option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>@endforeach</select>
                <noscript><button type="submit" class="casa-button-secondary">{{ __('Apply category') }}</button></noscript><x-input-error :messages="$errors->get('category')" />
            </form>
            <a href="{{ route('admin.reports.export', $filterQuery) }}" class="casa-button-primary reports-export" data-turbo="false" aria-label="{{ __('Export :report CSV', ['report' => $labels[$type]]) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M5 17v4h14v-4" /></svg>{{ __('Export CSV') }}</a>
        </section>
        @if (array_intersect(['revenue', 'completed', 'customers'], $visible))
            <section class="reports-metrics" data-metric-grid aria-label="{{ __('Selected-period metrics') }}" style="--report-metrics: {{ count(array_intersect(['revenue', 'completed', 'customers'], $visible)) }}">
                @foreach (['revenue' => ['Total revenue', 'payments'], 'completed' => ['Completed appointments', 'calendar'], 'customers' => ['Unique customers', 'customers']] as $key => [$label, $icon])
                    @continue(! in_array($key, $visible, true))
                    @php($metric = $dashboard['metrics'][$key])
                    <article class="reports-metric" data-metric-card data-report-metric="{{ $key }}">
                        <span class="reports-metric-icon"><x-nav-icon :name="$icon" /></span>
                        <div><h2>{{ __($label) }}</h2><p class="reports-metric-value">{{ $key === 'revenue' ? '₱'.number_format($metric['value'], fmod($metric['value'], 1) === 0.0 ? 0 : 2) : number_format($metric['value']) }}</p>
                            @if ($metric['has_previous'])<p class="reports-comparison" data-metric-meta>
                                @if ($metric['change'] !== null)<span class="reports-change {{ $metric['change'] < 0 ? 'reports-change--down' : '' }}">{{ $metric['change'] > 0 ? '↑ +' : ($metric['change'] < 0 ? '↓ ' : '') }}{{ number_format($metric['change'], 1) }}%</span>@else<span>{{ __('No prior activity') }}</span>@endif
                                <span>{{ __('vs :period', ['period' => $dashboard['comparison_label']]) }}</span>
                            </p>@else<p class="reports-comparison" data-metric-meta>{{ __('Selected period') }}</p>@endif
                        </div>
                    </article>
                @endforeach
            </section>
        @endif
        @if (array_intersect(['revenue', 'appointments', 'sentiment'], $visible))
            <section class="reports-charts {{ $category !== 'all' ? 'reports-charts--focused' : '' }}" aria-label="{{ __('Performance charts') }}">
                @if (in_array('revenue', $visible, true))
                    @php($revenue = $dashboard['revenue'])
                    <section class="reports-panel reports-revenue" aria-labelledby="reports-revenue-heading">
                        <h2 id="reports-revenue-heading">{{ __('Revenue by :unit', ['unit' => $revenue['unit']]) }}</h2>
                        @if ($dashboard['metrics']['revenue']['value'] == 0)<p class="reports-chart-empty">{{ __('No paid transactions in this period.') }}</p>@endif
                        <div class="reports-revenue-chart"><div class="reports-axis" aria-hidden="true">@foreach ([1, .75, .5, .25, 0] as $tick)<span>₱{{ number_format($revenue['max'] * $tick, 0) }}</span>@endforeach</div>
                            <div class="reports-bars-scroll" role="region" aria-label="{{ __('Revenue chart with period totals') }}"><div class="reports-bars" style="--report-columns: {{ min(7, count($revenue['buckets'])) }}">
                                @foreach ($revenue['buckets'] as $bucket)<div class="reports-bar-column"><div class="reports-bar-area"><span class="reports-bar-value">₱{{ number_format($bucket['total'], fmod($bucket['total'], 1) === 0.0 ? 0 : 2) }}</span><span class="reports-bar" style="--report-height: {{ $bucket['total'] / $revenue['max'] * 100 }}%" aria-hidden="true"></span></div><span class="reports-bar-label">{{ $bucket['label'] }}</span></div>@endforeach
                            </div></div>
                        </div>
                    </section>
                @endif
                @if (in_array('appointments', $visible, true))<x-report-distribution id="reports-appointments" title="Appointment status" :distribution="$dashboard['appointments']" caption="Appointments" />@endif
                @if (in_array('sentiment', $visible, true))<x-report-distribution id="reports-sentiment" title="Feedback sentiment" :distribution="$dashboard['sentiment']" caption="Feedback" />@endif
            </section>
        @endif
        @if (array_intersect(['services', 'rfm'], $visible))
            <section class="reports-breakdowns {{ $category !== 'all' ? 'reports-breakdowns--focused' : '' }}" aria-label="{{ __('Service and customer insights') }}">
                @if (in_array('services', $visible, true))
                    <section class="reports-panel" aria-labelledby="reports-services-heading"><h2 id="reports-services-heading">{{ __('Top services by revenue') }}</h2>
                        @if ($dashboard['services']->isEmpty())<p class="reports-panel-empty">{{ __('No paid service revenue in this period.') }}</p>@else
                            @php($serviceMax = max(1, (float) $dashboard['services']->max('total')))
                            <div class="reports-services">@foreach ($dashboard['services'] as $service)<div class="reports-service"><span>{{ $service->name }}</span><div class="reports-service-track" aria-hidden="true"><span style="width: {{ (float) $service->total / $serviceMax * 100 }}%"></span></div><strong>₱{{ number_format((float) $service->total, fmod((float) $service->total, 1) === 0.0 ? 0 : 2) }}</strong></div>@endforeach</div>
                        @endif
                    </section>
                @endif
                @if (in_array('rfm', $visible, true))
                    <section class="reports-panel reports-rfm" aria-labelledby="reports-rfm-heading"><h2 id="reports-rfm-heading">{{ __('Customers by RFM group') }}</h2>
                        <x-table-shell label="{{ __('Scrollable RFM customer groups') }}"><thead><tr><th scope="col">{{ __('RFM group') }}</th><th scope="col">{{ __('Customers') }}</th><th scope="col">{{ __('% of total') }}</th><th scope="col">{{ __('Avg. revenue') }}</th></tr></thead><tbody>
                            @foreach ($dashboard['rfm']['rows'] as $row)<tr><th scope="row">{{ __($row['name']) }}<span class="reports-rfm-bar" aria-hidden="true"><span style="width: {{ $row['percentage'] }}%"></span></span></th><td>{{ number_format($row['customers']) }}</td><td>{{ number_format($row['percentage'], 1) }}%</td><td>₱{{ number_format($row['average'], fmod($row['average'], 1) === 0.0 ? 0 : 2) }}</td></tr>@endforeach
                        </tbody></x-table-shell>
                        <p class="reports-help">{{ __('All :count profiles as of :date. Average revenue per customer uses the selected period.', ['count' => number_format($dashboard['rfm']['total']), 'date' => $dashboard['cutoff']->format('M j, Y')]) }}</p>
                        @if ($dashboard['rfm']['total'] === 0)<p class="reports-panel-empty">{{ __('No customer profiles at this reporting cutoff.') }}</p>@endif
                    </section>
                @endif
            </section>
        @endif
        <section class="reports-panel reports-records" aria-label="{{ __('Report records') }}">
            <nav class="reports-tabs" aria-label="{{ __('Report record types') }}">@foreach ($labels as $value => $label)<a href="{{ route('admin.reports.index', [...$range->query(), 'category' => $category === 'all' ? 'all' : $value, 'type' => $value]) }}" @if ($type === $value) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
            <x-list-toolbar :title="$labels[$type]" :count="$records->total()" :reset-url="route('admin.reports.index', $context)" :active-filters="$recordFilters" :collapsible="true" class="reports-record-toolbar" x-init="{{ $errors->any() ? 'filtersOpen = true' : 'null' }}">
                <form method="GET" action="{{ route('admin.reports.index') }}" class="reports-record-filters" x-data>
                    @foreach ([...$context, 'sort' => $filters['sort'] ?? '', 'direction' => $filters['direction'] ?? ''] as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                    <div class="reports-field reports-search"><label for="reports-search">{{ __('Search records') }}</label><input id="reports-search" type="search" name="q" class="casa-input" maxlength="255" value="{{ old('q', $filters['q'] ?? '') }}" placeholder="{{ __('Customer, number, service, offer') }}"><x-input-error :messages="$errors->get('q')" /></div>
                    @if ($statusOptions)<div class="reports-field"><label for="reports-status">{{ __('Status') }}</label><select id="reports-status" name="status" class="casa-input" x-on:change="$el.form.requestSubmit()"><option value="">{{ __('Any status') }}</option>@foreach ($statusOptions as $value)<option value="{{ $value }}" @selected(old('status', $filters['status'] ?? '') === $value)>{{ ucfirst(str_replace('_', ' ', $value)) }}</option>@endforeach</select><x-input-error :messages="$errors->get('status')" /></div>
                    @elseif ($type === 'transactions')<div class="reports-field"><label for="reports-payment">{{ __('Payment status') }}</label><select id="reports-payment" name="payment_status" class="casa-input" x-on:change="$el.form.requestSubmit()"><option value="">{{ __('Any payment') }}</option>@foreach (\App\Models\Transaction::PAYMENT_STATUSES as $value)<option value="{{ $value }}" @selected(old('payment_status', $filters['payment_status'] ?? '') === $value)>{{ ucfirst($value) }}</option>@endforeach</select><x-input-error :messages="$errors->get('payment_status')" /></div>
                    @elseif ($type === 'feedback')<div class="reports-field"><label for="reports-sentiment-filter">{{ __('Sentiment') }}</label><select id="reports-sentiment-filter" name="sentiment_label" class="casa-input" x-on:change="$el.form.requestSubmit()"><option value="">{{ __('Any sentiment') }}</option>@foreach (\App\Models\Feedback::SENTIMENT_LABELS as $value)<option value="{{ $value }}" @selected(old('sentiment_label', $filters['sentiment_label'] ?? '') === $value)>{{ ucfirst($value) }}</option>@endforeach</select><x-input-error :messages="$errors->get('sentiment_label')" /></div>@endif
                    <button type="submit" class="casa-button-secondary">{{ __('Apply filters') }}</button>
                </form>
            </x-list-toolbar>
            <p class="reports-help reports-record-help">{{ __(':dates · Asia/Manila. Search and status filter these records and their CSV; dashboard panels use the date range.', ['dates' => $dateContext]) }}</p>
            <noscript><style>.reports-record-toolbar [data-filter-panel] { display: block; } .reports-record-toolbar [data-filter-toggle] { display: none; }</style></noscript>
            @if ($records->isEmpty())<x-empty-state title="No report rows" description="Try adjusting the date range or record filters." />@else<x-report-records :type="$type" :records="$records" :reward-names="$rewardNames" /><div class="reports-pagination">{{ $records->links('pagination.compact') }}</div>@endif
        </section>
    </div>
</x-app-layout>
