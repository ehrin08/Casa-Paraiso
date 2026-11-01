<x-app-layout>
    <x-slot name="header">
        <div class="rewards-heading">
            <h1>{{ __('RFM rules and settings') }}</h1>
            <p>{{ __('Customers are scored 1–5 on recency, frequency and spending, then matched to the first rule that fits.') }}</p>
        </div>
        <a href="{{ route('admin.promotions.index') }}" class="casa-button-secondary">{{ __('Back to customer rewards') }}</a>
    </x-slot>

    @php
        $metricLabels = ['r' => __('Recency'), 'f' => __('Frequency'), 'm' => __('Monetary')];
        $formatValue = fn (string $metric, $value) => match ($metric) {
            'r' => trans_choice(':count day|:count days', (int) $value),
            'f' => trans_choice(':count visit|:count visits', (int) $value),
            default => '₱'.number_format((float) $value, fmod((float) $value, 1) === 0.0 ? 0 : 2),
        };
    @endphp

    <div class="rewards-workspace rewards-editor" data-rewards-settings-editor>
        <x-input-error class="mb-4" :messages="$errors->get('segment')" />

        <x-app-card>
            <div class="rewards-editor-heading">
                <div>
                    <h2>{{ __('Segment rules') }}</h2>
                    <p>{{ __('Rules are checked from top to bottom; a customer gets the first active rule they match. Changes affect future rewards only.') }}</p>
                </div>
                <a href="{{ route('admin.promotions.segments.create') }}" class="casa-button-primary">{{ __('Add rule') }}</a>
            </div>

            <div class="mt-4">
                <x-table-shell label="{{ __('Scrollable RFM segment rules') }}">
                    <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                        <tr>
                            <th class="px-4 py-3" scope="col">{{ __('Order') }}</th>
                            <th class="px-4 py-3" scope="col">{{ __('Segment') }}</th>
                            <th class="px-4 py-3" scope="col">{{ __('Conditions') }}</th>
                            <th class="px-4 py-3" scope="col">{{ __('Reward') }}</th>
                            <th class="px-4 py-3" scope="col">{{ __('Customers now') }}</th>
                            <th class="px-4 py-3" scope="col">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-casa-border text-sm">
                        @forelse ($segments as $segment)
                            <tr data-rfm-rule="{{ $segment->id }}">
                                <td class="px-4 py-4 font-bold">{{ $loop->iteration }}</td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.promotions.segments.edit', $segment) }}" class="font-bold text-casa-primary">{{ $segment->name }}</a>
                                    <p class="mt-1 text-xs text-casa-muted">{{ $segment->description }}</p>
                                    <div class="mt-2"><x-status-badge :tone="$segment->is_active ? 'success' : 'dark'">{{ $segment->is_active ? __('Active') : __('Inactive') }}</x-status-badge></div>
                                </td>
                                <td class="px-4 py-4 font-semibold whitespace-nowrap">{{ $segment->conditionSummary() }}</td>
                                <td class="px-4 py-4">
                                    {{ $segment->addon_code ? ($addonNames[$segment->addon_code] ?? $segment->addon_code) : __('No add-on') }}
                                    @if ($segment->recommendedMembershipTier)
                                        <p class="mt-1 text-xs text-casa-muted">{{ __('Recommends :tier card', ['tier' => $segment->recommendedMembershipTier->name]) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">{{ $segment->is_active ? number_format($matches[$segment->id] ?? 0) : '—' }}</td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @unless ($loop->first)
                                            <form method="POST" action="{{ route('admin.promotions.segments.move', $segment) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="direction" value="up">
                                                <button type="submit" class="font-bold text-casa-muted hover:text-casa-primary" aria-label="{{ __('Move :name up', ['name' => $segment->name]) }}">↑</button>
                                            </form>
                                        @endunless
                                        @unless ($loop->last)
                                            <form method="POST" action="{{ route('admin.promotions.segments.move', $segment) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="direction" value="down">
                                                <button type="submit" class="font-bold text-casa-muted hover:text-casa-primary" aria-label="{{ __('Move :name down', ['name' => $segment->name]) }}">↓</button>
                                            </form>
                                        @endunless
                                        <a href="{{ route('admin.promotions.segments.edit', $segment) }}" class="font-bold text-casa-primary">{{ __('Edit') }}</a>
                                        <x-confirm-action :action="route('admin.promotions.segments.destroy', $segment)" method="DELETE" :label="__('Delete')" :confirm-title="__('Delete this rule?')" :confirm-message="__('Rules that already issued rewards are kept for history; deactivate those instead.')" :confirm-button="__('Delete')" tone="danger" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-casa-muted">{{ __('No rules yet. Add a rule to start issuing rewards.') }}</td></tr>
                        @endforelse
                    </tbody>
                </x-table-shell>
            </div>
            <p class="mt-3 text-sm text-casa-muted">{{ trans_choice(':count customer with paid visits is scored.|:count customers with paid visits are scored.', $population) }} {{ __('Customers who match no active rule stay unassigned.') }}</p>
        </x-app-card>

        <x-app-card class="mt-5">
            <div class="rewards-editor-heading">
                <div>
                    <h2>{{ __('How scoring works') }}</h2>
                    <p>{{ __('Each score is a percent rank split into fifths: 5 is the best fifth of customers and 1 the lowest. Tied customers share the lower score. These ranges come from the current customer data and move as it grows.') }}</p>
                </div>
            </div>
            <div class="mt-4">
                <x-table-shell label="{{ __('Scrollable current RFM score ranges') }}">
                    <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                        <tr>
                            <th class="px-4 py-3" scope="col">{{ __('Score') }}</th>
                            @foreach ($metricLabels as $label)<th class="px-4 py-3" scope="col">{{ $label }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-casa-border text-sm" data-rfm-cutoffs>
                        @foreach ([5, 4, 3, 2, 1] as $score)
                            <tr>
                                <th class="px-4 py-3 text-left" scope="row">{{ $score }}</th>
                                @foreach (array_keys($metricLabels) as $metric)
                                    @php $range = $cutoffs[$metric][$score] ?? null; @endphp
                                    <td class="px-4 py-3">
                                        @if ($range === null)
                                            <span class="text-casa-muted">—</span>
                                        @elseif ($range['min'] == $range['max'])
                                            {{ $formatValue($metric, $range['min']) }}
                                        @else
                                            {{ $formatValue($metric, $range['min']) }} – {{ $formatValue($metric, $range['max']) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </x-table-shell>
            </div>
            <div class="rewards-info"><x-reward-icon name="info" /><p>{{ __('Recency counts days since the last paid visit, so fewer days earns a higher score. Frequency counts paid, completed visits and Monetary totals what the customer has paid.') }}</p></div>
        </x-app-card>

        <form method="POST" action="{{ route('admin.promotions.settings.update') }}" class="mt-5">
            @csrf
            @method('PATCH')
            <x-app-card>
                <div class="rewards-editor-heading">
                    <div>
                        <h2>{{ __('Reward validity') }}</h2>
                        <p>{{ __('Issued rewards keep their existing add-on and expiration date.') }}</p>
                    </div>
                    <div class="rewards-filter-field">
                        <x-input-label for="promotion_voucher_validity_days" :value="__('Reward validity')" />
                        <select id="promotion_voucher_validity_days" name="promotion_voucher_validity_days" class="casa-input">
                            <option value="">{{ __('No expiration') }}</option>
                            @foreach (config('casa.customer_rewards.validity_options') as $days)
                                <option value="{{ $days }}" @selected((string) old('promotion_voucher_validity_days', $settings->promotion_voucher_validity_days) === (string) $days)>{{ trans_choice(':count day|:count days', $days) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('promotion_voucher_validity_days')" />
                    </div>
                </div>
                <div class="rewards-editor-actions">
                    <button type="submit" class="casa-button-primary">{{ __('Save customer rewards') }}</button>
                </div>
            </x-app-card>
        </form>
    </div>
</x-app-layout>
