<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Therapist management') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Edit therapist') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Update access, profile details, bookable status, and service assignments.') }}
            </p>
        </div>

        <a href="{{ route('admin.staff.show', $staffProfile) }}" class="casa-button-secondary">{{ __('View therapist') }}</a>
    </x-slot>

    @include('admin.staff.partials.form', [
        'action' => route('admin.staff.update', $staffProfile),
        'method' => 'PATCH',
        'submitLabel' => __('Save changes'),
        'passwordHelp' => __('Leave password blank to keep the current password.'),
        'cancelUrl' => route('admin.staff.show', $staffProfile),
    ])
</x-app-layout>
