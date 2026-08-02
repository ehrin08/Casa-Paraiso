<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Therapist management') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Add therapist') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Pre-authorize a sign-in email, operational profile, and service eligibility in one place.') }}
            </p>
        </div>

        <a href="{{ route('admin.staff.index') }}" class="casa-button-secondary">{{ __('Back to therapists') }}</a>
    </x-slot>

    @include('admin.staff.partials.form', [
        'action' => route('admin.staff.store'),
        'method' => 'POST',
        'submitLabel' => __('Create therapist'),
    ])
</x-app-layout>
