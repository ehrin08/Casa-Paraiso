<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Weekly schedule') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Edit weekly shift') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Adjust recurring availability for ') }}{{ $staffProfile->user->name }}.
            </p>
        </div>

        <a href="{{ route('admin.staff.show', $staffProfile) }}" class="casa-button-secondary">{{ __('Back to therapist') }}</a>
    </x-slot>

    @include('admin.staff.weekly-schedules.partials.form', [
        'action' => route('admin.staff.weekly-schedules.update', [$staffProfile, $weeklySchedule]),
        'method' => 'PATCH',
        'submitLabel' => __('Save shift'),
    ])
</x-app-layout>
