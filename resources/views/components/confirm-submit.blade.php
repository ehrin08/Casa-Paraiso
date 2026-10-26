@props([
    'form',
    'label',
    'confirmTitle',
    'confirmMessage',
    'confirmButton' => null,
    'buttonClass' => 'casa-button-primary',
    'modalWidth' => 'md',
    'tone' => 'default',
    'dismissLabel' => null,
])

@php
    $modalName = 'confirm-submit-'.\Illuminate\Support\Str::uuid();
@endphp

<div @class(['block w-full' => str_contains($buttonClass, 'w-full'), 'inline' => ! str_contains($buttonClass, 'w-full')])>
    <button type="button" class="{{ $buttonClass }}" aria-haspopup="dialog" x-data="" x-on:click.prevent="$dispatch('open-modal', '{{ $modalName }}')">
        {{ $label }}
    </button>

    <x-modal :name="$modalName" :maxWidth="$modalWidth" :labelled-by="$modalName.'-title'" centered focusable>
        <x-modal-shell :title="$confirmTitle" :title-id="$modalName.'-title'">
            <p class="text-sm leading-6 text-casa-muted">{{ $confirmMessage }}</p>

            <x-slot name="footer">
                <button type="button" class="casa-button-secondary" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">
                    {{ $dismissLabel ?? __('Cancel') }}
                </button>
                <button type="submit" form="{{ $form }}" class="{{ $tone === 'danger' ? 'casa-danger-button' : 'casa-button-primary' }}">
                    {{ $confirmButton ?? $label }}
                </button>
            </x-slot>
        </x-modal-shell>
    </x-modal>
</div>
