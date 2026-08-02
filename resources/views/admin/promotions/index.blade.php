<x-app-layout>
    <x-slot name="header">
        <x-page-heading>
            <div>
                <p class="casa-section-label">{{ __('Customer care') }}</p>
                <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Customer rewards') }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                    {{ __('Choose the complimentary add-on for each customer group. Eligible rewards are issued automatically after completed paid visits.') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.feedback.index') }}" class="casa-button-secondary">{{ __('Feedback') }}</a>
                <a href="{{ route('admin.reports.index', ['type' => 'promotions']) }}" class="casa-button-secondary">{{ __('Reward report') }}</a>
            </div>
        </x-page-heading>
    </x-slot>

    <div class="space-y-6">
        <section class="casa-metric-grid grid gap-3 sm:gap-4 md:grid-cols-2 xl:grid-cols-4" data-metric-grid>
            <x-metric-card label="Available" :value="$summary['available']" meta="Ready for a customer to use" tone="green" />
            <x-metric-card label="Reserved" :value="$summary['reserved']" meta="Attached to a confirmed booking" tone="gold" />
            <x-metric-card label="Used" :value="$summary['used']" meta="Already redeemed" tone="charcoal" />
            <x-metric-card label="Expired" :value="$summary['expired']" meta="No longer available" tone="brown" />
        </section>

        <form method="POST" action="{{ route('admin.promotions.settings.update') }}">
            @csrf
            @method('PATCH')

            <x-app-card>
                <div class="flex flex-wrap items-end justify-between gap-4 border-b border-casa-border pb-5">
                    <div>
                        <p class="casa-section-label">{{ __('Reward setup') }}</p>
                        <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Automatic customer rewards') }}</h2>
                    </div>
                    <div class="w-full sm:w-64">
                        <x-input-label for="promotion_voucher_validity_days" :value="__('Reward validity')" />
                        <select id="promotion_voucher_validity_days" name="promotion_voucher_validity_days" class="casa-input mt-2">
                            <option value="">{{ __('No expiration') }}</option>
                            @foreach (config('casa.customer_rewards.validity_options') as $days)
                                <option value="{{ $days }}" @selected((string) old('promotion_voucher_validity_days', $settings->promotion_voucher_validity_days) === (string) $days)>{{ trans_choice(':count day|:count days', $days) }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('promotion_voucher_validity_days')" />
                    </div>
                </div>

                <p class="mt-4 text-sm leading-6 text-casa-muted">{{ __('Changes affect rewards issued from now on. Rewards already issued keep their existing add-on and expiration date.') }}</p>
                <x-input-error class="mt-3" :messages="$errors->get('groups')" />

                <div class="mt-5 grid gap-4 xl:grid-cols-2">
                    @foreach ($presets as $preset)
                        @php $segment = $preset['segment']; @endphp
                        <section class="rounded-2xl border border-casa-border bg-casa-bg p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="font-display text-lg font-black text-casa-text">{{ $preset['name'] }}</h3>
                                    <p class="mt-2 text-sm leading-6 text-casa-muted">{{ $preset['description'] }}</p>
                                </div>
                                <label class="inline-flex shrink-0 items-center gap-2 text-sm font-bold text-casa-text">
                                    <input type="hidden" name="groups[{{ $preset['key'] }}][is_active]" value="0">
                                    <input type="checkbox" name="groups[{{ $preset['key'] }}][is_active]" value="1" @checked(old("groups.{$preset['key']}.is_active", $segment?->is_active)) class="rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                                    {{ __('Active') }}
                                </label>
                            </div>
                            <div class="mt-4">
                                <x-input-label :for="'reward-'.$preset['key']" :value="__('Complimentary add-on')" />
                                <select id="{{ 'reward-'.$preset['key'] }}" name="groups[{{ $preset['key'] }}][addon_code]" class="casa-input mt-2" required>
                                    @foreach ($addons as $addon)
                                        <option value="{{ $addon->code }}" @selected(old("groups.{$preset['key']}.addon_code", $segment?->addon_code ?: $preset['addon_code']) === $addon->code)>{{ $addon->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get("groups.{$preset['key']}.addon_code")" />
                            </div>
                        </section>
                    @endforeach
                </div>

                <div class="mt-6 flex justify-end border-t border-casa-border pt-5">
                    <button type="submit" class="casa-button-primary">{{ __('Save customer rewards') }}</button>
                </div>
            </x-app-card>
        </form>

        <x-app-card>
            <x-list-toolbar eyebrow="{{ __('Reward activity') }}" title="{{ __('Customer reward history') }}" :count="$suggestions->total()" :reset-url="route('admin.promotions.index')" :active-filters="collect(request()->only(['q', 'lifecycle']))->filter(fn ($value) => filled($value))->count()" :collapsible="true">
                <form method="GET" action="{{ route('admin.promotions.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(12rem,1fr)_auto_auto] lg:min-w-[42rem]">
                    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search customer, group, or reward') }}" aria-label="{{ __('Search customer rewards') }}">
                    <select name="lifecycle" class="casa-input" aria-label="{{ __('Reward status') }}">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (['available' => __('Available'), 'reserved' => __('Reserved'), 'used' => __('Used'), 'dismissed' => __('Dismissed'), 'expired' => __('Expired')] as $value => $label)
                            <option value="{{ $value }}" @selected($lifecycle === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                </form>
            </x-list-toolbar>

            <div class="mt-5">
                @if ($suggestions->isEmpty())
                    <x-empty-state title="{{ __('No customer rewards yet') }}" description="{{ __('Rewards appear automatically after eligible completed paid visits.') }}" />
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <th class="px-4 py-3">{{ __('Customer') }}</th>
                                <th class="px-4 py-3">{{ __('Customer group') }}</th>
                                <th class="px-4 py-3">{{ __('Reward') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3">{{ __('Expires') }}</th>
                                <th class="px-4 py-3">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($suggestions as $suggestion)
                                @php $state = $suggestion->lifecycle(); @endphp
                                <tr class="casa-table-row">
                                    <td class="px-4 py-4 font-semibold text-casa-text">{{ $suggestion->customerProfile?->user?->name }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $suggestion->rfmSegment?->name ?: __('Previous reward rule') }}</td>
                                    <td class="px-4 py-4 font-semibold text-casa-text">{{ $suggestion->addonName() ?: $suggestion->suggested_offer }}</td>
                                    <td class="px-4 py-4"><x-status-badge>{{ ucfirst($state) }}</x-status-badge></td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $suggestion->expires_at?->format('M d, Y') ?: __('No expiration') }}</td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-3">
                                            <a href="{{ route('admin.promotions.show', $suggestion) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('View') }}</a>
                                            @if ($state === 'available')
                                                <x-confirm-action
                                                    :action="route('admin.promotions.dismiss', $suggestion)"
                                                    method="PATCH"
                                                    label="{{ __('Dismiss') }}"
                                                    confirm-title="{{ __('Dismiss this reward?') }}"
                                                    confirm-message="{{ __('The customer will no longer be able to use this reward. This cannot be undone.') }}"
                                                    confirm-button="{{ __('Dismiss reward') }}"
                                                />
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                    <div class="mt-5">{{ $suggestions->links() }}</div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
