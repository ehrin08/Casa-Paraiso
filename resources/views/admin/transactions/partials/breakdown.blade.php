@php
    $adjustments = $transaction->relationLoaded('adjustments') ? $transaction->adjustments : $transaction->adjustments()->get();
@endphp
@if ($adjustments->isNotEmpty() || $transaction->gift_voucher_id)
    <section class="mt-5 rounded-2xl border border-casa-border bg-casa-bg p-4 text-sm" data-transaction-breakdown aria-label="{{ __('Payment breakdown') }}">
        <p class="casa-section-label">{{ __('Payment breakdown') }}</p>
        @if ($transaction->customerMembership)
            <p class="mt-2 font-bold text-casa-text">{{ $transaction->customerMembership->tier?->name }} · {{ $transaction->customerMembership->card_number }}</p>
        @endif
        <dl class="mt-3 space-y-1">
            <div class="flex justify-between gap-3"><dt>{{ __('Amount before benefits') }}</dt><dd class="font-semibold">PHP {{ number_format((float) ($transaction->gross_amount ?? $transaction->amount), 2) }}</dd></div>
            @foreach ($adjustments as $adjustment)
                <div class="flex justify-between gap-3 text-casa-muted"><dt>{{ $adjustment->label }}</dt><dd class="whitespace-nowrap">− PHP {{ number_format((float) $adjustment->amount, 2) }}</dd></div>
            @endforeach
            <div class="flex justify-between gap-3 border-t border-casa-border pt-2 font-black text-casa-text"><dt>{{ __('Amount after benefits') }}</dt><dd>PHP {{ number_format((float) $transaction->amount, 2) }}</dd></div>
            @if ($transaction->giftVoucher)
                <div class="flex justify-between gap-3"><dt>{{ __('Gift voucher :code', ['code' => $transaction->giftVoucher->code]) }}</dt><dd class="whitespace-nowrap">− PHP {{ number_format((float) $transaction->gift_voucher_applied, 2) }}</dd></div>
                @if ((float) $transaction->giftVoucher->forfeited_amount > 0)
                    <div class="flex justify-between gap-3 text-casa-muted"><dt>{{ __('Unused voucher balance (forfeited)') }}</dt><dd>PHP {{ number_format((float) $transaction->giftVoucher->forfeited_amount, 2) }}</dd></div>
                @endif
                <div class="flex justify-between gap-3 font-black text-casa-text"><dt>{{ __('Paid with :method', ['method' => $transaction->payment_method ?: __('other method')]) }}</dt><dd>PHP {{ number_format((float) $transaction->amount_due, 2) }}</dd></div>
            @endif
        </dl>
    </section>
@endif
