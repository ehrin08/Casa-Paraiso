@props(['tabs' => [], 'label'])

{{-- Switches between related pages that share one drawer link; each tab keeps its own route. --}}
<nav {{ $attributes->merge(['class' => 'casa-section-tabs']) }} aria-label="{{ $label }}" data-section-tabs>
    @foreach ($tabs as $tab)
        @php $isActive = request()->routeIs(...(array) ($tab['active'] ?? $tab['route'])); @endphp
        <a href="{{ route($tab['route']) }}" @class(['casa-section-tab', 'is-active' => $isActive]) @if($isActive) aria-current="page" @endif>{{ $tab['label'] }}</a>
    @endforeach
</nav>
