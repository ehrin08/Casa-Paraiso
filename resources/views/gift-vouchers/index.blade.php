<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Payments') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Gift vouchers') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Record vouchers sold at the counter and track them until they are redeemed, expire, or are voided.') }}</p>
        </div>
        <a href="{{ route($routePrefix.'gift-vouchers.create') }}" class="casa-button-primary">{{ __('Sell voucher') }}</a>
    </x-slot>

    @php $peso = fn ($value) => '₱'.number_format((float) $value, fmod((float) $value, 1) === 0.0 ? 0 : 2); @endphp

    <div class="space-y-6" data-gift-vouchers-workspace>
        <x-stat-strip :items="[
            ['label' => __('Outstanding'), 'value' => $peso($summary['outstanding']), 'meta' => __('Unredeemed and unexpired'), 'tone' => 'green'],
            ['label' => __('Redeemed'), 'value' => $peso($summary['redeemed']), 'meta' => __('Applied to payments'), 'tone' => 'brown'],
            ['label' => __('Forfeited'), 'value' => $peso($summary['forfeited']), 'meta' => __('Unused balance at redemption'), 'tone' => 'gold'],
            ['label' => __('Expired'), 'value' => $peso($summary['expired']), 'meta' => __('Never redeemed'), 'tone' => 'dark'],
        ]" />

        <div>
            <x-list-toolbar eyebrow="{{ __('Vouchers') }}" title="{{ __('Voucher list') }}" :count="$vouchers->total()" :reset-url="route($routePrefix.'gift-vouchers.index')" :active-filters="collect([$search, $status])->filter(fn ($value) => filled($value))->count()" :collapsible="true" class="casa-list-toolbar--filters">
                <form method="GET" action="{{ route($routePrefix.'gift-vouchers.index') }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto]">
                    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Code or purchaser') }}" aria-label="{{ __('Search gift vouchers') }}">
                    <select name="status" class="casa-input" aria-label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option>@foreach (['active' => __('Active'), 'redeemed' => __('Redeemed'), 'expired' => __('Expired'), 'void' => __('Void')] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select>
                    <button type="submit" class="casa-button-secondary">{{ __('Apply') }}</button>
                </form>
            </x-list-toolbar>
            <div class="mt-5">
                <x-table-shell label="{{ __('Scrollable gift vouchers') }}">
                    <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                        <tr><th class="px-4 py-3" scope="col">{{ __('Code') }}</th><th class="px-4 py-3" scope="col">{{ __('Value') }}</th><th class="px-4 py-3" scope="col">{{ __('Purchaser') }}</th><th class="px-4 py-3" scope="col">{{ __('Expires') }}</th><th class="px-4 py-3" scope="col">{{ __('Status') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-casa-border text-sm">
                        @forelse ($vouchers as $voucher)
                            @php $lifecycle = $voucher->lifecycle(); @endphp
                            <tr>
                                <td class="px-4 py-3"><a class="font-bold text-casa-primary" href="{{ route($routePrefix.'gift-vouchers.show', $voucher) }}">{{ $voucher->code }}</a></td>
                                <td class="px-4 py-3 font-semibold">{{ $peso($voucher->value) }}</td>
                                <td class="px-4 py-3">{{ $voucher->purchaser?->display_name ?? $voucher->purchaser_name ?? __('Walk-in') }}</td>
                                <td class="px-4 py-3">{{ $voucher->expires_at?->format('M d, Y') ?? __('No expiry') }}</td>
                                <td class="px-4 py-3"><x-status-badge :tone="match ($lifecycle) { 'active' => 'success', 'redeemed' => 'info', 'expired' => 'warning', default => 'dark' }">{{ ucfirst($lifecycle) }}</x-status-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-casa-muted">{{ filled($search) || filled($status) ? __('No vouchers match these filters.') : __('No gift vouchers recorded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </x-table-shell>
                <div class="mt-5">{{ $vouchers->links() }}</div>
            </div>
        </div>

        @if (request()->user()->isAdmin())
            <form method="POST" action="{{ route('admin.gift-vouchers.settings.update') }}" data-gift-voucher-settings>
                @csrf
                @method('PATCH')
                <x-app-card>
                    <p class="casa-section-label">{{ __('Settings') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Voucher rules') }}</h2>
                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="gift_voucher_denominations" :value="__('Denominations (PHP)')" />
                            <x-text-input id="gift_voucher_denominations" name="gift_voucher_denominations" class="mt-2 block w-full" :value="old('gift_voucher_denominations', implode(', ', $settings->giftVoucherDenominations()))" required />
                            <x-input-error class="mt-2" :messages="$errors->get('gift_voucher_denominations')" />
                        </div>
                        <div>
                            <x-input-label for="gift_voucher_default_expiry_months" :value="__('Default validity (months)')" />
                            <x-text-input id="gift_voucher_default_expiry_months" name="gift_voucher_default_expiry_months" type="number" min="1" max="60" class="mt-2 block w-full" :value="old('gift_voucher_default_expiry_months', $settings->gift_voucher_default_expiry_months)" />
                            <x-input-error class="mt-2" :messages="$errors->get('gift_voucher_default_expiry_months')" />
                        </div>
                        <label class="flex items-start gap-3 text-sm sm:col-span-2">
                            <input type="hidden" name="gift_voucher_combinable" value="0">
                            <input type="checkbox" name="gift_voucher_combinable" value="1" @checked(old('gift_voucher_combinable', $settings->gift_voucher_combinable)) class="mt-1 rounded border-casa-border text-casa-primary focus:ring-casa-palm">
                            <span><strong>{{ __('Allow vouchers with member discounts and promos') }}</strong><br><span class="text-casa-muted">{{ __('Off by default: the printed terms say vouchers cannot be combined with other discounts, promos, or packages.') }}</span></span>
                        </label>
                    </div>
                    <div class="mt-5 flex justify-end border-t border-casa-border pt-5"><button type="submit" class="casa-button-primary">{{ __('Save voucher rules') }}</button></div>
                </x-app-card>
            </form>
        @endif
    </div>
</x-app-layout>
