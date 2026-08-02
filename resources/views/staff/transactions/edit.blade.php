<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Therapist transaction') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Edit payment') }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $transaction->transaction_number }}</p>
        </div>
    </x-slot>

    @include('staff.transactions.partials.form', [
        'action' => route('staff.transactions.update', $transaction),
        'method' => 'PATCH',
        'submitLabel' => __('Update transaction'),
    ])
</x-app-layout>
