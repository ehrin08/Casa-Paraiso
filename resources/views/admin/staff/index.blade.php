<x-app-layout>
    <x-slot name="header">
        <div class="therapists-heading">
            <p class="casa-section-label">{{ __('Manage') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Therapists') }}</h1>
            <p class="mt-2 text-sm leading-6 text-casa-muted">
                {{ __('Manage your team of therapists, their services, schedules, and availability.') }}
            </p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.staff-roster.index') }}" class="casa-button-secondary">{{ __('Manage schedule') }}</a>
        @if (auth()->user()->isSuperAdmin())
            <a data-panel-link data-turbo="false" aria-haspopup="dialog" href="{{ route('admin.staff.create') }}" class="casa-button-primary">
                <x-nav-icon name="plus" />
                {{ __('Add therapist') }}
            </a>
        @endif
        </div>
    </x-slot>

    @php
        $activeFilters = collect([$search, $status, $bookable])->filter(fn ($value) => filled($value))->count();
        $selectionQuery = request()->only(['q', 'status', 'bookable', 'sort', 'direction', 'page']);
        $initials = fn ($name) => \Illuminate\Support\Str::of($name)->squish()->explode(' ')
            ->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');
    @endphp

    <div class="therapists-workspace" data-therapists-workspace>
        <x-list-toolbar :count="$staffProfiles->total()" :reset-url="route('admin.staff.index')" :active-filters="$activeFilters" :collapsible="true" class="therapists-toolbar casa-list-toolbar--filters">
            <x-slot name="meta">
                <span class="casa-filter-chip">{{ __(':count active', ['count' => $activeAccountCount]) }}</span>
                <span class="casa-filter-chip">{{ __(':count bookable', ['count' => $bookableCount]) }}</span>
            </x-slot>
            <form method="GET" action="{{ route('admin.staff.index') }}" class="therapists-filter-form">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <div class="therapists-filter-field">
                    <label for="therapist-search" class="sr-only">{{ __('Search therapists') }}</label>
                    <x-filter-search input-id="therapist-search" :value="$search" placeholder="{{ __('Search therapists by name or service…') }}" label="{{ __('Search therapists') }}" />
                </div>
                <div class="therapists-filter-field">
                    <label for="therapist-status">{{ __('Account status') }}</label>
                    <select id="therapist-status" name="status" class="casa-input">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                </div>
                <div class="therapists-filter-field">
                    <label for="therapist-bookable">{{ __('Bookability') }}</label>
                    <select id="therapist-bookable" name="bookable" class="casa-input">
                        <option value="">{{ __('All availability') }}</option>
                        <option value="yes" @selected($bookable === 'yes')>{{ __('Bookable') }}</option>
                        <option value="no" @selected($bookable === 'no')>{{ __('Unavailable') }}</option>
                    </select>
                </div>
                <button type="submit" class="casa-button-primary">{{ __('Apply filters') }}</button>
            </form>
        </x-list-toolbar>
        <noscript><style>.therapists-toolbar [data-filter-panel] { display: block; } .therapists-toolbar [data-filter-toggle] { display: none; }</style></noscript>

        <div class="therapists-grid">
            <section class="therapists-list" aria-label="{{ __('Therapist list') }}">
                @if ($staffProfiles->isEmpty())
                    <x-empty-state
                        :title="$activeFilters ? __('No therapists found') : __('No therapist profiles yet')"
                        :description="$activeFilters ? __('Try another search or clear the filters to find a therapist.') : __('Create therapist accounts before assigning services and working hours.')"
                    >
                        <x-slot name="action">
                            @if ($activeFilters)
                                <a href="{{ route('admin.staff.index') }}" class="casa-button-secondary">{{ __('Clear filters') }}</a>
                            @elseif (auth()->user()->isSuperAdmin())
                                <a data-panel-link data-turbo="false" aria-haspopup="dialog" href="{{ route('admin.staff.create') }}" class="casa-button-primary">{{ __('Add therapist') }}</a>
                            @endif
                        </x-slot>
                    </x-empty-state>
                @else
                    <x-table-shell class="therapists-table" label="{{ __('Scrollable therapist records') }}">
                        <thead>
                            <tr>
                                @foreach (['name' => __('Therapist'), 'services' => __('Services'), 'status' => __('Account status'), 'bookable' => __('Bookability')] as $column => $label)
                                    @php
                                        $isSorted = $sort === $column;
                                        $nextDirection = $isSorted && $direction === 'asc' ? 'desc' : 'asc';
                                        $sortUrl = route('admin.staff.index', array_merge(request()->only(['q', 'status', 'bookable']), ['sort' => $column, 'direction' => $nextDirection]));
                                    @endphp
                                    <th scope="col" aria-sort="{{ $isSorted ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                        <a href="{{ $sortUrl }}">
                                            {{ $label }}
                                            <span aria-hidden="true">{{ $isSorted ? ($direction === 'asc' ? '↑' : '↓') : '↕' }}</span>
                                        </a>
                                    </th>
                                @endforeach
                                <th scope="col">{{ __('Schedule') }}</th>
                                <th scope="col"><span class="sr-only">{{ __('View therapist') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($staffProfiles as $staffProfile)
                                @php
                                    $isSelected = $selectedStaffProfile?->id === $staffProfile->id;
                                    $selectionUrl = route('admin.staff.index', array_merge($selectionQuery, ['selected' => $staffProfile->id, 'tab' => $selectedTab])).'#therapist-detail';
                                @endphp
                                <tr @class(['therapists-row', 'is-selected' => $isSelected]) data-therapist-id="{{ $staffProfile->id }}">
                                    <td>
                                        <a href="{{ $selectionUrl }}" class="therapists-row-link therapists-person" @if ($isSelected) aria-current="true" @endif>
                                            <span class="therapists-avatar" aria-hidden="true">{{ mb_strtoupper($initials($staffProfile->user->name)) }}</span>
                                            <span>{{ $staffProfile->user->name }}</span>
                                        </a>
                                    </td>
                                    <td>{{ trans_choice(':count service|:count services', $staffProfile->services_count) }}</td>
                                    <td>
                                        <span @class(['therapists-pill', 'therapists-pill--active' => $staffProfile->user->is_active, 'therapists-pill--inactive' => ! $staffProfile->user->is_active])>
                                            {{ $staffProfile->user->is_active ? __('Active') : __('Inactive') }}
                                        </span>
                                    </td>
                                    <td>
                                        <span @class(['therapists-pill', 'therapists-pill--active' => $staffProfile->is_bookable, 'therapists-pill--unavailable' => ! $staffProfile->is_bookable])>
                                            {{ $staffProfile->is_bookable ? __('Bookable') : __('Unavailable') }}
                                        </span>
                                    </td>
                                    <td class="therapists-muted">{{ $scheduleSummaries[$staffProfile->id]['daysLabel'] }}</td>
                                    <td>
                                        <a href="{{ $selectionUrl }}" class="therapists-chevron" aria-label="{{ __('View :name', ['name' => $staffProfile->user->name]) }}">
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                    <div class="therapists-list-footer">{{ $staffProfiles->links() }}</div>
                @endif
            </section>

            @if ($selectedStaffProfile)
                <x-therapist-detail
                    :staff-profile="$selectedStaffProfile"
                    :selected-tab="$selectedTab"
                    :selection-query="$selectionQuery"
                    :schedule="$scheduleSummaries[$selectedStaffProfile->id]"
                    :week-start="$scheduleWeekStart"
                    :week-end="$scheduleWeekEnd"
                    :upcoming-exceptions="$upcomingExceptions"
                />
            @else
                <aside id="therapist-detail" class="therapists-detail therapists-empty-detail">
                    <x-nav-icon name="team" class="size-10 text-casa-palm" />
                    <h2 class="text-lg font-extrabold">{{ __('Therapist details') }}</h2>
                    <p class="therapists-muted">{{ __('A therapist profile will appear here when there are matching records.') }}</p>
                </aside>
            @endif
        </div>
    </div>
</x-app-layout>
