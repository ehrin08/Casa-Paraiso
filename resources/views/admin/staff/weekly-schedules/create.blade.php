<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Weekly schedule') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Add weekly shift') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Create a recurring working window for ') }}{{ $staffProfile->user->name }}.
            </p>
        </div>

        <a href="{{ route('admin.staff.show', $staffProfile) }}" class="casa-button-secondary">{{ __('Back to therapist') }}</a>
    </x-slot>

    @include('admin.staff.weekly-schedules.partials.form', [
        'action' => route('admin.staff.weekly-schedules.store', $staffProfile),
        'method' => 'POST',
        'submitLabel' => __('Create shift'),
    ])
</x-app-layout>
