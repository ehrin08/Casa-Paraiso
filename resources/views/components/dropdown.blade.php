@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'p-2 bg-casa-surface'])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false"
    @keydown.escape="if (open) { open = false; $refs.trigger.querySelector('button, a')?.focus() }">
    <div x-ref="trigger" @click="open = ! open" x-effect="$el.querySelector('button')?.setAttribute('aria-expanded', open)">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-2 {{ $width }} rounded-[20px] shadow-casa-card {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
        <div class="rounded-[20px] border border-casa-border/80 {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
