<x-app-layout>
    <x-slot name="header">
        <div class="rewards-heading">
            <h1>{{ __('Customer groups and rewards') }}</h1>
            <p>{{ __('Customers are sorted into groups by when they last visited, how often they come and how much they spend. Each group can earn a free add-on.') }}</p>
        </div>
        <a href="{{ route('admin.promotions.index') }}" class="casa-button-secondary">{{ __('Back to customer rewards') }}</a>
    </x-slot>

    @inject('rfmWords', 'App\Services\RfmVocabulary')

    <div class="rewards-workspace rewards-editor" data-rewards-settings-editor>
        <x-input-error class="mb-4" :messages="$errors->get('segment')" />

        <x-app-card>
            <div class="rewards-editor-heading">
                <div>
                    <h2>{{ __('Customer groups') }}</h2>
                    <p>{{ __('Each customer joins the first active group they fit, starting from the top. Use the arrows to change the order. Changes affect future rewards only.') }}</p>
                </div>
                <a href="{{ route('admin.promotions.segments.create') }}" class="casa-button-primary">{{ __('Add group') }}</a>
            </div>

            <div class="mt-4">
                <x-table-shell label="{{ __('Scrollable customer groups') }}">
                    <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                        <tr>
                            <th class="px-4 py-3" scope="col">{{ __('Order') }}</th>
                            <th class="px-4 py-3" scope="col">{{ __('Group') }}</th>
                            <th class="px-4 py-3" scope="col">{{ __('Who it is for and what they get') }}</th>
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
                                <td class="px-4 py-4 min-w-64 leading-6" data-rfm-rule-sentence>{{ $rfmWords->sentence($segment, $segment->addon_code ? ($addonNames[$segment->addon_code] ?? $segment->addon_code) : null, $segment->recommendedMembershipTier?->name) }}</td>
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
                                        <x-confirm-action :action="route('admin.promotions.segments.destroy', $segment)" method="DELETE" :label="__('Delete')" :confirm-title="__('Delete this group?')" :confirm-message="__('Groups that already gave rewards are kept for history; switch those off instead.')" :confirm-button="__('Delete')" tone="danger" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-casa-muted">{{ __('No groups yet. Add a group to start giving rewards.') }}</td></tr>
                        @endforelse
                    </tbody>
                </x-table-shell>
            </div>
            <p class="mt-3 text-sm text-casa-muted">{{ trans_choice(':count customer with paid visits is sorted into groups.|:count customers with paid visits are sorted into groups.', $population) }} {{ __('Customers who fit no active group stay unassigned.') }}</p>
        </x-app-card>

        <x-app-card class="mt-5">
            <details class="rewards-how-it-works" data-rfm-how-it-works>
                <summary>
                    <div>
                        <h2>{{ __('How it works (RFM scoring)') }}</h2>
                        <p>{{ __('The technical method behind the groups, for staff who want the details.') }}</p>
                    </div>
                </summary>
                <p class="mt-4 text-sm leading-6 text-casa-muted">{{ __('Behind the plain names, each customer gets an RFM score from 1 to 5 for Recency (last visit), Frequency (how often) and Monetary (total spent). Each score is a percent rank split into fifths: 5 is the best fifth of customers and 1 the lowest. Tied customers share the lower score. These ranges come from the current customer data and move as it grows.') }}</p>
                <div class="mt-4">
                    <x-table-shell label="{{ __('Scrollable current RFM score ranges') }}">
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <th class="px-4 py-3" scope="col">{{ __('Score') }}</th>
                                @foreach (\App\Models\RfmSegment::METRICS as $metric => $technical)<th class="px-4 py-3" scope="col">{{ __($technical) }} · {{ $rfmWords->metric($metric) }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm" data-rfm-cutoffs>
                            @foreach ([5, 4, 3, 2, 1] as $score)
                                <tr>
                                    <th class="px-4 py-3 text-left" scope="row">{{ $score }}</th>
                                    @foreach (array_keys(\App\Models\RfmSegment::METRICS) as $metric)
                                        <td class="px-4 py-3">
                                            <span class="font-semibold">{{ $rfmWords->level($metric, $score) }}</span>
                                            <span class="block text-casa-muted">{{ $rfmWords->valueHint($metric, $score, $cutoffs) ?? '—' }}</span>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                </div>
                <h3 class="mt-5 text-sm font-bold">{{ __('Groups as score ranges') }}</h3>
                <ul class="mt-2 space-y-1 text-sm" data-rfm-technical-rules>
                    @foreach ($segments as $segment)
                        <li><span class="font-semibold">{{ $segment->name }}:</span> <span class="text-casa-muted">{{ $segment->conditionSummary() }}</span></li>
                    @endforeach
                </ul>
                <div class="rewards-info"><x-reward-icon name="info" /><p>{{ __('Recency counts days since the last paid visit, so fewer days earns a higher score. Frequency counts paid, completed visits and Monetary totals what the customer has paid. The first matching group wins, like a first-hit decision table.') }}</p></div>
            </details>
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
