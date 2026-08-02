<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin transaction') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Record payment') }}</h1>
        </div>
    </x-slot>

    @include('admin.transactions.partials.form', [
        'action' => route('admin.transactions.store'),
        'method' => 'POST',
        'submitLabel' => __('Save transaction'),
    ])
</x-app-layout>
