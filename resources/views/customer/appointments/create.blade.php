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

    @if ($bookingRestrictionMessage)
        <x-app-card data-booking-restricted>
            <p class="casa-section-label">{{ __('Online booking') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Booking is not available right now') }}</h2>
            <p class="mt-3 text-sm leading-6 text-casa-muted" role="status">{{ $bookingRestrictionMessage }}</p>
            <a href="{{ route('customer.appointments.index') }}" class="mt-5 casa-button-secondary">{{ __('Back to appointments') }}</a>
        </x-app-card>
    @else
        @include('customer.appointments.partials.form')
    @endif
</x-app-layout>
