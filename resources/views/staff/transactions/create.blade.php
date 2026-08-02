<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Therapist transaction') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Record payment') }}</h1>
        </div>
    </x-slot>

    @include('staff.transactions.partials.form', [
        'action' => route('staff.transactions.store'),
        'method' => 'POST',
        'submitLabel' => __('Save transaction'),
    ])
</x-app-layout>
