<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin transaction') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Edit transaction') }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $transaction->transaction_number }}</p>
        </div>
    </x-slot>

    @include('admin.transactions.partials.form', [
        'action' => route('admin.transactions.update', $transaction),
        'method' => 'PATCH',
        'submitLabel' => __('Update transaction'),
        'cancelUrl' => route('admin.transactions.show', $transaction),
    ])
</x-app-layout>
