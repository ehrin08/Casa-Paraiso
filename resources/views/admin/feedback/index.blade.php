<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Feedback') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Review ratings, comments, and simple sentiment labels from completed visits.') }}
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="casa-metric-grid grid gap-3 sm:gap-4 md:grid-cols-3" data-metric-grid>
            <x-metric-card label="Positive" :value="$overview['positive']" :meta="$overview['positive_rate'].'% of period'" tone="green" />
            <x-metric-card label="Neutral" :value="$overview['neutral']" meta="Middle rating" tone="gold" />
            <x-metric-card label="Negative" :value="$overview['negative']" :meta="$overview['negative_rate'].'% · needs attention'" tone="charcoal" />
        </section>

        <section class="grid gap-4 lg:grid-cols-2" aria-label="{{ __('Feedback breakdowns') }}">
            <x-app-card>
                <h2 class="text-lg font-black text-casa-text">{{ __('Needs attention by service') }}</h2>
                <div class="mt-4 space-y-2">
                    @forelse ($overview['service_breakdown'] as $row)
                        <div class="flex items-center justify-between text-sm"><span>{{ $row->service?->name ?? __('Unassigned service') }}</span><span class="font-bold text-casa-muted">{{ $row->negative }} negative / {{ $row->total }}</span></div>
                    @empty
                        <p class="text-sm text-casa-muted">{{ __('No service breakdown for this period.') }}</p>
                    @endforelse
                </div>
            </x-app-card>
            <x-app-card>
                <h2 class="text-lg font-black text-casa-text">{{ __('Experience topics') }}</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    @forelse ($overview['topic_breakdown'] as $row)
                        <span class="rounded-full bg-casa-bg px-3 py-2 text-xs font-bold text-casa-muted">{{ str($row->topic_key)->replace('_', ' ')->title() }} · {{ $row->negative }} negative</span>
                    @empty
                        <p class="text-sm text-casa-muted">{{ __('No topic findings for this period.') }}</p>
                    @endforelse
                </div>
            </x-app-card>
        </section>

        <x-app-card>
            <x-list-toolbar eyebrow="{{ __('Reviews') }}" title="{{ __('Customer feedback') }}" :count="$feedback->total()" :reset-url="route('admin.feedback.index')" :active-filters="collect(request()->only(['q', 'sentiment_label', 'date_from', 'date_to', 'service_id', 'topic']))->filter(fn ($value) => filled($value))->count()" :collapsible="true">
                <form method="GET" action="{{ route('admin.feedback.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(12rem,1fr)_auto_auto] lg:min-w-[42rem]">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search customer, service, comment') }}" aria-label="{{ __('Search feedback') }}">
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="casa-input" aria-label="{{ __('From date') }}">
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="casa-input" aria-label="{{ __('To date') }}">
                    <select name="sentiment_label" class="casa-input">
                        <option value="">{{ __('All sentiment') }}</option>
                        @foreach (\App\Models\Feedback::SENTIMENT_LABELS as $option)
                            <option value="{{ $option }}" @selected($sentiment === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                    <select name="service_id" class="casa-input" aria-label="{{ __('Filter by service') }}">
                        <option value="">{{ __('All services') }}</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((string) $serviceId === (string) $service->id)>{{ $service->name }}</option>
                        @endforeach
                    </select>
                    <select name="topic" class="casa-input" aria-label="{{ __('Filter by topic') }}">
                        <option value="">{{ __('All topics') }}</option>
                        @foreach (array_keys(config('sentiment.topics', [])) as $option)
                            <option value="{{ $option }}" @selected($topic === $option)>{{ str($option)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                </form>
            </x-list-toolbar>

            <div class="mt-5">
                @if ($feedback->isEmpty())
                    <x-empty-state title="{{ __('No feedback yet') }}" description="{{ __('Customer feedback appears after completed appointments.') }}" />
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <x-sortable-th sort="customer">{{ __('Customer') }}</x-sortable-th>
                                <x-sortable-th sort="service">{{ __('Service') }}</x-sortable-th>
                                <x-sortable-th sort="rating">{{ __('Rating') }}</x-sortable-th>
                                <x-sortable-th sort="sentiment">{{ __('Sentiment') }}</x-sortable-th>
                                <x-sortable-th sort="submitted">{{ __('Submitted') }}</x-sortable-th>
                                <th class="px-4 py-3">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($feedback as $item)
                                <tr class="casa-table-row">
                                    <td class="px-4 py-4 font-semibold text-casa-text">{{ $item->customerProfile?->user?->name }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $item->service?->name }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $item->rating }}/5</td>
                                    <td class="px-4 py-4">
                                        <x-status-badge>{{ ucfirst($item->sentiment_label) }}</x-status-badge>
                                        <span class="mt-1 block text-[0.65rem] text-casa-muted">{{ ucfirst($item->sentiment_source ?? 'rules') }}{{ $item->sentiment_confidence !== null ? ' · '.number_format((float) $item->sentiment_confidence * 100, 0).'% confidence' : '' }}</span>
                                        @if ($item->topics->isNotEmpty())
                                            <div class="mt-2 flex flex-wrap gap-1 text-[0.65rem] text-casa-muted">
                                                @foreach ($item->topics as $topic)
                                                    <span class="rounded-full bg-casa-bg px-2 py-1">{{ str($topic->topic_key)->replace('_', ' ')->title() }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $item->submitted_at?->format('M d, Y') }}</td>
                                    <td class="px-4 py-4"><a href="{{ route('admin.feedback.show', $item) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                    <div class="mt-5">{{ $feedback->links() }}</div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
