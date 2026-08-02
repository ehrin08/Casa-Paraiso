<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Therapist module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Customer lookup') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Find customer contact details, appointment history, and feedback needed for daily operations.') }}
            </p>
        </div>
    </x-slot>

    <x-app-card>
        <x-list-toolbar eyebrow="{{ __('Lookup') }}" title="{{ __('Operational customer list') }}" :count="$customers->total()" :reset-url="route('staff.customers.index')" :active-filters="collect(request()->only(['q']))->filter(fn ($value) => filled($value))->count()" :collapsible="true">
            <form method="GET" action="{{ route('staff.customers.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(12rem,1fr)_auto] lg:min-w-[36rem]">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search customers') }}" aria-label="{{ __('Search customers') }}">
                <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
            </form>
        </x-list-toolbar>

        <div class="mt-5">
            @if ($customers->isEmpty())
                <x-empty-state title="{{ __('No customers found') }}" description="{{ __('Try a different name, phone number, email, or customer code.') }}" />
            @else
                <x-table-shell>
                    <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                        <tr>
                            <x-sortable-th sort="name">{{ __('Customer') }}</x-sortable-th>
                            <th class="px-4 py-3">{{ __('Contact') }}</th>
                            <x-sortable-th sort="appointments">{{ __('Activity') }}</x-sortable-th>
                            <th class="px-4 py-3">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-casa-border text-sm">
                        @foreach ($customers as $customer)
                            <tr class="casa-table-row">
                                <td class="px-4 py-4">
                                    <a href="{{ route('staff.customers.show', $customer) }}" class="font-bold text-casa-text hover:text-casa-primary">
                                        {{ $customer->user->name }}
                                    </a>
                                    <p class="mt-1 text-xs text-casa-muted">{{ $customer->customer_code }}</p>
                                </td>
                                <td class="px-4 py-4 text-casa-muted">
                                    <p>{{ $customer->user->phone ?: __('No phone') }}</p>
                                    <p>{{ $customer->contact_preference ?: __('No preference') }}</p>
                                </td>
                                <td class="px-4 py-4 text-casa-muted">
                                    {{ trans_choice(':count appointment|:count appointments', $customer->appointments_count) }},
                                    {{ trans_choice(':count feedback|:count feedback', $customer->feedback_count) }}
                                </td>
                                <td class="px-4 py-4">
                                    <a href="{{ route('staff.customers.show', $customer) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a>
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
</x-app-layout>
