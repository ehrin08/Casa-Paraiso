<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Add-on catalog') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Add add-on') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Create a booking extra with pricing and duration before it is offered on appointments.') }}
            </p>
        </div>

        <a href="{{ route('admin.addons.index') }}" class="casa-button-secondary">{{ __('Back to add-ons') }}</a>
    </x-slot>

    @include('admin.addons.partials.form', [
        'action' => route('admin.addons.store'),
        'method' => 'POST',
        'submitLabel' => __('Create add-on'),
    ])
</x-app-layout>
