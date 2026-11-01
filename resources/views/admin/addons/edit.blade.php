<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Add-on catalog') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Edit add-on') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Update extra details while keeping booked appointments connected to this add-on record.') }}
            </p>
        </div>

        <a href="{{ route('admin.addons.show', $addon) }}" class="casa-button-secondary">{{ __('View add-on') }}</a>
    </x-slot>

    @include('admin.addons.partials.form', [
        'action' => route('admin.addons.update', $addon),
        'method' => 'PATCH',
        'submitLabel' => __('Save changes'),
        'cancelUrl' => route('admin.addons.show', $addon),
    ])
</x-app-layout>
