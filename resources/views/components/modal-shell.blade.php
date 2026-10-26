@props([
    'title',
    'eyebrow' => null,
    'titleId' => null,
    'closeModal' => null,
])

{{-- Shared dialog layout: title bar, scrolling body and an action footer, matching the workspace panel. --}}
<div {{ $attributes->merge(['class' => 'casa-modal-shell']) }}>
    <div class="casa-modal-shell__header">
        <div class="min-w-0">
            @if ($eyebrow)
                <p class="casa-section-label">{{ $eyebrow }}</p>
            @endif
            <h2 @if ($titleId) id="{{ $titleId }}" @endif class="font-display text-xl font-black text-casa-text">{{ $title }}</h2>
        </div>
        @if ($closeModal)
            <button type="button" class="casa-icon-button" aria-label="{{ __('Close dialog') }}" x-on:click="$dispatch('close-modal', '{{ $closeModal }}')">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        @endif
    </div>

    <div class="casa-modal-shell__body">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="casa-modal-shell__footer" data-modal-actions>
            {{ $footer }}
        </div>
    @endisset
</div>
