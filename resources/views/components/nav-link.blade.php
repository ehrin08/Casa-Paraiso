@props(['active'])

@php
$classes = ($active ?? false)
            ? 'casa-top-link casa-top-link-active'
            : 'casa-top-link';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
