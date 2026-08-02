<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customer feedback') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Submit feedback') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Rate a completed appointment and leave an optional service comment.') }}</p>
        </div>
    </x-slot>

    @include('customer.feedback.partials.form')
</x-app-layout>
