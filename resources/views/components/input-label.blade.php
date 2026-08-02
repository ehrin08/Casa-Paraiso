@props(['value'])

<label {{ $attributes->merge(['class' => 'casa-label']) }}>
    {{ $value ?? $slot }}
</label>
