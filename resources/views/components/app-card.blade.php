@props(['padding' => 'p-4 sm:p-5'])

<div {{ $attributes->merge(['class' => 'casa-card casa-app-card '.$padding]) }}>
    {{ $slot }}
</div>
