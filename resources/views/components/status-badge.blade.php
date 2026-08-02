@props(['tone' => 'neutral'])

@php
    $classes = match ($tone) {
        'success' => 'border-casa-green/30 bg-casa-green/15 text-casa-green',
        'warning' => 'border-casa-brass/40 bg-casa-brass/15 text-casa-cacao',
        'danger' => 'border-red-200 bg-red-50 text-red-700',
        'dark' => 'border-casa-charcoal/20 bg-casa-charcoal/10 text-casa-charcoal',
        default => 'border-casa-border bg-casa-sand/55 text-casa-muted',
    };
@endphp

<span {{ $attributes->merge(['class' => 'casa-badge inline-flex items-center gap-1.5 '.$classes]) }}>
    @if ($tone === 'success')
        <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
    @elseif ($tone === 'warning')
        <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
    @elseif ($tone === 'danger')
        <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
    @endif
    <span>{{ $slot }}</span>
</span>
