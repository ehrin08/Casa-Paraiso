@props([
    'quoteUrl',
    'appointment' => null,
    'amountField' => 'amount',
    'appointmentField' => null,
    'customerField' => null,
    'paidAtField' => null,
    'voucherField' => null,
    'modal' => null,
])

@php
    // Forms inside a modal load the preview when that modal opens instead of on page load.
    $initial = $appointment && ! $modal
        ? \App\Http\Controllers\TransactionQuoteController::present(app(\App\Services\PaymentPricing::class)->quote($appointment, $appointment->expectedAmount()))
        : null;
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-casa-border bg-casa-bg p-4 text-sm']) }}
     data-payment-benefits
     @if ($modal) x-on:open-modal.window="if ($event.detail === @js($modal)) refresh()" @endif
     x-data="{
        quote: @js($initial),
        appointmentId: @js($appointment?->id),
        timer: null,
        field(id) { return id ? document.getElementById(id) : null },
        money(value) { return 'PHP ' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
        refresh() {
            const appointment = this.field(@js($appointmentField))?.value || this.appointmentId;
            const customer = this.field(@js($customerField))?.value;
            const voucher = this.field(@js($voucherField))?.value?.trim();
            const amount = this.field(@js($amountField))?.value;
            if ((! appointment && ! voucher) || amount === '' || amount === undefined) { this.quote = null; return; }
            const params = new URLSearchParams({ amount });
            if (appointment) params.set('appointment_id', appointment);
            if (customer) params.set('customer_profile_id', customer);
            if (voucher) params.set('gift_voucher_code', voucher);
            const paidAt = this.field(@js($paidAtField))?.value;
            if (paidAt) params.set('paid_at', paidAt);
            fetch(@js($quoteUrl) + '?' + params, { headers: { Accept: 'application/json' } })
                .then((response) => response.ok ? response.json() : null)
                .then((quote) => { this.quote = quote })
                .catch(() => {});
        },
        queue() { clearTimeout(this.timer); this.timer = setTimeout(() => this.refresh(), 300) },
        init() {
            [@js($amountField), @js($appointmentField), @js($customerField), @js($paidAtField), @js($voucherField)].forEach((id) => {
                this.field(id)?.addEventListener('input', () => this.queue());
                this.field(id)?.addEventListener('change', () => this.queue());
            });
            if (! this.quote && ! @js($modal)) this.refresh();
        },
     }">
    <p class="casa-section-label">{{ __('Benefits and voucher') }}</p>
    <template x-if="! quote || (! quote.membership && quote.adjustments.length === 0 && ! quote.voucher)">
        <p class="mt-2 text-casa-muted">{{ __('No membership benefits apply. The amount entered is charged as is.') }}</p>
    </template>
    <template x-if="quote && (quote.membership || quote.adjustments.length > 0 || quote.voucher)">
        <div class="mt-2 space-y-2">
            <p class="font-bold text-casa-text" x-show="quote.membership" x-text="quote.membership"></p>
            <ul class="space-y-1">
                <template x-for="adjustment in quote.adjustments">
                    <li class="flex justify-between gap-3"><span x-text="adjustment.label"></span><span class="whitespace-nowrap font-semibold" x-text="'− ' + money(adjustment.amount)"></span></li>
                </template>
            </ul>
            <p class="flex justify-between gap-3 border-t border-casa-border pt-2 font-black text-casa-text"><span>{{ __('Amount after benefits') }}</span><span x-text="money(quote.net)"></span></p>
            <template x-if="quote.voucher">
                <div class="space-y-1">
                    <p class="flex justify-between gap-3"><span x-text="'{{ __('Gift voucher') }} ' + quote.voucher.code"></span><span class="whitespace-nowrap font-semibold" x-text="'− ' + money(quote.voucher.applied)"></span></p>
                    <p class="flex justify-between gap-3 text-casa-muted" x-show="quote.voucher.forfeited > 0"><span>{{ __('Unused voucher balance (forfeited)') }}</span><span x-text="money(quote.voucher.forfeited)"></span></p>
                    <p class="flex justify-between gap-3 font-black text-casa-text"><span>{{ __('Still to pay') }}</span><span x-text="money(quote.amount_due)"></span></p>
                </div>
            </template>
        </div>
    </template>
    <p class="mt-2 font-semibold text-casa-danger-text" x-show="quote && quote.voucher_error" x-text="quote?.voucher_error"></p>
    <p class="mt-2 text-xs text-casa-muted">{{ __('Benefits and vouchers are applied when the payment is first saved as paid.') }}</p>
</div>
