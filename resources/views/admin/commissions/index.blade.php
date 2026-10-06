<x-app-layout>
    <x-slot name="header">
        <div class="commissions-heading">
            <h1 class="font-display text-3xl font-black text-casa-text">{{ __('Commissions') }}</h1>
            <p>{{ __('Track and manage therapist commissions for services rendered.') }}</p>
        </div>
        <a href="{{ route('admin.commissions.export', $filters) }}" class="casa-button-primary commissions-export">
            <x-payment-icon name="download" />
            {{ __('Export commissions') }}
        </a>
    </x-slot>

    @php
        $money = fn ($amount) => '₱'.number_format((float) $amount, (float) $amount === floor((float) $amount) ? 0 : 2);
        $activeFilters = count($filters);
        $selectionQuery = array_merge($filters, ['page' => $commissions->currentPage()]);
    @endphp

    <div class="commissions-workspace" data-commissions-workspace>
        <x-stat-strip presentation="cards" class="commissions-metrics" :items="[
            ['label' => __('Pending'), 'value' => $money($totals['pending']), 'icon' => 'commission'],
            ['label' => __('Paid'), 'value' => $money($totals['paid']), 'icon' => 'check'],
            ['label' => __('Net total'), 'value' => $money($totals['net']), 'icon' => 'coins'],
        ]" />

        <x-list-toolbar :active-filters="$activeFilters" :collapsible="true" class="commissions-toolbar casa-list-toolbar--filters">
            <form method="GET" action="{{ route('admin.commissions.index') }}" class="commissions-filter-form">
                <div class="commissions-field">
                    <label for="commission-therapist">{{ __('Therapist') }}</label>
                    <select id="commission-therapist" name="staff_profile_id" class="casa-input">
                        <option value="">{{ __('All therapists') }}</option>
                        @foreach ($staffProfiles as $staff)
                            <option value="{{ $staff->id }}" @selected($staffProfileId === $staff->id)>{{ $staff->user?->name ?: __('Therapist unavailable') }}{{ $staff->trashed() ? ' ('.__('Archived').')' : '' }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('staff_profile_id')" />
                </div>
                <div class="commissions-field">
                    <label for="commission-status">{{ __('Payout status') }}</label>
                    <select id="commission-status" name="status" class="casa-input">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (\App\Models\TherapistCommission::STATUSES as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </div>
                <fieldset class="commissions-date-range">
                    <legend>{{ __('Earned date range') }}</legend>
                    <div class="commissions-date-inputs">
                        <div class="commissions-field">
                            <label for="commission-date-from">{{ __('From') }}</label>
                            <input id="commission-date-from" type="date" name="date_from" value="{{ $dateFrom }}" class="casa-input">
                            <x-input-error :messages="$errors->get('date_from')" />
                        </div>
                        <div class="commissions-field">
                            <label for="commission-date-to">{{ __('Through') }}</label>
                            <input id="commission-date-to" type="date" name="date_to" value="{{ $dateTo }}" class="casa-input">
                            <x-input-error :messages="$errors->get('date_to')" />
                        </div>
                    </div>
                </fieldset>
                <div class="commissions-filter-actions">
                    @if ($activeFilters)
                        <a href="{{ route('admin.commissions.index') }}" class="commissions-clear">{{ __('Clear filters') }}</a>
                    @endif
                    <button type="submit" class="casa-button-primary">{{ __('Apply filters') }}</button>
                </div>
            </form>
        </x-list-toolbar>
        <noscript><style>.commissions-toolbar [data-filter-panel] { display: block; } .commissions-toolbar [data-filter-toggle] { display: none; }</style></noscript>

        <div class="commissions-grid">
            <section class="commissions-records" aria-labelledby="commission-records-heading">
                <div class="commissions-records-heading">
                    <h2 id="commission-records-heading">{{ __('Commission records') }}</h2>
                    <p>{{ trans_choice(':count record|:count records', $commissions->total()) }}</p>
                </div>
                @if ($commissions->isEmpty())
                    <x-empty-state
                        :title="$activeFilters ? __('No commissions found') : __('No commissions yet')"
                        :description="$activeFilters ? __('Try another date range or clear the filters to find a commission.') : __('Fully paid completed services will appear here.')"
                    >
                        @if ($activeFilters)
                            <x-slot name="action">
                                <a href="{{ route('admin.commissions.index') }}" class="casa-button-secondary">{{ __('Clear filters') }}</a>
                            </x-slot>
                        @endif
                    </x-empty-state>
                @else
                    <x-table-shell class="commissions-table" label="{{ __('Scrollable therapist commission records') }}">
                        <thead>
                            <tr>
                                @foreach ([__('Therapist'), __('Earned date'), __('Basis amount'), __('Rate'), __('Commission'), __('Status'), __('View payout')] as $label)
                                    <th scope="col">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($commissions as $commission)
                                @php
                                    $isSelected = $selectedCommission?->id === $commission->id;
                                    $selectionUrl = route('admin.commissions.index', array_merge($selectionQuery, ['selected' => $commission->id])).'#commission-detail';
                                    $earned = $commission->earned_at?->timezone('Asia/Manila');
                                @endphp
                                <tr @class(['commissions-row', 'is-selected' => $isSelected]) data-commission-id="{{ $commission->id }}">
                                    <td class="commissions-therapist">
                                        <a href="{{ $selectionUrl }}" @if ($isSelected) aria-current="true" @endif>{{ $commission->staffProfile?->user?->name ?: __('Therapist unavailable') }}</a>
                                        @if ($commission->commission_type === \App\Models\TherapistCommission::TYPE_ADJUSTMENT)
                                            <span class="commissions-adjustment">{{ __('Adjustment') }}</span>
                                        @endif
                                    </td>
                                    <td class="commissions-earned">
                                        @if ($earned)
                                            <time datetime="{{ $earned->toIso8601String() }}">{{ $earned->format('M j, Y') }}<span>{{ $earned->format('g:i A') }}</span></time>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="commissions-amount">{{ $money($commission->basis_amount) }}</td>
                                    <td>{{ rtrim(rtrim(number_format((float) $commission->commission_rate * 100, 2), '0'), '.') }}%</td>
                                    <td class="commissions-amount commissions-net">{{ $money($commission->commission_amount) }}</td>
                                    <td><span class="commissions-status commissions-status--{{ $commission->status }}">{{ ucfirst($commission->status) }}</span></td>
                                    <td>
                                        <a href="{{ $selectionUrl }}" class="commissions-view" aria-label="{{ __('View payout for :name, commission #:id', ['name' => $commission->staffProfile?->user?->name ?: __('therapist'), 'id' => $commission->id]) }}">
                                            <x-payment-icon name="eye" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                    <div class="commissions-pagination">{{ $commissions->links() }}</div>
                @endif
            </section>

            @if ($selectedCommission)
                <x-commission-detail :commission="$selectedCommission" :return-filters="$selectionQuery" />
            @else
                <aside id="commission-detail" class="commissions-detail commissions-empty-detail">
                    <h2>{{ __('Payout details') }}</h2>
                    <x-payment-icon name="coins" />
                    <p>{{ __('Payout details will appear here when there are matching records.') }}</p>
                </aside>
            @endif
        </div>
    </div>
</x-app-layout>
