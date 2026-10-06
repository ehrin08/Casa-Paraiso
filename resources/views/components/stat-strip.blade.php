@props(['items' => [], 'presentation' => 'strip'])

<dl
    {{ $attributes->merge(['class' => $presentation === 'cards' ? 'casa-appointment-stats' : 'casa-stat-strip']) }}
>
    @foreach ($items as $item)
        @php
            $tone = match ($item['tone'] ?? 'neutral') {
                'green' => 'text-casa-green',
                'gold' => 'text-casa-cacao',
                'brown' => 'text-casa-cacao-dark',
                'dark' => 'text-casa-charcoal',
                default => 'text-casa-text',
            };
        @endphp
        <div @class(['min-w-0 bg-casa-paper px-4 py-3', 'casa-appointment-stat' => $presentation === 'cards'])>
            @if ($presentation === 'cards')
                <span class="casa-appointment-stat-icon {{ ($item['tone'] ?? '') === 'red' ? 'is-cancelled' : '' }}">
                    <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        @if (($item['icon'] ?? '') === 'check')
                            <circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>
                        @elseif (($item['icon'] ?? '') === 'cancel')
                            <circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>
                        @elseif (($item['icon'] ?? '') === 'commission')
                            <circle cx="15" cy="6" r="4"/><path d="M15 4v4m1-4h-1.5a1 1 0 0 0 0 2H15a1 1 0 0 1 0 2h-1M3 14h3v7H3zM6 15l3-2h4a2 2 0 0 1 0 4h-2m2 0 5-3a2 2 0 0 1 2 3l-6 4H6"/>
                        @elseif (($item['icon'] ?? '') === 'coins')
                            <ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v14c0 1.7 3.1 3 7 3s7-1.3 7-3V5M5 10c0 1.7 3.1 3 7 3s7-1.3 7-3M5 15c0 1.7 3.1 3 7 3s7-1.3 7-3"/>
                        @else
                            <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/>
                        @endif
                    </svg>
                </span>
            @endif
            <div>
            <dt class="text-sm font-extrabold uppercase tracking-[0.05em] text-casa-muted">{{ $item['label'] }}</dt>
            <dd class="mt-1 text-2xl font-extrabold tracking-tight {{ $tone }}">{{ $item['value'] }}</dd>
            @if (filled($item['meta'] ?? null))
                <p class="mt-1 text-sm leading-5 text-casa-muted">{{ $item['meta'] }}</p>
            @endif
            </div>
        </div>
    @endforeach
</dl>
