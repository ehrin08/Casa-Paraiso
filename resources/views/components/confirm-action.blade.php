@props([
    'action',
    'method' => 'POST',
    'label',
    'confirmTitle',
    'confirmMessage',
    'confirmButton' => null,
    'buttonClass' => 'font-bold text-casa-muted hover:text-casa-primary',
    'modalWidth' => 'md',
    'tone' => 'default',
    'dismissLabel' => null,
])

@php
    $modalName = 'confirm-action-'.\Illuminate\Support\Str::uuid();
    $spoofMethod = strtoupper($method);
@endphp

<div @class(['block w-full' => str_contains($buttonClass, 'w-full'), 'inline' => ! str_contains($buttonClass, 'w-full')])>
    <button type="button" class="{{ $buttonClass }}" aria-haspopup="dialog" x-data="" x-on:click.prevent="$dispatch('open-modal', '{{ $modalName }}')">
        {{ $trigger ?? $label }}
    </button>

    <x-modal :name="$modalName" :maxWidth="$modalWidth" :labelled-by="$modalName.'-title'" centered focusable>
        <form method="POST" action="{{ $action }}" data-turbo="false">
            @csrf
            @if ($spoofMethod !== 'POST')
                @method($spoofMethod)
            @endif

            <x-modal-shell :title="$confirmTitle" :title-id="$modalName.'-title'">
                <p class="text-sm leading-6 text-casa-muted">{{ $confirmMessage }}</p>
                {{ $slot }}

                <x-slot name="footer">
                    <button type="button" class="casa-button-secondary" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">
                        {{ $dismissLabel ?? __('Cancel') }}
                    </button>
                    <button type="submit" class="{{ $tone === 'danger' ? 'casa-danger-button' : 'casa-button-primary' }}">
                        {{ $confirmButton ?? $label }}
                    </button>
                </x-slot>
            </x-modal-shell>
        </form>
    </x-modal>
</div>
