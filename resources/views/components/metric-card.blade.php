@props([
    'label',
    'value',
    'meta' => null,
    'tone' => 'brown',
])

@php
    $accent = match ($tone) {
        'green' => 'bg-casa-green',
        'gold' => 'bg-casa-brass',
        'charcoal' => 'bg-casa-charcoal',
        default => 'bg-casa-cacao',
    };
@endphp

<div data-metric-card {{ $attributes->merge(['class' => 'casa-card-compact casa-metric-card relative min-w-0 overflow-hidden p-3 sm:p-4']) }}>
    <span class="absolute inset-y-0 start-0 w-1 {{ $accent }}"></span>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-extrabold uppercase tracking-[0.06em] text-casa-muted">{{ $label }}</p>
            <p class="mt-1 break-words text-lg font-extrabold leading-tight tracking-tight text-casa-text sm:mt-2 sm:text-2xl">{{ $value }}</p>
        </div>
        <span class="mt-1 hidden size-2.5 shrink-0 rounded-full {{ $accent }} shadow-sm sm:block"></span>
    </div>

    @if ($meta)
        <p data-metric-meta class="mt-2 hidden text-sm font-semibold leading-5 text-casa-muted sm:block">{{ $meta }}</p>
    @endif
</div>
