<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Therapist module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Feedback') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('View feedback related to your assigned appointments.') }}</p>
        </div>
    </x-slot>

    <x-app-card>
        <x-list-toolbar eyebrow="{{ __('Reviews') }}" title="{{ __('Related feedback') }}" :count="$feedback->total()" :reset-url="route('staff.feedback.index')" :active-filters="collect(request()->only(['q', 'sentiment_label']))->filter(fn ($value) => filled($value))->count()" :collapsible="true">
            <form method="GET" action="{{ route('staff.feedback.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(12rem,1fr)_auto_auto] lg:min-w-[42rem]">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search customer, service, comment') }}" aria-label="{{ __('Search feedback') }}">
                <select name="sentiment_label" class="casa-input">
                    <option value="">{{ __('All sentiment') }}</option>
                    @foreach (\App\Models\Feedback::SENTIMENT_LABELS as $option)
                        <option value="{{ $option }}" @selected($sentiment === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
            </form>
        </x-list-toolbar>

        <div class="mt-5">
        @if ($feedback->isEmpty())
            <x-empty-state title="{{ __('No related feedback yet') }}" description="{{ __('Customer reviews for your completed appointments will appear here.') }}" />
        @else
            <x-table-shell>
                <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                    <tr>
                        <x-sortable-th sort="customer">{{ __('Customer') }}</x-sortable-th>
                        <x-sortable-th sort="service">{{ __('Service') }}</x-sortable-th>
                        <x-sortable-th sort="rating">{{ __('Rating') }}</x-sortable-th>
                        <x-sortable-th sort="sentiment">{{ __('Sentiment') }}</x-sortable-th>
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
                                        @if ($item->topics->isNotEmpty())
                                            <div class="mt-2 flex flex-wrap gap-1 text-[0.65rem] text-casa-muted">
                                                @foreach ($item->topics as $topic)
                                                    <span class="rounded-full bg-casa-bg px-2 py-1">{{ str($topic->topic_key)->replace('_', ' ')->title() }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                            <td class="px-4 py-4"><a href="{{ route('staff.feedback.show', $item) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table-shell>
            <div class="mt-5">{{ $feedback->links() }}</div>
        @endif
        </div>
    </x-app-card>
</x-app-layout>
