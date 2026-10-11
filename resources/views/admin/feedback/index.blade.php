<x-app-layout>
    <x-slot name="header">
        <div class="feedback-heading">
            <h1>{{ __('Feedback') }}</h1>
            <p>{{ __('Customer feedback and experience insights') }}</p>
        </div>
    </x-slot>

    @php
        $activeFilters = collect([$search, $serviceId, $sentiment, $dateFrom, $dateTo, $topic])->filter(fn ($value) => filled($value))->count();
        $moreFiltersActive = filled($dateFrom) || filled($dateTo) || filled($topic);
        $filterValues = collect(['q' => $search, 'service_id' => $serviceId, 'sentiment_label' => $sentiment, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'topic' => $topic])
            ->map(function ($value, $key) {
                $oldValue = old($key, $value);

                return is_scalar($oldValue) ? (string) $oldValue : '';
            });
    @endphp

    <div class="feedback-workspace" data-feedback-workspace>
        <section class="feedback-metrics" data-metric-grid aria-label="{{ __('Filtered sentiment totals') }}">
            @foreach (['positive' => __('Positive'), 'neutral' => __('Neutral'), 'negative' => __('Negative')] as $key => $label)
                <article class="feedback-metric" data-metric-card aria-labelledby="feedback-metric-{{ $key }}">
                    <span class="feedback-metric-icon"><x-feedback-icon :name="$key" /></span>
                    <div>
                        <h2 id="feedback-metric-{{ $key }}">{{ $label }}</h2>
                        <p class="feedback-metric-value" data-feedback-total="{{ $key }}">{{ $summary[$key] }}</p>
                        <p class="sr-only" data-metric-meta>{{ __('Matching reviews across all pages') }}</p>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="feedback-filters" aria-label="{{ __('Filter customer feedback') }}">
            <x-list-toolbar :active-filters="$activeFilters" :collapsible="true" class="feedback-toolbar casa-list-toolbar--filters" x-init="{{ $errors->any() ? 'filtersOpen = true' : 'null' }}">
                <form method="GET" action="{{ route('admin.feedback.index') }}" class="feedback-filter-form" x-data>
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <div class="feedback-filter-field">
                        <label for="feedback-search">{{ __('Search feedback') }}</label>
                        <div class="feedback-search">
                            <input id="feedback-search" type="search" name="q" value="{{ $filterValues['q'] }}" maxlength="255" class="casa-input" placeholder="{{ __('Name, email, service, or comment…') }}">
                            <button type="submit" aria-label="{{ __('Search feedback') }}"><x-feedback-icon name="search" /></button>
                        </div>
                        <x-input-error :messages="$errors->get('q')" />
                    </div>
                    <div class="feedback-filter-field">
                        <label for="feedback-service">{{ __('Service') }}</label>
                        <select id="feedback-service" name="service_id" class="casa-input" x-on:change="$el.form.requestSubmit()">
                            <option value="">{{ __('All services') }}</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected($filterValues['service_id'] === (string) $service->id)>{{ $service->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('service_id')" />
                    </div>
                    <div class="feedback-filter-field">
                        <label for="feedback-sentiment">{{ __('Sentiment') }}</label>
                        <select id="feedback-sentiment" name="sentiment_label" class="casa-input" x-on:change="$el.form.requestSubmit()">
                            <option value="">{{ __('All sentiments') }}</option>
                            @foreach (\App\Models\Feedback::SENTIMENT_LABELS as $option)
                                <option value="{{ $option }}" @selected($filterValues['sentiment_label'] === $option)>{{ ucfirst($option) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('sentiment_label')" />
                    </div>
                    <div class="feedback-filter-actions">
                        @if ($activeFilters > 0)
                            <a href="{{ route('admin.feedback.index') }}" class="casa-button-primary feedback-clear">{{ __('Clear filters') }}</a>
                        @endif
                        <noscript><button type="submit" class="casa-button-secondary">{{ __('Apply filters') }}</button></noscript>
                    </div>

                    <details class="feedback-more-filters" @if ($moreFiltersActive || $errors->hasAny(['date_from', 'date_to', 'topic'])) open @endif>
                        <summary>{{ __('More filters') }}@if ($moreFiltersActive)<span class="feedback-filter-dot" aria-label="{{ __('Additional filters active') }}"></span>@endif</summary>
                        <div class="feedback-more-fields">
                            <div class="feedback-filter-field">
                                <label for="feedback-date-from">{{ __('Submitted from') }}</label>
                                <input id="feedback-date-from" type="date" name="date_from" value="{{ $filterValues['date_from'] }}" class="casa-input" aria-describedby="feedback-date-help">
                                <x-input-error :messages="$errors->get('date_from')" />
                            </div>
                            <div class="feedback-filter-field">
                                <label for="feedback-date-to">{{ __('Submitted to') }}</label>
                                <input id="feedback-date-to" type="date" name="date_to" value="{{ $filterValues['date_to'] }}" class="casa-input" aria-describedby="feedback-date-help">
                                <x-input-error :messages="$errors->get('date_to')" />
                            </div>
                            <div class="feedback-filter-field">
                                <label for="feedback-topic">{{ __('Experience topic') }}</label>
                                <select id="feedback-topic" name="topic" class="casa-input" x-on:change="$el.form.requestSubmit()">
                                    <option value="">{{ __('All topics') }}</option>
                                    @foreach (config('sentiment.topics', []) as $key => $definition)
                                        <option value="{{ $key }}" @selected($filterValues['topic'] === $key)>{{ $definition['label'] }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('topic')" />
                            </div>
                            <div class="feedback-more-footer">
                                <span id="feedback-date-help">{{ $dateFrom === '' && $dateTo === '' ? __('All dates · Asia/Manila') : __('Asia/Manila submission dates') }}</span>
                                <button type="submit" class="casa-button-secondary">{{ __('Apply filters') }}</button>
                            </div>
                        </div>
                    </details>
                </form>
            </x-list-toolbar>
            <noscript><style>.feedback-toolbar [data-filter-panel] { display: block; } .feedback-toolbar [data-filter-toggle] { display: none; }</style></noscript>
        </section>

        <section class="feedback-insights" aria-label="{{ __('Feedback insights') }}">
            <section class="feedback-panel" aria-labelledby="feedback-service-heading">
                <h2 id="feedback-service-heading">{{ __('Service sentiment summary') }}</h2>
                @if ($serviceBreakdown->isEmpty())
                    <p class="feedback-panel-empty">{{ __('No service feedback for the selected filters.') }}</p>
                @else
                    <x-table-shell class="feedback-service-table" label="{{ __('Scrollable service sentiment summary') }}">
                        <thead>
                            <tr>
                                @foreach ([__('Service'), __('Positive'), __('Neutral'), __('Negative'), __('Total')] as $label)
                                    <th scope="col">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($serviceBreakdown as $row)
                                <tr>
                                    <th scope="row">{{ $row->service_name ?: __('Unassigned service') }}</th>
                                    @foreach (['positive', 'neutral', 'negative'] as $label)
                                        <td><span class="feedback-count feedback-sentiment--{{ $label }}">{{ $row->{$label} }}</span></td>
                                    @endforeach
                                    <td class="feedback-service-total">{{ $row->total }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                @endif
            </section>
            <section class="feedback-panel" aria-labelledby="feedback-topic-heading">
                <h2 id="feedback-topic-heading">{{ __('Experience topics') }}</h2>
                @if ($topicBreakdown->isEmpty())
                    <p class="feedback-panel-empty">{{ __('No topic findings for the selected filters.') }}</p>
                @else
                    <div class="feedback-topics">
                        @foreach ($topicBreakdown as $row)
                            @php
                                $percentage = (int) round((int) $row->positive / (int) $row->total * 100);
                                $topicLabel = config('sentiment.topics.'.$row->topic_key.'.label', str($row->topic_key)->replace('_', ' ')->title()->toString());
                            @endphp
                            <div class="feedback-topic" data-feedback-topic="{{ $row->topic_key }}">
                                <div class="feedback-topic-label"><span>{{ $topicLabel }}</span><span>{{ __(':positive of :total positive (:percentage%)', ['positive' => $row->positive, 'total' => $row->total, 'percentage' => $percentage]) }}</span></div>
                                <meter min="0" max="{{ $row->total }}" value="{{ $row->positive }}" aria-label="{{ __('Positive findings for :topic', ['topic' => $topicLabel]) }}">{{ $percentage }}%</meter>
                            </div>
                        @endforeach
                    </div>
                    <p class="feedback-topic-help">{{ __('Positive findings divided by all findings for each topic. A review may have more than one finding.') }}</p>
                @endif
            </section>
        </section>

        <section class="feedback-panel feedback-reviews" aria-labelledby="feedback-reviews-heading">
            <h2 id="feedback-reviews-heading">{{ __('Customer reviews (:count)', ['count' => $feedback->total()]) }}</h2>
            @if ($feedback->isEmpty())
                <x-empty-state :title="$activeFilters > 0 ? __('No feedback found') : __('No feedback yet')" :description="$activeFilters > 0 ? __('Try another search or clear the filters to find a review.') : __('Customer feedback appears after completed appointments.')">
                    @if ($activeFilters > 0)
                        <x-slot name="action"><a href="{{ route('admin.feedback.index') }}" class="casa-button-secondary">{{ __('Clear filters') }}</a></x-slot>
                    @endif
                </x-empty-state>
            @else
                <x-table-shell class="feedback-review-table" label="{{ __('Scrollable customer reviews') }}">
                    <thead>
                        <tr>
                            <x-sortable-th sort="customer" scope="col">{{ __('Customer') }}</x-sortable-th>
                            <x-sortable-th sort="service" scope="col">{{ __('Treatment / Service') }}</x-sortable-th>
                            <x-sortable-th sort="submitted" scope="col">{{ __('Date') }}</x-sortable-th>
                            <x-sortable-th sort="sentiment" scope="col">{{ __('Sentiment') }}</x-sortable-th>
                            <th scope="col">{{ __('Review') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($feedback as $item)
                            @php
                                $customer = $item->customerProfile;
                                $contact = $customer?->display_email ?: ($customer?->is_guest ? $customer->display_phone : null);
                                $submitted = $item->submitted_at?->timezone('Asia/Manila');
                                $comment = trim((string) $item->comment);
                            @endphp
                            <tr>
                                <td class="feedback-customer">
                                    <a href="{{ route('admin.feedback.show', $item) }}" aria-label="{{ __('View feedback from :customer', ['customer' => $customer?->display_name ?: __('Customer unavailable')]) }}">{{ $customer?->display_name ?: __('Customer unavailable') }}</a>
                                    @if (filled($contact))<p>{{ $contact }}</p>@endif
                                </td>
                                <td class="feedback-service">{{ $item->service?->name ?: __('Unassigned service') }}</td>
                                <td class="feedback-date">@if ($submitted)<time datetime="{{ $submitted->toIso8601String() }}">{{ $submitted->format('M j, Y') }}</time>@else{{ __('Not recorded') }}@endif</td>
                                <td><span class="feedback-sentiment feedback-sentiment--{{ $item->sentiment_label }}">{{ ucfirst($item->sentiment_label) }}</span></td>
                                <td class="feedback-comment">
                                    <p>{{ $comment !== '' ? (mb_strlen($comment) > 180 ? mb_substr($comment, 0, 180).'…' : $comment) : __('No written comment.') }}</p>
                                    @if (mb_strlen($comment) > 180)<a href="{{ route('admin.feedback.show', $item) }}">{{ __('Read full review') }}</a>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table-shell>
                <div class="feedback-pagination">{{ $feedback->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
