@props([
    'sort',
    'align' => 'left',
])

@php
    $currentSort = (string) request()->query('sort');
    $currentDirection = strtolower((string) request()->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
    $isActive = $currentSort === $sort;
    $nextDirection = $isActive && $currentDirection === 'asc' ? 'desc' : 'asc';
    $query = array_merge(request()->except('page'), [
        'sort' => $sort,
        'direction' => $nextDirection,
    ]);
    $alignment = $align === 'right' ? 'text-right' : 'text-left';
@endphp

<th {{ $attributes->merge(['class' => "px-4 py-3 {$alignment}"]) }}>
    <a href="{{ request()->url().'?'.http_build_query($query) }}" class="inline-flex items-center gap-1.5 rounded-full text-casa-muted transition hover:text-casa-primary">
        <span>{{ $slot }}</span>
        <span class="text-[0.65rem]" aria-hidden="true">
            @if ($isActive)
                {{ $currentDirection === 'asc' ? '▲' : '▼' }}
            @else
                ↕
            @endif
        </span>
    </a>
</th>
