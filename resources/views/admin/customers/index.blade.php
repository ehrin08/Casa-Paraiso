<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Customers') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Review customer records, visit history, payments, feedback, and promotion context.') }}
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-app-card>
            <x-list-toolbar eyebrow="{{ __('Customer records') }}" title="{{ __('Profile list') }}" :count="$customers->total()" :reset-url="route('admin.customers.index')" :active-filters="collect(request()->only(['q', 'status']))->filter(fn ($value) => filled($value))->count()" :collapsible="true">
                <form method="GET" action="{{ route('admin.customers.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(12rem,1fr)_auto_auto] lg:min-w-[42rem]">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search customers') }}" aria-label="{{ __('Search customers') }}">
                    <select name="status" class="casa-input">
                        <option value="">{{ __('All accounts') }}</option>
                        <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                    <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                </form>
            </x-list-toolbar>

            <div class="mt-5">
                @if ($customers->isEmpty())
                    <x-empty-state
                        title="{{ __('No customers found') }}"
                        description="{{ __('Customer profiles appear after registration or when seeded for demo workflows.') }}"
                    />
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <x-sortable-th sort="name">{{ __('Customer') }}</x-sortable-th>
                                <th class="px-4 py-3">{{ __('Contact') }}</th>
                                <x-sortable-th sort="appointments">{{ __('History') }}</x-sortable-th>
                                <x-sortable-th sort="preference">{{ __('Preference') }}</x-sortable-th>
                                <th class="px-4 py-3">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($customers as $customer)
                                <tr class="casa-table-row">
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.customers.show', $customer) }}" class="font-bold text-casa-text hover:text-casa-primary">
                                            {{ $customer->user->name }}
                                        </a>
                                        <p class="mt-1 text-xs text-casa-muted">{{ $customer->customer_code }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">
                                        <p>{{ $customer->user->email }}</p>
                                        <p>{{ $customer->user->phone ?: __('No phone') }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">
                                        {{ trans_choice(':count appointment|:count appointments', $customer->appointments_count) }},
                                        {{ trans_choice(':count transaction|:count transactions', $customer->transactions_count) }},
                                        {{ trans_choice(':count feedback|:count feedback', $customer->feedback_count) }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-status-badge>{{ $customer->contact_preference ?: __('Not set') }}</x-status-badge>
                                    </td>
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.customers.show', $customer) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">
                                            {{ __('Open') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>

                    <div class="mt-5">
                        {{ $customers->links() }}
                    </div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
