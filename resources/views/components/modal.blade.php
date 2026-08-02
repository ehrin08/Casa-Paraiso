@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl'
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '3xl' => 'sm:max-w-3xl',
    '4xl' => 'sm:max-w-4xl',
    '5xl' => 'sm:max-w-5xl',
    '6xl' => 'sm:max-w-6xl',
][$maxWidth];
@endphp

<div
    x-data="casaModal({
        name: @js($name),
        initialShow: @js($show),
        focusable: @js($attributes->has('focusable')),
    })"
    x-init="init()"
    data-modal-name="{{ $name }}"
>
<template x-teleport="body">
    <div
        x-on:close.stop="close()"
        x-on:keydown.tab="handleTab($event)"
        x-show="show"
        x-cloak
        x-bind:aria-hidden="(! show).toString()"
        data-modal-name="{{ $name }}"
        class="fixed inset-0 z-[100] isolate overflow-y-auto px-4 py-6 sm:px-0"
        role="dialog"
        aria-modal="true"
        aria-label="{{ str_replace('-', ' ', ucfirst($name)) }}"
    >
        <div
            x-show="show"
            class="fixed inset-0 z-0 transform transition-all"
            x-on:click="close()"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div class="absolute inset-0 bg-casa-charcoal/72 backdrop-blur-sm"></div>
        </div>

        <div
            x-show="show"
            class="casa-card relative z-10 mb-6 max-h-[calc(100vh-3rem)] overflow-y-auto transform transition-all sm:w-full {{ $maxWidth }} sm:mx-auto"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            {{ $slot }}
        </div>
    </div>
</template>
</div>
