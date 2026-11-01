<x-app-layout>
    @php
        $lifecycle = $voucher->lifecycle();
        $peso = fn ($value) => 'PHP '.number_format((float) $value, 2);
    @endphp
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Gift voucher') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $voucher->code }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $peso($voucher->value) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($voucher->status === \App\Models\GiftVoucher::STATUS_ACTIVE && request()->user()->isAdmin())
                <button type="button" class="casa-button-secondary" x-data x-on:click="$dispatch('open-modal', 'void-voucher')">{{ __('Void voucher') }}</button>
            @endif
            <a href="{{ route($routePrefix.'gift-vouchers.index') }}" class="casa-button-secondary">{{ __('All vouchers') }}</a>
        </div>
    </x-slot>

    <x-app-card data-gift-voucher-detail>
        <x-input-error class="mb-4" :messages="$errors->get('voucher')" />
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Status') }}</dt><dd class="mt-2"><x-status-badge :tone="match ($lifecycle) { 'active' => 'success', 'redeemed' => 'info', 'expired' => 'warning', default => 'dark' }">{{ ucfirst($lifecycle) }}</x-status-badge></dd></div>
            <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Valid until') }}</dt><dd class="mt-2 font-semibold">{{ $voucher->expires_at?->format('M d, Y') ?? __('No expiry') }}</dd></div>
            <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Sold') }}</dt><dd class="mt-2 font-semibold">{{ $voucher->issued_at->format('M d, Y') }} · {{ $voucher->sale_payment_method }} · {{ $voucher->seller?->name }}</dd></div>
            <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Purchaser') }}</dt><dd class="mt-2 font-semibold">{{ $voucher->purchaser?->display_name ?? $voucher->purchaser_name ?? __('Walk-in') }}</dd></div>
            @if ($voucher->status === \App\Models\GiftVoucher::STATUS_REDEEMED)
                <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Redeemed') }}</dt><dd class="mt-2 font-semibold">{{ $voucher->redeemed_at?->format('M d, Y g:i A') }} · {{ $voucher->redeemedTransaction?->transaction_number }}</dd></div>
                <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Applied / forfeited') }}</dt><dd class="mt-2 font-semibold">{{ $peso($voucher->applied_amount) }} / {{ $peso($voucher->forfeited_amount) }}</dd></div>
            @endif
            @if ($voucher->status === \App\Models\GiftVoucher::STATUS_VOID)
                <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2"><dt class="casa-section-label">{{ __('Voided') }}</dt><dd class="mt-2 font-semibold">{{ $voucher->voided_at?->format('M d, Y') }} · {{ $voucher->void_reason }}</dd></div>
            @endif
        </dl>
        @if ($voucher->notes)
            <p class="mt-5 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $voucher->notes }}</p>
        @endif
    </x-app-card>

    @if ($voucher->status === \App\Models\GiftVoucher::STATUS_ACTIVE && request()->user()->isAdmin())
        <x-modal name="void-voucher" :show="$errors->has('void_reason')" maxWidth="md" focusable>
            <form method="POST" action="{{ route('admin.gift-vouchers.void', $voucher) }}" class="p-5">
                @csrf
                @method('PATCH')
                <h2 class="font-display text-xl font-black">{{ __('Void this voucher?') }}</h2>
                <p class="mt-2 text-sm text-casa-muted">{{ __('A voided voucher can no longer be redeemed.') }}</p>
                <x-input-label class="mt-4" for="void_reason" :value="__('Reason')" />
                <x-text-input id="void_reason" name="void_reason" class="mt-2 block w-full" required maxlength="255" />
                <x-input-error class="mt-2" :messages="$errors->get('void_reason')" />
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" class="casa-button-secondary" x-on:click="$dispatch('close')">{{ __('Keep voucher') }}</button>
                    <button type="submit" class="casa-button-primary">{{ __('Void voucher') }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>
