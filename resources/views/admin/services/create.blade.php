<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Service catalog') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Add service') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Create a treatment record with pricing and duration before it becomes available for therapist assignment.') }}
            </p>
        </div>

        <a href="{{ route('admin.services.index') }}" class="casa-button-secondary">{{ __('Back to services') }}</a>
    </x-slot>

    @include('admin.services.partials.form', [
        'action' => route('admin.services.store'),
        'method' => 'POST',
        'submitLabel' => __('Create service'),
    ])
</x-app-layout>
