<x-app-layout>
    <x-slot name="header">
        <div class="rewards-heading">
            <h1>{{ __('Customer rewards') }}</h1>
            <p>{{ __('Manage customer rewards and their history.') }}</p>
        </div>
        <div class="rewards-header-actions">
            <a href="{{ route('admin.promotions.settings.edit') }}" class="casa-button-primary">
                <x-reward-icon name="settings" />
                {{ __('Automatic reward settings') }}
            </a>
        </div>
    </x-slot>

    @php
        $activeFilters = collect([$search, $lifecycle, $dateFrom, $dateTo])->filter(fn ($value) => filled($value))->count();
        $activeGroups = $presets->filter(fn ($preset) => $preset['segment']?->is_active);
        $rewardsEnabled = $activeGroups->isNotEmpty();
    @endphp

    <div class="rewards-workspace" data-rewards-workspace>
        <section class="rewards-metrics" data-metric-grid aria-label="{{ __('Filtered reward totals') }}">
            @foreach ([
                ['key' => 'available', 'label' => __('Available'), 'icon' => 'gift', 'meta' => __('Ready for a customer to use')],
                ['key' => 'reserved', 'label' => __('Reserved'), 'icon' => 'bookmark', 'meta' => __('Attached to a confirmed booking')],
                ['key' => 'used', 'label' => __('Used'), 'icon' => 'check', 'meta' => __('Already redeemed')],
                ['key' => 'expired', 'label' => __('Expired'), 'icon' => 'clock', 'meta' => __('No longer available')],
            ] as $metric)
                <article class="rewards-metric" data-metric-card aria-labelledby="reward-metric-{{ $metric['key'] }}">
                    <span class="rewards-metric-icon"><x-reward-icon :name="$metric['icon']" /></span>
                    <div>
                        <h2 id="reward-metric-{{ $metric['key'] }}">{{ $metric['label'] }}</h2>
                        <p class="rewards-metric-value" data-reward-total="{{ $metric['key'] }}">{{ $summary[$metric['key']] }}</p>
                        <p class="sr-only" data-metric-meta>{{ $metric['meta'] }}</p>
                    </div>
                </article>
            @endforeach
        </section>

        <div class="rewards-columns">
            <section class="rewards-history" aria-labelledby="reward-history-heading">
                <div class="rewards-panel-heading">
                    <h2 id="reward-history-heading">{{ __('Reward history') }}</h2>
                    <a href="{{ route('admin.reports.index', ['type' => 'promotions']) }}" class="rewards-report-link">{{ __('Reward report') }} <span aria-hidden="true">→</span></a>
                </div>
                <x-list-toolbar :active-filters="$activeFilters" :collapsible="true" class="rewards-toolbar casa-list-toolbar--filters">
                    <form method="GET" action="{{ route('admin.promotions.index') }}" class="rewards-filter-form" x-data>
                        <div class="rewards-search">
                            <label for="reward-search" class="sr-only">{{ __('Search customer, email, group, or reward') }}</label>
                            <input id="reward-search" type="search" name="q" value="{{ $search }}" maxlength="255" class="casa-input" placeholder="{{ __('Search customer, email, or reward…') }}">
                            <button type="submit" aria-label="{{ __('Search customer rewards') }}"><x-reward-icon name="search" /></button>
                            <x-input-error :messages="$errors->get('q')" />
                        </div>
                        <div class="rewards-filter-field">
                            <label for="reward-status">{{ __('Status') }}</label>
                            <select id="reward-status" name="lifecycle" class="casa-input" x-on:change="$el.form.requestSubmit()">
                                <option value="">{{ __('All statuses') }}</option>
                                @foreach (['available' => __('Available'), 'reserved' => __('Reserved'), 'used' => __('Used'), 'expired' => __('Expired'), 'dismissed' => __('Dismissed')] as $value => $label)
                                    <option value="{{ $value }}" @selected($lifecycle === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('lifecycle')" />
                        </div>
                        <fieldset class="rewards-date-range">
                            <legend>{{ __('Issued date') }}</legend>
                            <div class="rewards-date-inputs">
                                <div class="rewards-filter-field">
                                    <label for="reward-date-from">{{ __('From') }}</label>
                                    <input id="reward-date-from" type="date" name="date_from" value="{{ $dateFrom }}" class="casa-input" aria-describedby="reward-date-help">
                                    <x-input-error :messages="$errors->get('date_from')" />
                                </div>
                                <div class="rewards-filter-field">
                                    <label for="reward-date-to">{{ __('To') }}</label>
                                    <input id="reward-date-to" type="date" name="date_to" value="{{ $dateTo }}" class="casa-input" aria-describedby="reward-date-help">
                                    <x-input-error :messages="$errors->get('date_to')" />
                                </div>
                            </div>
                        </fieldset>
                        <div class="rewards-filter-footer">
                            <span id="reward-date-help">{{ $dateFrom === '' && $dateTo === '' ? __('All dates · Asia/Manila') : __('Asia/Manila issued dates') }}</span>
                            <div>
                                @if ($activeFilters > 0)
                                    <a href="{{ route('admin.promotions.index') }}" class="rewards-clear">{{ __('Clear filters') }}</a>
                                @endif
                                <button type="submit" class="casa-button-secondary">{{ __('Apply filters') }}</button>
                            </div>
                        </div>
                    </form>
                </x-list-toolbar>
                <noscript><style>.rewards-toolbar [data-filter-panel] { display: block; } .rewards-toolbar [data-filter-toggle] { display: none; }</style></noscript>

                @if ($suggestions->isEmpty())
                    <x-empty-state
                        :title="$activeFilters > 0 ? __('No customer rewards found') : __('No customer rewards yet')"
                        :description="$activeFilters > 0 ? __('Try another search or clear the filters to find a reward.') : __('Rewards appear automatically after eligible completed paid visits.')"
                    >
                        @if ($activeFilters > 0)
                            <x-slot name="action"><a href="{{ route('admin.promotions.index') }}" class="casa-button-secondary">{{ __('Clear filters') }}</a></x-slot>
                        @endif
                    </x-empty-state>
                @else
                    <x-table-shell class="rewards-table" label="{{ __('Scrollable customer reward history') }}">
                        <thead>
                            <tr>
                                @foreach ([__('Customer'), __('Reward'), __('Issued date'), __('Expiry'), __('Status'), __('View')] as $label)
                                    <th scope="col">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($suggestions as $suggestion)
                                @php
                                    $state = $suggestion->lifecycle();
                                    $customer = $suggestion->customerProfile;
                                    $contact = $customer?->display_email ?: ($customer?->is_guest ? $customer->display_phone : null);
                                    $addonName = $addonNames->get($suggestion->addon_code);
                                    $issued = $suggestion->created_at?->timezone('Asia/Manila');
                                    $expiry = $suggestion->expires_at?->timezone('Asia/Manila');
                                @endphp
                                <tr>
                                    <td class="rewards-customer">
                                        <span>{{ $customer?->display_name ?: __('Customer unavailable') }}</span>
                                        @if (filled($contact))<p>{{ $contact }}</p>@endif
                                    </td>
                                    <td class="rewards-offer">{{ $addonName ? __('Complimentary :addon', ['addon' => $addonName]) : $suggestion->suggested_offer }}</td>
                                    <td class="rewards-date">@if ($issued)<time datetime="{{ $issued->toIso8601String() }}">{{ $issued->format('M j, Y') }}</time>@else{{ __('Not recorded') }}@endif</td>
                                    <td class="rewards-date">@if ($expiry)<time datetime="{{ $expiry->toIso8601String() }}">{{ $expiry->format('M j, Y') }}</time>@else{{ __('No expiration') }}@endif</td>
                                    <td><span class="rewards-status rewards-status--{{ $state }}">{{ ucfirst($state) }}</span></td>
                                    <td><a href="{{ route('admin.promotions.show', $suggestion) }}" class="rewards-view" aria-label="{{ __('View reward for :customer', ['customer' => $customer?->display_name ?: __('Customer unavailable')]) }}" title="{{ __('View reward') }}"><x-reward-icon name="eye" /></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                    <div class="rewards-pagination">{{ $suggestions->links() }}</div>
                @endif
            </section>

            <aside class="rewards-settings" aria-labelledby="reward-settings-heading">
                <div class="rewards-panel-heading">
                    <h2 id="reward-settings-heading">{{ __('Automatic reward settings') }}</h2>
                    <a href="{{ route('admin.promotions.settings.edit') }}" class="casa-button-secondary rewards-edit"><x-reward-icon name="pencil" />{{ __('Edit') }}</a>
                </div>
                <div class="rewards-automation">
                    <div class="rewards-automation-heading">
                        <span class="rewards-automation-mark {{ $rewardsEnabled ? 'rewards-automation-mark--enabled' : '' }}" aria-hidden="true"></span>
                        <h3>{{ __('Automatic rewards') }}</h3>
                        <span class="rewards-status {{ $rewardsEnabled ? 'rewards-status--available' : 'rewards-status--dismissed' }}" data-rewards-enabled="{{ $rewardsEnabled ? 'true' : 'false' }}">{{ $rewardsEnabled ? __('Enabled') : __('Disabled') }}</span>
                    </div>
                    <p>{{ $rewardsEnabled ? __('Eligible customers receive a reward after a completed paid visit.') : __('No customer groups are enabled. Edit settings to enable automatic rewards.') }}</p>
                </div>
                <section class="rewards-settings-section" aria-labelledby="reward-details-heading">
                    <h3 id="reward-details-heading">{{ __('Reward details') }}</h3>
                    <dl>
                        <div><dt>{{ __('Reward type') }}</dt><dd>{{ __('Complimentary add-on') }}</dd></div>
                        <div><dt>{{ __('Validity period') }}</dt><dd>{{ $settings->promotion_voucher_validity_days === null ? __('No expiration') : trans_choice(':count day from issue|:count days from issue', $settings->promotion_voucher_validity_days) }}</dd></div>
                        <div><dt>{{ __('Issued after') }}</dt><dd>{{ __('Eligible completed paid visit') }}</dd></div>
                    </dl>
                </section>
                <section class="rewards-settings-section" aria-labelledby="reward-groups-heading">
                    <h3 id="reward-groups-heading">{{ __('Eligible customer groups') }}</h3>
                    <div class="rewards-group-chips">
                        @forelse ($activeGroups as $preset)
                            <span>{{ $preset['name'] }}</span>
                        @empty
                            <p>{{ __('No groups enabled') }}</p>
                        @endforelse
                    </div>
                </section>
                <div class="rewards-info"><x-reward-icon name="info" /><p>{{ __('Rewards use each customer’s latest RFM group. Changes to settings affect future rewards; issued rewards keep their add-on and expiry.') }}</p></div>
            </aside>
        </div>
    </div>
</x-app-layout>
