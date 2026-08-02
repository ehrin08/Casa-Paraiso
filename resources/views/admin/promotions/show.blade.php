<x-app-layout>
    <x-slot name="header">
        <x-page-heading>
            <div>
                <p class="casa-section-label">{{ __('Customer reward') }}</p>
                <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $suggestion->customerProfile?->user?->name }}</h1>
                <p class="mt-2 text-sm text-casa-muted">{{ $suggestion->rfmSegment?->name ?: __('Previous reward rule') }}</p>
            </div>
            <a href="{{ route('admin.promotions.index') }}" class="casa-button-secondary">{{ __('All customer rewards') }}</a>
        </x-page-heading>
    </x-slot>

    @php $state = $suggestion->lifecycle(); @endphp
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <x-app-card>
                <x-stat-strip :items="[
                    ['label' => __('Days since last paid visit'), 'value' => $suggestion->recency_days ?? __('N/A'), 'meta' => __('At time of issue'), 'tone' => 'gold'],
                    ['label' => __('Paid visits'), 'value' => $suggestion->frequency_count ?? 0, 'meta' => __('At time of issue'), 'tone' => 'green'],
                    ['label' => __('Paid total'), 'value' => 'PHP '.number_format((float) $suggestion->monetary_total, 2), 'meta' => __('At time of issue'), 'tone' => 'brown'],
                ]" />
                <div class="mt-6 rounded-2xl bg-casa-bg p-5">
                    <p class="casa-section-label">{{ __('Complimentary add-on') }}</p>
                    <p class="mt-3 text-lg font-bold text-casa-text">{{ $suggestion->addonName() ?: $suggestion->suggested_offer }}</p>
                    @if ($suggestion->notes)
                        <p class="mt-4 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $suggestion->notes }}</p>
                    @endif
                </div>
            </x-app-card>
        </section>

        <aside class="space-y-4">
            <x-app-card>
                <p class="casa-section-label">{{ __('Reward status') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ ucfirst($state) }}</h2>
                <div class="mt-4 space-y-2 text-sm text-casa-muted">
                    <p>{{ __('Issued') }}: {{ $suggestion->created_at?->format('M d, Y g:i A') }}</p>
                    <p>{{ __('Expires') }}: {{ $suggestion->expires_at?->format('M d, Y g:i A') ?: __('No expiration') }}</p>
                    <p>{{ __('Reserved or used') }}: {{ $suggestion->applied_at?->format('M d, Y g:i A') ?: __('No') }}</p>
                    <p>{{ __('Dismissed') }}: {{ $suggestion->dismissed_at?->format('M d, Y g:i A') ?: __('No') }}</p>
                </div>
            </x-app-card>
        </aside>
    </div>
</x-app-layout>
