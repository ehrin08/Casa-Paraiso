<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin appointment') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Create appointment') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Create a confirmed booking by choosing an eligible therapist and schedule.') }}
            </p>
        </div>
    </x-slot>

    @include('admin.appointments.partials.form', [
        'action' => route('admin.appointments.store'),
        'method' => 'POST',
        'submitLabel' => __('Save appointment'),
        'fixedStatus' => \App\Models\Appointment::STATUS_CONFIRMED,
    ])
</x-app-layout>
