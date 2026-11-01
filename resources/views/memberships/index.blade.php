<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customers') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Memberships') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Gold and Platinum cards, their validity, and customers RFM recommends for a card.') }}</p>
        </div>
        <a href="{{ route($routePrefix.'memberships.create') }}" class="casa-button-primary">{{ __('Issue card') }}</a>
    </x-slot>

    <div class="space-y-6" data-memberships-workspace>
        <x-stat-strip :items="[
            ['label' => __('Active cards'), 'value' => $summary['active'], 'meta' => __('Valid today'), 'tone' => 'green'],
            ['label' => __('Expiring soon'), 'value' => $summary['expiring'], 'meta' => __('Within :days days', ['days' => config('casa.memberships.expiring_soon_days')]), 'tone' => 'gold'],
            ['label' => __('Expired'), 'value' => $summary['expired'], 'meta' => __('Past valid-until date'), 'tone' => 'brown'],
            ['label' => __('Referral credits'), 'value' => $summary['credits'], 'meta' => __('Not yet used'), 'tone' => 'dark'],
        ]" />

        <x-app-card data-membership-recommendations>
            <p class="casa-section-label">{{ __('RFM recommendations') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Recommended for membership') }}</h2>
            <p class="mt-2 text-sm text-casa-muted">{{ __('Customers whose RFM segment recommends a higher card than they hold. Set recommendations on each RFM rule.') }}</p>
            @if ($recommendations->isEmpty())
                <p class="mt-4 text-sm text-casa-muted">{{ __('No customers to recommend right now.') }}</p>
            @else
                <div class="mt-4">
                    <x-table-shell label="{{ __('Scrollable membership recommendations') }}">
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr><th class="px-4 py-3" scope="col">{{ __('Customer') }}</th><th class="px-4 py-3" scope="col">{{ __('RFM segment') }}</th><th class="px-4 py-3" scope="col">{{ __('Scores') }}</th><th class="px-4 py-3" scope="col">{{ __('Current card') }}</th><th class="px-4 py-3" scope="col">{{ __('Recommended') }}</th><th class="px-4 py-3" scope="col"><span class="sr-only">{{ __('Actions') }}</span></th></tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($recommendations as $row)
                                <tr>
                                    <td class="px-4 py-3 font-bold">{{ $row['customer']->display_name }}</td>
                                    <td class="px-4 py-3">{{ $row['segment'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">R{{ $row['scores']['r'] }} · F{{ $row['scores']['f'] }} · M{{ $row['scores']['m'] }}</td>
                                    <td class="px-4 py-3">{{ $row['current']?->name ?? __('None') }}</td>
                                    <td class="px-4 py-3 font-bold">{{ $row['tier']->name }}</td>
                                    <td class="px-4 py-3"><a class="font-bold text-casa-primary" href="{{ route($routePrefix.'memberships.create', ['customer' => $row['customer']->id, 'tier' => $row['tier']->id]) }}">{{ __('Issue card') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                </div>
            @endif
        </x-app-card>

        <div>
            <x-list-toolbar eyebrow="{{ __('Cards') }}" title="{{ __('Membership cards') }}" :count="$memberships->total()" :reset-url="route($routePrefix.'memberships.index')" :active-filters="collect([$search, $status, $tierId])->filter(fn ($value) => filled($value))->count()" :collapsible="true" class="casa-list-toolbar--filters">
                <form method="GET" action="{{ route($routePrefix.'memberships.index') }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_12rem_auto]">
                    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Card number or customer') }}" aria-label="{{ __('Search memberships') }}">
                    <select name="tier" class="casa-input" aria-label="{{ __('Tier') }}"><option value="">{{ __('All tiers') }}</option>@foreach ($tiers as $tier)<option value="{{ $tier->id }}" @selected((int) $tierId === $tier->id)>{{ $tier->name }}</option>@endforeach</select>
                    <select name="status" class="casa-input" aria-label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option>@foreach (['active' => __('Active'), 'expiring' => __('Expiring soon'), 'expired' => __('Expired'), 'cancelled' => __('Cancelled')] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select>
                    <button type="submit" class="casa-button-secondary">{{ __('Apply') }}</button>
                </form>
            </x-list-toolbar>
            <div class="mt-5">
                <x-table-shell label="{{ __('Scrollable membership cards') }}">
                    <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                        <tr><th class="px-4 py-3" scope="col">{{ __('Card') }}</th><th class="px-4 py-3" scope="col">{{ __('Customer') }}</th><th class="px-4 py-3" scope="col">{{ __('Tier') }}</th><th class="px-4 py-3" scope="col">{{ __('Valid until') }}</th><th class="px-4 py-3" scope="col">{{ __('Status') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-casa-border text-sm">
                        @forelse ($memberships as $membership)
                            @php $lifecycle = $membership->lifecycle(); @endphp
                            <tr>
                                <td class="px-4 py-3"><a class="font-bold text-casa-primary" href="{{ route($routePrefix.'memberships.show', $membership) }}">{{ $membership->card_number }}</a></td>
                                <td class="px-4 py-3">{{ $membership->customerProfile?->display_name }}</td>
                                <td class="px-4 py-3">{{ $membership->tier?->name }} <span aria-hidden="true" class="text-casa-gold">{{ str_repeat('★', $membership->tier?->stars ?? 0) }}</span></td>
                                <td class="px-4 py-3">{{ $membership->valid_until->format('M d, Y') }}</td>
                                <td class="px-4 py-3"><x-status-badge :tone="match ($lifecycle) { 'active' => 'success', 'expired' => 'warning', default => 'dark' }">{{ ucfirst($lifecycle) }}</x-status-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-casa-muted">{{ filled($search) || filled($status) || filled($tierId) ? __('No cards match these filters.') : __('No membership cards issued yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </x-table-shell>
                <div class="mt-5">{{ $memberships->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
