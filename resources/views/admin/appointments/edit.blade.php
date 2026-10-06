<x-app-layout>
    <x-slot name="header">
        <x-page-heading>
        <div>
            <p class="casa-section-label">{{ __('Admin appointment') }}</p>
            <h1 class="mt-2 font-display font-black text-casa-text">{{ __('Edit appointment') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $appointment->appointment_number }}
            </p>
        </div>
        <a href="{{ route('admin.appointments.index') }}" class="casa-button-secondary">{{ __('Back to appointments') }}</a>
        </x-page-heading>
    </x-slot>

    @include('admin.appointments.partials.form', [
        'action' => route('admin.appointments.update', $appointment),
        'method' => 'PATCH',
        'submitLabel' => __('Update appointment'),
        'cancelUrl' => route('admin.appointments.show', $appointment),
    ])
</x-app-layout>
