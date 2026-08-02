<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Service catalog') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Edit service') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Update treatment details while keeping appointment history connected to this service record.') }}
            </p>
        </div>

        <a href="{{ route('admin.services.show', $service) }}" class="casa-button-secondary">{{ __('View service') }}</a>
    </x-slot>

    @include('admin.services.partials.form', [
        'action' => route('admin.services.update', $service),
        'method' => 'PATCH',
        'submitLabel' => __('Save changes'),
        'cancelUrl' => route('admin.services.show', $service),
    ])
</x-app-layout>
