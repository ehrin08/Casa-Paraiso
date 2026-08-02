<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Reception payment') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $transaction->exists ? __('Edit payment') : __('Record payment') }}</h1>
        </div>
    </x-slot>

    @include('admin.transactions.partials.form', [
        'transaction' => $transaction,
        'action' => $transaction->exists ? route('reception.transactions.update', $transaction) : route('reception.transactions.store'),
        'method' => $transaction->exists ? 'PATCH' : 'POST',
        'submitLabel' => $transaction->exists ? __('Save payment') : __('Record payment'),
        'cancelUrl' => route('reception.transactions.index'),
    ])
</x-app-layout>
