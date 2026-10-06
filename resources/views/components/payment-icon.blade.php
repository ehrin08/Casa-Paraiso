@props(['name'])

@php
    $paths = match ($name) {
        'plus' => '<path d="M12 4v16M4 12h16"/>',
        'download' => '<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>',
        'search' => '<circle cx="10.75" cy="10.75" r="6.75"/><path d="m16 16 4.25 4.25"/>',
        'coins' => '<ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v14c0 1.7 3.1 3 7 3s7-1.3 7-3V5M5 10c0 1.7 3.1 3 7 3s7-1.3 7-3M5 15c0 1.7 3.1 3 7 3s7-1.3 7-3"/>',
        'cash' => '<rect x="3" y="6" width="18" height="12" rx="1"/><circle cx="12" cy="12" r="2.5"/><path d="M3 10a4 4 0 0 0 4-4m10 0a4 4 0 0 0 4 4M3 14a4 4 0 0 1 4 4m10 0a4 4 0 0 1 4-4"/>',
        'phone' => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/>',
        'bank' => '<path d="m3 8 9-5 9 5H3Zm0 13h18M5 11v7m5-7v7m4-7v7m5-7v7"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'pencil' => '<path d="m15 5 4 4M4 20l5-1L20 8a2.8 2.8 0 0 0-4-4L5 15l-1 5Z"/>',
        default => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20M6 15h2"/>',
    };
@endphp

<svg {{ $attributes->merge(['class' => 'payments-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $paths !!}</svg>
