@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-2xl border border-casa-gold/40 bg-casa-gold/15 px-4 py-3 text-start text-sm font-bold text-casa-primary transition duration-150 ease-in-out'
            : 'block w-full rounded-2xl border border-transparent px-4 py-3 text-start text-sm font-bold text-casa-muted transition duration-150 ease-in-out hover:border-casa-border hover:bg-white/70 hover:text-casa-primary';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
