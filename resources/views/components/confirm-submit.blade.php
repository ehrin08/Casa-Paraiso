@props([
    'form',
    'label',
    'confirmTitle',
    'confirmMessage',
    'confirmButton' => null,
    'buttonClass' => 'casa-button-primary',
    'modalWidth' => 'md',
])

@php
    $modalName = 'confirm-submit-'.\Illuminate\Support\Str::uuid();
@endphp

<div @class(['block w-full' => str_contains($buttonClass, 'w-full'), 'inline' => ! str_contains($buttonClass, 'w-full')])>
    <button type="button" class="{{ $buttonClass }}" x-data="" x-on:click.prevent="$dispatch('open-modal', '{{ $modalName }}')">
        {{ $label }}
    </button>

    <x-modal :name="$modalName" :maxWidth="$modalWidth">
        <div class="p-6">
            <h2 class="font-display text-xl font-black text-casa-text">{{ $confirmTitle }}</h2>
            <p class="mt-3 text-sm leading-6 text-casa-muted">{{ $confirmMessage }}</p>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" class="casa-button-secondary" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" form="{{ $form }}" class="casa-button-primary">
                    {{ $confirmButton ?? $label }}
                </button>
            </div>
        </div>
    </x-modal>
</div>
