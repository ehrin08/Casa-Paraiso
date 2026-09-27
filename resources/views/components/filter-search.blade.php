@props(['name' => 'q', 'value' => '', 'placeholder', 'label', 'inputId' => null])

<div {{ $attributes->class(['casa-filter-search']) }}>
    <svg class="casa-filter-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <circle cx="10.75" cy="10.75" r="6.75" />
        <path stroke-linecap="round" d="m16 16 4.25 4.25" />
    </svg>
    <input type="search" @if ($inputId) id="{{ $inputId }}" @endif name="{{ $name }}" value="{{ $value }}" class="casa-input" placeholder="{{ $placeholder }}" aria-label="{{ $label }}">
</div>
