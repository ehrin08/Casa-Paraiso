<x-app-layout>
    <x-slot name="header">
        <div class="services-heading">
            <h1>{{ __('Services') }}</h1>
            <p>
                {{ __('Manage your treatment catalog, durations and prices.') }}
            </p>
        </div>

        <a href="{{ route('admin.services.create') }}" class="casa-button-primary services-add-button">
            <x-nav-icon name="plus" />
            {{ __('Add service') }}
        </a>
    </x-slot>

    @php
        $activeFilters = collect([$search, $status])->filter(fn ($value) => filled($value))->count();
        $hasCatalog = $activeCount + $inactiveCount > 0;
    @endphp

    <div class="services-workspace" data-services-workspace>
        <x-list-toolbar :reset-url="route('admin.services.index')" :active-filters="$activeFilters" :collapsible="true" class="services-toolbar casa-list-toolbar--filters">
                <form method="GET" action="{{ route('admin.services.index') }}" class="services-filter-form" x-data>
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <div class="services-search">
                        <label for="service-search" class="sr-only">{{ __('Search services') }}</label>
                        <input id="service-search" type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search services…') }}">
                        <button type="submit" class="services-search-submit" aria-label="{{ __('Search services') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="10.75" cy="10.75" r="6.75" />
                                <path stroke-linecap="round" d="m16 16 4.25 4.25" />
                            </svg>
                        </button>
                    </div>
                    <div class="services-status-filter">
                        <label for="service-status">{{ __('Status') }}</label>
                        <select id="service-status" name="status" class="casa-input" x-on:change="$el.form.requestSubmit()">
                            <option value="">{{ __('All statuses') }}</option>
                            <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
                            <option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option>
                        </select>
                    </div>
                    <noscript><button type="submit" class="casa-button-primary">{{ __('Apply filters') }}</button></noscript>
                </form>
        </x-list-toolbar>
        <noscript><style>.services-toolbar [data-filter-panel] { display: block; } .services-toolbar [data-filter-toggle] { display: none; }</style></noscript>

            <section class="services-catalog" aria-label="{{ __('Treatment catalog') }}">
                @if ($services->isEmpty())
                    <x-empty-state
                        :title="$hasCatalog ? __('No services found') : __('No services yet')"
                        :description="$hasCatalog ? __('Try another search or clear the filters to find a treatment.') : __('Add the first treatment with duration and pricing before therapist assignments and appointments are connected.')"
                    >
                        <x-slot name="action">
                            @if ($hasCatalog)
                                <a href="{{ route('admin.services.index') }}" class="casa-button-secondary">{{ __('Clear filters') }}</a>
                            @else
                                <a href="{{ route('admin.services.create') }}" class="casa-button-primary">{{ __('Add service') }}</a>
                            @endif
                        </x-slot>
                    </x-empty-state>
                @else
                    <x-table-shell class="services-table" label="{{ __('Scrollable treatment records') }}">
                        <thead>
                            <tr>
                                @foreach (['name' => __('Treatment'), 'duration' => __('Duration'), 'price' => __('Price'), 'status' => __('Status')] as $column => $label)
                                    @php
                                        $isSorted = $sort === $column;
                                        $nextDirection = $isSorted && $direction === 'asc' ? 'desc' : 'asc';
                                        $sortUrl = route('admin.services.index', array_merge(request()->only(['q', 'status']), ['sort' => $column, 'direction' => $nextDirection]));
                                    @endphp
                                    <th scope="col" aria-sort="{{ $isSorted ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                        <a href="{{ $sortUrl }}">
                                            {{ $label }}
                                            @if ($isSorted)
                                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                                    <path d="{{ $direction === 'asc' ? 'm6 12 4-4 4 4' : 'm6 8 4 4 4-4' }}" />
                                                </svg>
                                            @endif
                                        </a>
                                    </th>
                                @endforeach
                                <th scope="col">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($services as $service)
                                @php
                                    $formattedPrice = number_format((float) $service->price, 2);
                                    if (str_ends_with($formattedPrice, '.00')) {
                                        $formattedPrice = substr($formattedPrice, 0, -3);
                                    }
                                @endphp
                                <tr>
                                    <td class="services-treatment">
                                        <a href="{{ route('admin.services.show', $service) }}">
                                            {{ $service->name }}
                                        </a>
                                        @if (filled($service->description))
                                            <p>{{ $service->description }}</p>
                                        @endif
                                    </td>
                                    <td class="services-duration">{{ $service->duration_minutes }} {{ __('min') }}</td>
                                    <td class="services-price">₱{{ $formattedPrice }}</td>
                                    <td>
                                        <span @class(['services-status', 'services-status--active' => $service->is_active, 'services-status--inactive' => ! $service->is_active])>
                                            <span class="services-status-dot" aria-hidden="true"></span>
                                            {{ $service->is_active ? __('Active') : __('Inactive') }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="services-actions">
                                            <a href="{{ route('admin.services.edit', $service) }}" class="services-action services-action--edit" aria-label="{{ __('Edit :name', ['name' => $service->name]) }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="m16 4 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15l-1 6 5-2" />
                                                </svg>
                                                {{ __('Edit') }}
                                            </a>
                                            <x-confirm-action
                                                :action="route('admin.services.toggle', $service)"
                                                method="PATCH"
                                                :label="$service->is_active ? __('Deactivate') : __('Activate')"
                                                :confirm-title="$service->is_active ? __('Deactivate service?') : __('Activate service?')"
                                                :confirm-message="$service->is_active ? __('Customers will no longer be able to book this service until it is activated again.') : __('Customers will be able to request this service once it is active.')"
                                                :confirm-button="$service->is_active ? __('Deactivate') : __('Activate')"
                                                :button-class="$service->is_active ? 'services-action services-action--deactivate' : 'services-action services-action--activate'"
                                            >
                                                <x-slot name="trigger">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        @if ($service->is_active)
                                                            <circle cx="12" cy="12" r="9" /><path d="m5.6 5.6 12.8 12.8" />
                                                        @else
                                                            <path d="m7 4 13 8-13 8V4Z" />
                                                        @endif
                                                    </svg>
                                                    <span>{{ $service->is_active ? __('Deactivate') : __('Activate') }}</span>
                                                    <span class="sr-only">{{ $service->name }}</span>
                                                </x-slot>
                                            </x-confirm-action>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>

                @endif
            </section>
            {{ $services->links() }}
    </div>
</x-app-layout>
