@props([
    'action',
    'method' => 'POST',
    'label',
    'confirmTitle',
    'confirmMessage',
    'confirmButton' => null,
    'buttonClass' => 'font-bold text-casa-muted hover:text-casa-primary',
    'modalWidth' => 'md',
])

@php
    $modalName = 'confirm-action-'.\Illuminate\Support\Str::uuid();
    $spoofMethod = strtoupper($method);
@endphp

<div @class(['block w-full' => str_contains($buttonClass, 'w-full'), 'inline' => ! str_contains($buttonClass, 'w-full')])>
    <button type="button" class="{{ $buttonClass }}" x-data="" x-on:click.prevent="$dispatch('open-modal', '{{ $modalName }}')">
        {{ $label }}
    </button>

    <x-modal :name="$modalName" :maxWidth="$modalWidth">
        <form method="POST" action="{{ $action }}" class="p-6">
            @csrf
            @if ($spoofMethod !== 'POST')
                @method($spoofMethod)
            @endif
            {{ $slot }}

            <h2 class="font-display text-xl font-black text-casa-text">{{ $confirmTitle }}</h2>
            <p class="mt-3 text-sm leading-6 text-casa-muted">{{ $confirmMessage }}</p>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" class="casa-button-secondary" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="casa-button-primary">
                    {{ $confirmButton ?? $label }}
                </button>
            </div>
        </form>
    </x-modal>
</div>
