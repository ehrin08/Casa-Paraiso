<x-app-layout>
    <x-slot name="header">
        <div class="services-heading">
            <h1>{{ __('Add-ons') }}</h1>
            <p>
                {{ __('Manage the booking extras, durations and prices offered with treatments.') }}
            </p>
        </div>

        <a data-panel-link data-turbo="false" aria-haspopup="dialog" href="{{ route('admin.addons.create') }}" class="casa-button-primary services-add-button">
            <x-nav-icon name="plus" />
            {{ __('Add add-on') }}
        </a>
    </x-slot>

    @php
        $activeFilters = collect([$search, $status])->filter(fn ($value) => filled($value))->count();
        $hasCatalog = $activeCount + $inactiveCount > 0;
    @endphp

    <div class="services-workspace" data-services-workspace>
        <x-list-toolbar :reset-url="route('admin.addons.index')" :active-filters="$activeFilters" :collapsible="true" class="services-toolbar casa-list-toolbar--filters">
                <form method="GET" action="{{ route('admin.addons.index') }}" class="services-filter-form" x-data>
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <div class="services-search">
                        <label for="addon-search" class="sr-only">{{ __('Search add-ons') }}</label>
                        <input id="addon-search" type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search add-ons…') }}">
                        <button type="submit" class="services-search-submit" aria-label="{{ __('Search add-ons') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="10.75" cy="10.75" r="6.75" />
                                <path stroke-linecap="round" d="m16 16 4.25 4.25" />
                            </svg>
                        </button>
                    </div>
                    <div class="services-status-filter">
                        <label for="addon-status">{{ __('Status') }}</label>
                        <select id="addon-status" name="status" class="casa-input" x-on:change="$el.form.requestSubmit()">
                            <option value="">{{ __('All statuses') }}</option>
                            <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
                            <option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option>
                        </select>
                    </div>
                    <noscript><button type="submit" class="casa-button-primary">{{ __('Apply filters') }}</button></noscript>
                </form>
        </x-list-toolbar>
        <noscript><style>.services-toolbar [data-filter-panel] { display: block; } .services-toolbar [data-filter-toggle] { display: none; }</style></noscript>

            <section class="services-catalog" aria-label="{{ __('Add-on catalog') }}">
                @if ($addons->isEmpty())
                    <x-empty-state
                        :title="$hasCatalog ? __('No add-ons found') : __('No add-ons yet')"
                        :description="$hasCatalog ? __('Try another search or clear the filters to find an add-on.') : __('Add the first booking extra with duration and pricing so it can be offered on appointments.')"
                    >
                        <x-slot name="action">
                            @if ($hasCatalog)
                                <a href="{{ route('admin.addons.index') }}" class="casa-button-secondary">{{ __('Clear filters') }}</a>
                            @else
                                <a data-panel-link data-turbo="false" aria-haspopup="dialog" href="{{ route('admin.addons.create') }}" class="casa-button-primary">{{ __('Add add-on') }}</a>
                            @endif
                        </x-slot>
                    </x-empty-state>
                @else
                    <x-table-shell class="services-table" label="{{ __('Scrollable add-on records') }}">
                        <thead>
                            <tr>
                                @foreach (['name' => __('Add-on'), 'duration' => __('Duration'), 'price' => __('Price'), 'status' => __('Status')] as $column => $label)
                                    @php
                                        $isSorted = $sort === $column;
                                        $nextDirection = $isSorted && $direction === 'asc' ? 'desc' : 'asc';
                                        $sortUrl = route('admin.addons.index', array_merge(request()->only(['q', 'status']), ['sort' => $column, 'direction' => $nextDirection]));
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
                            @foreach ($addons as $addon)
                                @php
                                    $formattedPrice = number_format((float) $addon->price, 2);
                                    if (str_ends_with($formattedPrice, '.00')) {
                                        $formattedPrice = substr($formattedPrice, 0, -3);
                                    }
                                @endphp
                                <tr>
                                    <td class="services-treatment">
                                        <a href="{{ route('admin.addons.show', $addon) }}">
                                            {{ $addon->name }}
                                        </a>
                                        <p>{{ $addon->code }}</p>
                                        @if (filled($addon->description))
                                            <p>{{ $addon->description }}</p>
                                        @endif
                                    </td>
                                    <td class="services-duration">{{ $addon->duration_minutes }} {{ __('min') }}</td>
                                    <td class="services-price">₱{{ $formattedPrice }}</td>
                                    <td>
                                        <span @class(['services-status', 'services-status--active' => $addon->is_active, 'services-status--inactive' => ! $addon->is_active])>
                                            <span class="services-status-dot" aria-hidden="true"></span>
                                            {{ $addon->is_active ? __('Active') : __('Inactive') }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="services-actions">
                                            <a data-panel-link data-turbo="false" aria-haspopup="dialog" href="{{ route('admin.addons.edit', $addon) }}" class="services-action services-action--edit" aria-label="{{ __('Edit :name', ['name' => $addon->name]) }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="m16 4 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15l-1 6 5-2" />
                                                </svg>
                                                {{ __('Edit') }}
                                            </a>
                                            <x-confirm-action
                                                :action="route('admin.addons.toggle', $addon)"
                                                method="PATCH"
                                                :label="$addon->is_active ? __('Deactivate') : __('Activate')"
                                                :confirm-title="$addon->is_active ? __('Deactivate add-on?') : __('Activate add-on?')"
                                                :confirm-message="$addon->is_active ? __('It will no longer be offered for new bookings until it is activated again.') : __('It will be available for new bookings once it is active.')"
                                                :confirm-button="$addon->is_active ? __('Deactivate') : __('Activate')"
                                                :button-class="$addon->is_active ? 'services-action services-action--deactivate' : 'services-action services-action--activate'"
                                                :tone="$addon->is_active ? 'danger' : 'default'"
                                            >
                                                <x-slot name="trigger">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        @if ($addon->is_active)
                                                            <circle cx="12" cy="12" r="9" /><path d="m5.6 5.6 12.8 12.8" />
                                                        @else
                                                            <path d="m7 4 13 8-13 8V4Z" />
                                                        @endif
                                                    </svg>
                                                    <span>{{ $addon->is_active ? __('Deactivate') : __('Activate') }}</span>
                                                    <span class="sr-only">{{ $addon->name }}</span>
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
            {{ $addons->links() }}
    </div>
</x-app-layout>
