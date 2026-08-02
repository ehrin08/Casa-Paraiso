<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Schedule exception') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Add exception') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Add a one-off availability override for ') }}{{ $staffProfile->user->name }}.
            </p>
        </div>

        <a href="{{ route('admin.staff.show', $staffProfile) }}" class="casa-button-secondary">{{ __('Back to therapist') }}</a>
    </x-slot>

    @include('admin.staff.schedule-exceptions.partials.form', [
        'action' => route('admin.staff.schedule-exceptions.store', $staffProfile),
        'method' => 'POST',
        'submitLabel' => __('Create exception'),
    ])
</x-app-layout>
