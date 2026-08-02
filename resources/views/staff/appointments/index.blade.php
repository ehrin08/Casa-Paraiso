<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Therapist schedule') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('My booking calendar') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('See your confirmed visits and eligible customer requests against your admin-managed working hours.') }}
            </p>
        </div>
    </x-slot>

    <x-operational-calendar
        :feed-url="route('staff.appointments.calendar')"
        :initial-week="$initialWeek"
        role="staff"
    />
</x-app-layout>
