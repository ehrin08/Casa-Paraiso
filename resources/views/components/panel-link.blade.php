@props([
    'href',
])

<a href="{{ $href }}" data-panel-link data-turbo="false" {{ $attributes }}>
    {{ $slot }}
</a>
