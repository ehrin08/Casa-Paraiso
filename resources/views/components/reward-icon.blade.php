@props(['name'])

@php
    $paths = match ($name) {
        'gift' => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v9h14v-9M12 8v13M12 8H8a3 3 0 1 1 3-3l1 3Zm0 0h4a3 3 0 1 0-3-3l-1 3Z"/>',
        'bookmark' => '<path d="M6 21V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v17l-6-4-6 4Z"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m7 12 3 3 7-7"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>',
        'settings' => '<path d="m9 3 .6-2h4.8l.6 2 2 .9 2-.5 2.4 4.2-1.4 1.5v2.8l1.4 1.5-2.4 4.2-2-.5-2 .9-.6 2H9.6L9 18l-2-.9-2 .5-2.4-4.2L4 11.9V9.1L2.6 7.6 5 3.4l2 .5 2-.9Z" transform="translate(0 1.5)"/><circle cx="12" cy="12" r="3"/>',
        'pencil' => '<path d="m15 5 4 4M4 20l5-1L20 8a2.8 2.8 0 0 0-4-4L5 15l-1 5Z"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'search' => '<circle cx="10.75" cy="10.75" r="6.75"/><path d="m16 16 4.25 4.25"/>',
        default => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>',
    };
@endphp

<svg {{ $attributes->merge(['class' => 'rewards-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $paths !!}</svg>
