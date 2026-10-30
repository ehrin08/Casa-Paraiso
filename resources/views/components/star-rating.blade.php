@props(['value'])

@php $rating = filled($value) ? max(0, min(5, (int) $value)) : null; @endphp

@if ($rating === null)
    <span {{ $attributes }}>&mdash;</span>
@else
    <span {{ $attributes->merge(['class' => 'casa-star-display']) }} role="img" aria-label="{{ __(':count out of 5 stars', ['count' => $rating]) }}">
        @for ($star = 1; $star <= 5; $star++)
            <svg @class(['is-filled' => $star <= $rating]) viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2.8 2.77 5.61 6.2.9-4.49 4.37 1.06 6.18L12 16.95l-5.54 2.91 1.06-6.18-4.49-4.37 6.2-.9L12 2.8Z"/></svg>
        @endfor
    </span>
@endif
