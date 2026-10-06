<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-casa-muted">{{ $dashboardDate->format('l, F j, Y') }}</p>
            <h1 class="casa-dashboard-title mt-1 font-display font-black text-casa-text">{{ __('Today at Casa Paraiso') }}</h1>
            <p class="mt-1 text-sm text-casa-muted">{{ __('A clear view of your day.') }}</p>
        </div>
        <a href="{{ route('admin.appointments.create') }}" class="casa-button-primary" data-panel-link data-turbo="false">
            <x-nav-icon name="plus" /> {{ __('Add appointment') }}
        </a>
    </x-slot>

    <div class="casa-admin-dashboard">
        <section class="casa-dashboard-metrics casa-metric-grid" data-metric-grid aria-label="{{ __('Today’s activity') }}">
            @foreach ([
                ['label' => 'Confirmed visits', 'value' => $summary['confirmedToday'], 'icon' => 'calendar', 'meta' => 'Scheduled today'],
                ['label' => 'Paid revenue', 'value' => '₱'.number_format((float) $summary['todayRevenue'], 2), 'icon' => 'payments', 'meta' => 'Paid transactions today'],
                ['label' => 'New feedback', 'value' => $summary['newFeedback'], 'icon' => 'feedback', 'meta' => 'Submitted today'],
                ['label' => 'Upcoming visits', 'value' => $summary['upcomingAppointments'], 'icon' => 'customers', 'meta' => 'All future confirmed visits'],
            ] as $metric)
                <x-app-card class="casa-dashboard-metric" padding="" data-metric-card>
                    <span class="casa-dashboard-icon"><x-nav-icon :name="$metric['icon']" /></span>
                    <div class="min-w-0">
                        <p class="text-sm text-casa-text">{{ __($metric['label']) }}</p>
                        <p @class(['casa-dashboard-value', 'casa-dashboard-value--wide' => mb_strlen((string) $metric['value']) > 10])>{{ $metric['value'] }}</p>
                        <p data-metric-meta class="mt-1 hidden text-sm font-semibold leading-5 text-casa-muted sm:block">{{ __($metric['meta']) }}</p>
                    </div>
                </x-app-card>
            @endforeach
        </section>

        <section class="casa-dashboard-attention" aria-label="{{ __('Needs attention') }}">
            <h2 class="flex items-center gap-3 font-extrabold"><x-nav-icon name="errors" class="text-casa-cacao" /> {{ __('Needs attention') }}</h2>
            <a href="{{ route('admin.appointments.index') }}" class="casa-dashboard-attention-link">
                <x-nav-icon name="calendar" />
                <span>{{ $summary['overdueConfirmed'] ? trans_choice(':count overdue confirmed visit|:count overdue confirmed visits', $summary['overdueConfirmed']) : __('No overdue visits') }}</span>
                <span aria-hidden="true">→</span>
            </a>
            <a href="{{ route('admin.feedback.index') }}" class="casa-dashboard-attention-link">
                <x-nav-icon name="feedback" />
                <span>{{ trans_choice(':count new review today|:count new reviews today', $summary['newFeedback']) }}</span>
                <span aria-hidden="true">→</span>
            </a>
        </section>

        <div class="casa-dashboard-columns">
            <x-app-card class="casa-dashboard-upcoming">
                <div class="casa-dashboard-section-heading">
                    <h2>{{ __('Upcoming visits') }}</h2>
                    <a href="{{ route('admin.appointments.index') }}" class="casa-dashboard-link" aria-label="{{ __('View all appointments') }}">{{ __('View all') }} <span aria-hidden="true">→</span></a>
                </div>
                <x-table-shell :label="__('Upcoming confirmed visits table')" class="casa-dashboard-table">
                    <thead class="text-left">
                        <tr>
                            <th scope="col">{{ __('Time') }}</th>
                            <th scope="col">{{ __('Customer & service') }}</th>
                            <th scope="col">{{ __('Therapist') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col"><span class="sr-only">{{ __('Details') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-casa-border text-sm">
                        @forelse ($upcomingAppointments as $appointment)
                            <tr class="casa-table-row">
                                <td class="whitespace-nowrap align-top">
                                    <time datetime="{{ $appointment->scheduled_start_at->toIso8601String() }}">{{ $appointment->scheduled_start_at->timezone('Asia/Manila')->format('g:i A') }}</time>
                                    @if (! $appointment->scheduled_start_at->isSameDay($dashboardDate))
                                        <span class="mt-1 block text-casa-muted">{{ $appointment->scheduled_start_at->format('M j, Y') }}</span>
                                    @endif
                                </td>
                                <td class="casa-dashboard-customer">
                                    <p class="font-bold">{{ $appointment->customerProfile?->display_name ?? __('Customer') }}</p>
                                    <p class="mt-1 text-casa-muted">{{ $appointment->service?->name ?? __('Service') }}</p>
                                    <p class="mt-1 text-casa-muted">{{ $appointment->appointment_number }}</p>
                                </td>
                                <td>{{ $appointment->staffProfile?->user?->name ?: __('Unassigned') }}</td>
                                <td><x-status-badge tone="success">{{ __('Confirmed') }}</x-status-badge></td>
                                <td>
                                    <a href="{{ route('admin.appointments.show', $appointment) }}" class="casa-dashboard-detail" data-panel-link data-turbo="false" aria-label="{{ __('View appointment :number', ['number' => $appointment->appointment_number]) }}"><span aria-hidden="true">›</span></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="casa-dashboard-empty">{{ __('No upcoming confirmed visits') }}</div></td></tr>
                        @endforelse
                    </tbody>
                </x-table-shell>
            </x-app-card>

            <div class="casa-dashboard-aside">
                <x-app-card>
                    <div class="casa-dashboard-section-heading">
                        <h2>{{ __('Latest feedback') }}</h2>
                        <a href="{{ route('admin.feedback.index') }}" class="casa-dashboard-link" aria-label="{{ __('View all feedback') }}">{{ __('View all') }} <span aria-hidden="true">→</span></a>
                    </div>
                    @if ($latestFeedback)
                        @php
                            $sentimentTone = match ($latestFeedback->sentiment_label) {
                                'positive' => 'success',
                                'negative' => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <div class="casa-dashboard-feedback">
                            <x-status-badge :tone="$sentimentTone">{{ $latestFeedback->sentiment_label ? __(ucfirst($latestFeedback->sentiment_label)) : __('Not classified') }}</x-status-badge>
                            <p class="casa-dashboard-quote">{{ filled($latestFeedback->comment) ? \Illuminate\Support\Str::limit($latestFeedback->comment, 220) : __('No written comment provided.') }}</p>
                            <div class="flex flex-wrap items-center justify-between gap-x-3">
                                <p class="min-w-0 break-words text-sm text-casa-muted">{{ $latestFeedback->customerProfile?->display_name ?? __('Customer') }}</p>
                                <a href="{{ route('admin.feedback.show', $latestFeedback) }}" class="casa-dashboard-link">{{ __('Read feedback') }} <span aria-hidden="true">→</span></a>
                            </div>
                        </div>
                    @else
                        <p class="casa-dashboard-empty">{{ __('No feedback yet. Customer feedback will appear here after it is submitted.') }}</p>
                    @endif
                </x-app-card>

                <x-app-card>
                    <div class="casa-dashboard-section-heading"><h2>{{ __('Quick actions') }}</h2></div>
                    <nav aria-label="{{ __('Dashboard quick actions') }}">
                        <a href="{{ route('admin.appointments.index') }}" class="casa-dashboard-action"><span class="casa-dashboard-icon"><x-nav-icon name="calendar" /></span><span>{{ __('Appointments') }}</span><span aria-hidden="true">›</span></a>
                        <a href="{{ route('admin.promotions.index') }}" class="casa-dashboard-action"><span class="casa-dashboard-icon"><x-nav-icon name="services" /></span><span>{{ __('Customer rewards') }}<span class="block text-sm text-casa-muted">{{ trans_choice(':count customer reward available|:count customer rewards available', $summary['availableRewards']) }}</span></span><span aria-hidden="true">›</span></a>
                        <a href="{{ route('admin.reports.index') }}" class="casa-dashboard-action"><span class="casa-dashboard-icon"><x-nav-icon name="insights" /></span><span>{{ __('Reports') }}</span><span aria-hidden="true">›</span></a>
                    </nav>
                </x-app-card>
            </div>
        </div>

        <x-app-card>
            <figure aria-labelledby="weekly-visits-title">
                <figcaption>
                    <h2 id="weekly-visits-title" class="casa-dashboard-chart-title">{{ __('Visits this week') }}</h2>
                    <p class="text-sm text-casa-muted">{{ __('Confirmed and completed appointments') }} · {{ $dashboardDate->startOfWeek()->format('M j') }}–{{ $dashboardDate->endOfWeek()->format('M j, Y') }}</p>
                </figcaption>
                <div class="casa-dashboard-chart" aria-hidden="true">
                    @foreach ($weeklyVisits as $day)
                        <div class="casa-dashboard-chart-column">
                            <div class="casa-dashboard-chart-track">
                                <span class="casa-dashboard-chart-count" style="bottom: {{ $day['count'] / $weeklyMaximum * 75 }}%">{{ $day['count'] }}</span>
                                <span class="casa-dashboard-chart-bar" style="height: {{ $day['count'] / $weeklyMaximum * 75 }}%"></span>
                            </div>
                            <span class="casa-dashboard-chart-day">{{ $day['day'] }}</span>
                        </div>
                    @endforeach
                </div>
                <ul class="sr-only">
                    @foreach ($weeklyVisits as $day)
                        <li>{{ $day['label'] }}: {{ trans_choice(':count visit|:count visits', $day['count']) }}</li>
                    @endforeach
                </ul>
                @if ($weeklyVisits->sum('count') === 0)
                    <p class="mt-2 text-sm text-casa-muted">{{ __('No confirmed or completed visits this week.') }}</p>
                @endif
            </figure>
        </x-app-card>
    </div>
</x-app-layout>
