@props(['label' => null])

<div
    {{ $attributes->merge([
        'class' => 'casa-table-wrap',
        'aria-label' => $label ?? __('Scrollable records table'),
    ]) }}
    tabindex="0"
    role="region"
>
    <table class="min-w-full divide-y divide-casa-border">
        {{ $slot }}
    </table>
</div>
