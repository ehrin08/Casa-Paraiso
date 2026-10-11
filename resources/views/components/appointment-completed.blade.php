@props(['transaction', 'role', 'voucherName' => null])

@php
    $modalName = 'appointment-completed-'.$transaction->id;
    $transactionUrl = route($role.'.transactions.show', $transaction);
    $returnFocus = $role === 'admin' ? '#appointments-add' : 'a[data-panel-link]';
@endphp

<x-modal :name="$modalName" :show="true" maxWidth="5xl" focusable centered transient
    panel-class="casa-completion-dialog" labelled-by="appointment-completed-title" :return-focus="$returnFocus">
    <header class="casa-completion-header">
        <div>
            <p class="casa-section-label">{{ __('Visit saved') }}</p>
            <h2 id="appointment-completed-title" class="mt-1 text-2xl font-extrabold">{{ __('Appointment completed') }}</h2>
            <p class="mt-2 text-sm text-casa-muted">{{ __('The appointment and transaction have been recorded.') }}</p>
        </div>
        <button type="button" class="casa-completion-close" aria-label="{{ __('Close appointment completion summary') }}" x-on:click="close()">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
        </button>
    </header>
    <div class="casa-completion-body">
        <x-appointment-completion-details :transaction="$transaction" :voucher-name="$voucherName" />
    </div>
    <footer class="casa-completion-footer">
        <button type="button" class="casa-button-secondary" x-on:click="close()">{{ __('Close') }}</button>
        <a href="{{ $transactionUrl }}" class="casa-button-primary">{{ __('View transaction') }}</a>
    </footer>
</x-modal>

<noscript>
    <section class="casa-card mt-5 p-5" aria-labelledby="appointment-completed-fallback-title">
        <h2 id="appointment-completed-fallback-title" class="mb-5 text-2xl font-extrabold">{{ __('Appointment completed') }}</h2>
        <x-appointment-completion-details :transaction="$transaction" :voucher-name="$voucherName" />
        <a href="{{ $transactionUrl }}" class="casa-button-primary mt-5">{{ __('View transaction') }}</a>
    </section>
</noscript>
