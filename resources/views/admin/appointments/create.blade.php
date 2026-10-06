<x-app-layout>
    <x-slot name="header">
        <x-page-heading>
        <div>
            <p class="casa-section-label">{{ __('Appointments / Create appointment') }}</p>
            <h1 class="mt-2 font-display font-black text-casa-text">{{ __('Create appointment') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Choose a customer, treatment, and available therapist.') }}
            </p>
        </div>
        <a href="{{ route('admin.appointments.index') }}" class="casa-button-secondary">{{ __('Back to appointments') }}</a>
        </x-page-heading>
    </x-slot>

    @include('admin.appointments.partials.form', [
        'action' => route('admin.appointments.store'),
        'method' => 'POST',
        'submitLabel' => __('Save appointment'),
        'fixedStatus' => \App\Models\Appointment::STATUS_CONFIRMED,
    ])
</x-app-layout>
