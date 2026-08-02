@props(['name'])

@php
    $paths = match ($name) {
        'dashboard' => '<path d="M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z"/>',
        'calendar' => '<path d="M7 3v3m10-3v3M4.5 9h15M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/><path d="M8 13h3v3H8z"/>',
        'customers' => '<path d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20m5.5-9A3.5 3.5 0 1 0 9.5 4a3.5 3.5 0 0 0 0 7Zm6-5.5a3 3 0 0 1 0 5.8M18 15a3.5 3.5 0 0 1 2 3.2V20"/>',
        'team' => '<path d="M4 20v-1.5A3.5 3.5 0 0 1 7.5 15h3a3.5 3.5 0 0 1 3.5 3.5V20M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7-1 1.2 1.2L20 8.4"/>',
        'payments' => '<path d="M3.5 7h17v11a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2V7Zm0 4h17M7 16h3"/><path d="M6 4h12a2 2 0 0 1 2 2v1H4V6a2 2 0 0 1 2-2Z"/>',
        'insights' => '<path d="M5 19V9m7 10V5m7 14v-7M3 19h18"/><path d="m5 7 5-3 4 3 5-4"/>',
        'feedback' => '<path d="M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-8l-5 4v-4H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path d="M8 9h8m-8 4h5"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21h-4v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3.1 14H3v-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5V3h4v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1v4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        default => '<path d="M5 12h14M12 5v14"/>',
    };
@endphp

<svg {{ $attributes->merge(['class' => 'size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {!! $paths !!}
</svg>
