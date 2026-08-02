<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-eyebrow">{{ __('Customer lounge') }}</p>
            <h1 class="mt-3 font-editorial text-4xl font-semibold text-casa-text">{{ __('Book an appointment') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Choose your service and an available time. Your therapist and visit are reserved as soon as booking succeeds.') }}
            </p>
        </div>

        <a href="{{ route('customer.appointments.index') }}" class="casa-button-secondary">{{ __('Back to appointments') }}</a>
    </x-slot>

    @include('customer.appointments.partials.form')
</x-app-layout>
