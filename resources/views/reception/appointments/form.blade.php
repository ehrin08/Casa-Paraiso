<x-app-layout>
    <x-slot name="header">
        <x-page-heading>
            <div>
                <p class="casa-section-label">{{ __('Appointments') }}</p>
                <h1 class="mt-2 font-display font-black text-casa-text">{{ $appointment->exists ? __('Edit appointment') : __('Create appointment') }}</h1>
                <p class="mt-2 text-sm text-casa-muted">{{ $appointment->exists ? $appointment->appointment_number : __('Choose a customer, treatment, and available therapist.') }}</p>
            </div>
            <a href="{{ route('reception.appointments.index') }}" class="casa-button-secondary">{{ __('Back to appointments') }}</a>
        </x-page-heading>
    </x-slot>
    @include('admin.appointments.partials.form', ['appointment' => $appointment, 'action' => $appointment->exists ? route('reception.appointments.update', $appointment) : route('reception.appointments.store'), 'method' => $appointment->exists ? 'PATCH' : 'POST', 'submitLabel' => $appointment->exists ? __('Save appointment') : __('Confirm appointment'), 'fixedStatus' => $appointment->exists ? null : \App\Models\Appointment::STATUS_CONFIRMED, 'availableTherapistsUrl' => route('reception.appointments.available-therapists'), 'availabilityUrl' => route('reception.appointments.availability'), 'cancelUrl' => route('reception.appointments.index')])
</x-app-layout>
