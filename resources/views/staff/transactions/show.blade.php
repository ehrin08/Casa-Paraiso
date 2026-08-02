<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Transaction detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $transaction->transaction_number }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $transaction->customerProfile?->user?->name }}</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('staff.transactions.index') }}" class="casa-button-secondary">{{ __('All transactions') }}</a>
        </div>
    </x-slot>

    <x-app-card>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl bg-casa-bg p-4">
                <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Amount') }}</dt>
                <dd class="mt-2 font-display text-2xl font-black text-casa-text">PHP {{ number_format((float) $transaction->amount, 2) }}</dd>
            </div>
            <div class="rounded-2xl bg-casa-bg p-4">
                <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Status') }}</dt>
                <dd class="mt-2"><x-status-badge>{{ ucfirst($transaction->payment_status) }}</x-status-badge></dd>
            </div>
            <div class="rounded-2xl bg-casa-bg p-4">
                <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Service') }}</dt>
                <dd class="mt-2 font-semibold text-casa-text">{{ $transaction->service?->name }}</dd>
            </div>
        </dl>
    </x-app-card>
</x-app-layout>
