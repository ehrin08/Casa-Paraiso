@props(['name'])

@php
    $paths = match ($name) {
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/>',
        'moon' => '<path d="M20 14.2A8.7 8.7 0 0 1 9.8 4a8.7 8.7 0 1 0 10.2 10.2Z"/>',
        'leaf' => '<path d="M19.5 4.5C10 3.5 4.5 7.5 5 14c.3 3.7 4 5.7 7.5 3.5 5-3 6.5-8 7-13Z"/><path d="m4 20 11-11M9 15v-5m0 5h5"/>',
        'arrow' => '<path d="M4 12h15m-5-5 5 5-5 5"/>',
        'pin' => '<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'message' => '<path d="M20 11.5a8 8 0 0 1-8 8 8.5 8.5 0 0 1-3.3-.7L4 20l1.2-4.7A8.5 8.5 0 0 1 4 11.5a8 8 0 0 1 16 0Z"/>',
        'facebook' => '<path d="M14.5 21v-8h3l.5-4h-3.5V7c0-1.2.5-2 2-2H18V1.5a19 19 0 0 0-2.7-.2c-3.3 0-5.3 2-5.3 5.6V9H7v4h3v8"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        default => '<circle cx="12" cy="8" r="3.5"/><path d="M5 21v-2a7 7 0 0 1 14 0v2Z"/>',
    };
@endphp

<svg {{ $attributes->merge(['class' => 'landing-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {!! $paths !!}
</svg>
