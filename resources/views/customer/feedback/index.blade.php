<x-app-layout>
    @php $submitFeedbackModal = 'customer-feedback-submit'; @endphp
    <x-slot name="header">
        <div>
            <p class="casa-eyebrow">{{ __('Customer lounge') }}</p>
            <h1 class="mt-3 font-editorial text-4xl font-semibold text-casa-text">{{ __('Feedback') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Share how your completed visit felt and revisit the care notes you have already sent.') }}</p>
        </div>
        <button type="button" class="casa-button-primary" x-data="" x-on:click="$dispatch('open-modal', '{{ $submitFeedbackModal }}')">
            <x-nav-icon name="feedback" class="size-4" />
            {{ __('Share feedback') }}
        </button>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section>
            <x-app-card>
                <x-list-toolbar eyebrow="{{ __('My reviews') }}" title="{{ __('Feedback history') }}" :count="$feedback->total()" :reset-url="route('customer.feedback.index')" :active-filters="collect(request()->only(['q', 'sentiment_label']))->filter(fn ($value) => filled($value))->count()" :collapsible="true">
                    <form method="GET" action="{{ route('customer.feedback.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(10rem,1fr)_auto_auto]">
                        <input type="hidden" name="sort" value="{{ $sort }}">
                        <input type="hidden" name="direction" value="{{ $direction }}">
                        <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search service or comment') }}" aria-label="{{ __('Search feedback') }}">
                        <select name="sentiment_label" class="casa-input" aria-label="{{ __('Filter by sentiment') }}">
                            <option value="">{{ __('All sentiment') }}</option>
                            @foreach (\App\Models\Feedback::SENTIMENT_LABELS as $option)
                                <option value="{{ $option }}" @selected($sentiment === $option)>{{ ucfirst($option) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                    </form>
                </x-list-toolbar>

                <div class="mt-5 space-y-3">
                    @forelse ($feedback as $item)
                        @php
                            $tone = match ($item->sentiment_label) {
                                \App\Models\Feedback::SENTIMENT_POSITIVE => 'success',
                                \App\Models\Feedback::SENTIMENT_NEGATIVE => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <article class="rounded-2xl border border-casa-border bg-casa-paper p-5">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-status-badge :tone="$tone">{{ ucfirst($item->sentiment_label) }}</x-status-badge>
                                        <span class="text-xs font-bold text-casa-muted">{{ $item->submitted_at?->format('M d, Y') }}</span>
                                    </div>
                                    <h2 class="mt-3 text-lg font-extrabold text-casa-text">{{ $item->service?->name ?? __('Spa service') }}</h2>
                                    @if ($item->comment)
                                        <p class="mt-2 text-sm leading-7 text-casa-muted">“{{ $item->comment }}”</p>
                                    @endif
                                </div>
                                <div class="flex shrink-0 items-center gap-1 rounded-full bg-casa-brass/12 px-3 py-2 text-casa-cacao" aria-label="{{ trans_choice(':count star|:count stars', $item->rating) }}">
                                    <svg class="size-4 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2.8 2.77 5.61 6.2.9-4.49 4.37 1.06 6.18L12 16.95l-5.54 2.91 1.06-6.18-4.49-4.37 6.2-.9L12 2.8Z"/></svg>
                                    <span class="text-sm font-extrabold">{{ $item->rating }}/5</span>
                                </div>
                            </div>
                        </article>
                    @empty
                        <x-empty-state title="{{ __('No feedback found') }}" description="{{ __('Completed appointments waiting for a review will appear beside your feedback history.') }}" />
                    @endforelse
                </div>

                @if ($feedback->isNotEmpty())
                    <div class="mt-5">{{ $feedback->links() }}</div>
                @endif
            </x-app-card>
        </section>

        <aside class="space-y-4">
            <x-app-card class="lg:sticky lg:top-6">
                <p class="casa-eyebrow">{{ __('Ready to review') }}</p>
                <h2 class="mt-4 font-editorial text-3xl font-semibold text-casa-text">{{ __('Completed visits') }}</h2>
                <p class="mt-2 text-sm leading-6 text-casa-muted">{{ __('A short, honest note helps the team understand what felt good and what could feel better.') }}</p>

                <div class="mt-5 space-y-3">
                    @forelse ($completedAppointments->whereNull('feedback') as $appointment)
                        <button type="button" class="block min-h-11 w-full rounded-2xl border border-casa-border bg-casa-sand/45 p-4 text-left transition hover:border-casa-brass hover:bg-casa-paper" x-data="" x-on:click="$dispatch('open-modal', '{{ $submitFeedbackModal }}')">
                            <span class="block text-sm font-extrabold text-casa-text">{{ $appointment->service?->name }}</span>
                            <span class="mt-1 block text-xs text-casa-muted">{{ $appointment->completed_at?->format('M d, Y') }}</span>
                        </button>
                    @empty
                        <p class="rounded-2xl border border-dashed border-casa-border p-4 text-sm leading-6 text-casa-muted">{{ __('No completed appointments are waiting for feedback.') }}</p>
                    @endforelse
                </div>
            </x-app-card>
        </aside>
    </div>

    <x-modal :name="$submitFeedbackModal" :show="old('_modal') === $submitFeedbackModal" maxWidth="4xl" focusable><div class="p-5 sm:p-6">@include('customer.feedback.partials.form', ['modalName' => $submitFeedbackModal])</div></x-modal>
</x-app-layout>
