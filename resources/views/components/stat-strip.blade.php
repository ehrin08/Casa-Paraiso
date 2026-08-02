@props(['items' => []])

<dl
    {{ $attributes->merge(['class' => 'casa-stat-strip']) }}
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
        <div class="min-w-0 bg-casa-paper px-4 py-3">
            <dt class="text-sm font-extrabold uppercase tracking-[0.05em] text-casa-muted">{{ $item['label'] }}</dt>
            <dd class="mt-1 text-2xl font-extrabold tracking-tight {{ $tone }}">{{ $item['value'] }}</dd>
            @if (filled($item['meta'] ?? null))
                <p class="mt-1 text-sm leading-5 text-casa-muted">{{ $item['meta'] }}</p>
            @endif
        </div>
    @endforeach
</dl>
