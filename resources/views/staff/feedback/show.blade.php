<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Feedback detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $feedback->customerProfile?->user?->name }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $feedback->service?->name }}</p>
        </div>
        <a href="{{ route('staff.feedback.index') }}" class="casa-button-secondary">{{ __('All feedback') }}</a>
    </x-slot>

    <x-app-card>
        <x-stat-strip :items="[
            ['label' => __('Rating'), 'value' => $feedback->rating.'/5', 'meta' => __('Customer score'), 'tone' => 'gold'],
            ['label' => __('Sentiment'), 'value' => ucfirst($feedback->sentiment_label), 'meta' => __('Analysis ').($feedback->sentiment_analysis_version ?? '1.0'), 'tone' => 'green'],
        ]" />
        <p class="mt-6 whitespace-pre-line rounded-2xl bg-casa-bg p-5 text-sm leading-7 text-casa-muted">{{ $feedback->comment ?: __('No written comment.') }}</p>
        @if ($feedback->topics->isNotEmpty())
            <div class="mt-5 flex flex-wrap gap-2">
                @foreach ($feedback->topics as $topic)
                    <span class="rounded-full bg-casa-bg px-3 py-2 text-xs font-bold text-casa-muted">{{ str($topic->topic_key)->replace('_', ' ')->title() }}</span>
                @endforeach
            </div>
        @endif
    </x-app-card>
</x-app-layout>
