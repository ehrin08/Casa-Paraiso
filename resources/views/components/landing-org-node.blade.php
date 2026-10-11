@props(['node', 'tier' => 'leader'])

@php
    $displayName = filled($node['name'] ?? null) ? $node['name'] : $node['title'];
    $photoUrl = \App\Models\ApplicationSetting::orgChartPhotoUrl($node['photo'] ?? null);
    $initials = collect(preg_split('/\s+/u', trim($displayName)))
        ->take(2)
        ->map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<article {{ $attributes->class(['landing-org-node', 'landing-org-node--team' => $tier === 'team']) }} role="listitem">
    <div class="landing-org-avatar">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="{{ $displayName }}" width="64" height="64" loading="lazy">
        @else
            <span aria-hidden="true">{{ $initials }}</span>
        @endif
    </div>
    <div class="landing-org-copy">
        <h3>{{ $displayName }}</h3>
        @if (filled($node['name'] ?? null))
            <p class="landing-org-title">{{ $node['title'] }}</p>
        @endif
        @if (filled($node['description'] ?? null))
            <p class="landing-org-description">{{ $node['description'] }}</p>
        @endif
    </div>
</article>
