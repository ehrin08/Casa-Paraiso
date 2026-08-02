@props(['variant' => 'operational'])

<div
    data-page-heading
    data-page-heading-variant="{{ $variant }}"
    {{ $attributes->merge(['class' => 'casa-page-heading flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end sm:justify-between']) }}
>
    {{ $slot }}
</div>
