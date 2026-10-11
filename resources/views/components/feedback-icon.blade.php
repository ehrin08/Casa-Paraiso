@props(['name'])

<svg {{ $attributes->merge(['class' => 'feedback-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @if ($name === 'search')
        <circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 4 4" />
    @else
        <circle cx="12" cy="12" r="9" />
        <circle cx="9" cy="9" r="0.9" fill="currentColor" stroke="none" /><circle cx="15" cy="9" r="0.9" fill="currentColor" stroke="none" />
        @switch($name)
            @case('positive')<path d="M8 14c1.5 3 6.5 3 8 0" />@break
            @case('neutral')<path d="M8.5 15h7" />@break
            @case('negative')<path d="M8 16c1.5-3 6.5-3 8 0" />@break
        @endswitch
    @endif
</svg>
