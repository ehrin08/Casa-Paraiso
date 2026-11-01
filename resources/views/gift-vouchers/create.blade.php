<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Gift vouchers') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Sell gift voucher') }}</h1>
        </div>
        <a href="{{ route($routePrefix.'gift-vouchers.index') }}" class="casa-button-secondary">{{ __('All vouchers') }}</a>
    </x-slot>

    <form method="POST" action="{{ route($routePrefix.'gift-vouchers.store') }}" class="mx-auto max-w-3xl" x-data="{ value: @js(old('value', $denominations[0] ?? '')) }" data-gift-voucher-form>
        @csrf
        <x-app-card>
            <fieldset>
                <legend class="text-sm font-semibold">{{ __('Value') }}</legend>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($denominations as $amount)
                        <button type="button" class="casa-button-secondary" x-on:click="value = {{ $amount }}" x-bind:aria-pressed="Number(value) === {{ $amount }}" x-bind:class="Number(value) === {{ $amount }} && 'ring-2 ring-casa-palm'">₱{{ number_format($amount) }}</button>
                    @endforeach
                </div>
                <div class="mt-3 max-w-xs">
                    <x-input-label for="value" :value="__('Amount (PHP)')" />
                    <input id="value" name="value" type="number" min="1" step="0.01" class="casa-input mt-2 block w-full" x-model="value" required>
                    <x-input-error class="mt-2" :messages="$errors->get('value')" />
                </div>
            </fieldset>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="code" :value="__('Voucher code (optional)')" />
                    <x-text-input id="code" name="code" class="mt-2 block w-full uppercase" :value="old('code')" maxlength="60" autocomplete="off" />
                    <p class="mt-1 text-xs text-casa-muted">{{ __('Scan or type the barcode number printed on the voucher. Leave blank to generate one.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('code')" />
                </div>
                <div>
                    <x-input-label for="expires_at" :value="__('Valid until')" />
                    <x-text-input id="expires_at" name="expires_at" type="date" class="mt-2 block w-full" :value="old('expires_at', $expiresAt)" />
                    <x-input-error class="mt-2" :messages="$errors->get('expires_at')" />
                </div>
                <div>
                    <x-input-label for="purchaser_customer_profile_id" :value="__('Purchaser (customer)')" />
                    <select id="purchaser_customer_profile_id" name="purchaser_customer_profile_id" class="casa-input mt-2">
                        <option value="">{{ __('Not a registered customer') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('purchaser_customer_profile_id') === $customer->id)>{{ $customer->display_name }} ({{ $customer->customer_code }})</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('purchaser_customer_profile_id')" />
                </div>
                <div>
                    <x-input-label for="purchaser_name" :value="__('Or purchaser name')" />
                    <x-text-input id="purchaser_name" name="purchaser_name" class="mt-2 block w-full" :value="old('purchaser_name')" maxlength="255" />
                    <x-input-error class="mt-2" :messages="$errors->get('purchaser_name')" />
                </div>
                <div>
                    <x-input-label for="sale_payment_method" :value="__('Paid with')" />
                    <select id="sale_payment_method" name="sale_payment_method" class="casa-input mt-2" required>
                        @foreach (\App\Models\Transaction::PAYMENT_METHODS as $method)
                            <option value="{{ $method }}" @selected(old('sale_payment_method', \App\Models\ApplicationSetting::current()->default_payment_method) === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('sale_payment_method')" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="notes" :value="__('Notes')" />
                    <textarea id="notes" name="notes" rows="2" class="casa-input mt-2 block w-full">{{ old('notes') }}</textarea>
                </div>
            </div>
            <div class="rewards-info"><p>{{ __('Vouchers are one-time use and non-refundable. When redeemed, any amount above the voucher is paid separately and any unused balance is forfeited.') }}</p></div>
            <div class="mt-5 flex flex-col-reverse gap-3 border-t border-casa-border pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route($routePrefix.'gift-vouchers.index') }}" class="casa-button-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="casa-button-primary">{{ __('Record voucher') }}</button>
            </div>
        </x-app-card>
    </form>
</x-app-layout>
