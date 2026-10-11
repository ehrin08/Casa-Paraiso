@props(['label', 'icon' => 'profile'])

<div class="notifications-detail-item">
    <span class="notifications-detail-icon">
        @if ($icon === 'clock')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></svg>
        @elseif ($icon === 'treatment')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21c-5-4-6-11 0-18 6 7 5 14 0 18Zm0 0C4 21 1 16 3 9c4 0 7 2 9 5m0 7c8 0 11-5 9-12-4 0-7 2-9 5"/></svg>
        @else
            <x-nav-icon :name="$icon"/>
        @endif
    </span>
    <div><dt>{{ $label }}</dt><dd>{{ $slot }}</dd></div>
</div>
