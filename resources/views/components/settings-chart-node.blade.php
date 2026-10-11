@props(['role', 'label'])
@php $photoUrl = \App\Models\ApplicationSetting::orgChartPhotoUrl($role['photo'] ?? null); @endphp
<div {{ $attributes->class(['settings-chart-node']) }}>
    @if ($photoUrl)
        <img src="{{ $photoUrl }}" alt="" width="36" height="36" loading="lazy">
    @else
        <x-nav-icon :name="$label === __('Team role') ? 'team' : 'profile'" />
    @endif
    <div>
        <span class="settings-chart-role">{{ $role['title'] }}</span>
        @if (filled($role['name'] ?? null))
            <p>{{ $role['name'] }}</p>
        @endif
        @if (filled($role['description'] ?? null))
            <p>{{ $role['description'] }}</p>
        @endif
    </div>
</div>
