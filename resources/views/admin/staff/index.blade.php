<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin module') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Team & Services') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Manage therapists, treatment eligibility, service pricing, and availability foundations in one workspace.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.services.create') }}" class="casa-button-secondary">{{ __('Add service') }}</a>
            @if(auth()->user()->isSuperAdmin())<a href="{{ route('admin.staff.create') }}" class="casa-button-primary">{{ __('Add therapist') }}</a>@endif
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-app-card>
            <x-list-toolbar eyebrow="{{ __('Team') }}" title="{{ __('Therapist profiles') }}" :count="$staffProfiles->total()" :reset-url="route('admin.staff.index')" :active-filters="collect(request()->only(['q', 'status', 'bookable']))->filter(fn ($value) => filled($value))->count()" :collapsible="true">
                <x-slot name="meta">
                    <span class="casa-filter-chip">{{ __(':count active', ['count' => $activeAccountCount]) }}</span>
                    <span class="casa-filter-chip">{{ __(':count bookable', ['count' => $bookableCount]) }}</span>
                    <span class="casa-filter-chip">{{ __(':count services', ['count' => $activeServiceCount]) }}</span>
                </x-slot>
                <form method="GET" action="{{ route('admin.staff.index') }}" class="casa-filter-grid sm:grid-cols-[minmax(12rem,1fr)_auto_auto_auto] lg:min-w-[48rem]">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <input type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search therapists') }}" aria-label="{{ __('Search therapists') }}">
                    <select name="status" class="casa-input">
                        <option value="">{{ __('All accounts') }}</option>
                        <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                    <select name="bookable" class="casa-input">
                        <option value="">{{ __('Any bookable') }}</option>
                        <option value="yes" @selected($bookable === 'yes')>{{ __('Bookable') }}</option>
                        <option value="no" @selected($bookable === 'no')>{{ __('Not bookable') }}</option>
                    </select>
                    <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
                </form>
            </x-list-toolbar>

            <div class="mt-5">
                @if ($staffProfiles->isEmpty())
                    <x-empty-state
                        title="{{ __('No therapist profiles yet') }}"
                        description="{{ __('Create therapist accounts before assigning services, schedules, and appointment responsibilities.') }}"
                    >
                        <x-slot name="action">
                            @if(auth()->user()->isSuperAdmin())<a href="{{ route('admin.staff.create') }}" class="casa-button-primary">{{ __('Add therapist') }}</a>@endif
                        </x-slot>
                    </x-empty-state>
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <x-sortable-th sort="name">{{ __('Therapist') }}</x-sortable-th>
                                <x-sortable-th sort="position">{{ __('Role') }}</x-sortable-th>
                                <x-sortable-th sort="services">{{ __('Services') }}</x-sortable-th>
                                <x-sortable-th sort="appointments">{{ __('Appointments') }}</x-sortable-th>
                                <x-sortable-th sort="status">{{ __('Status') }}</x-sortable-th>
                                <th class="px-4 py-3">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($staffProfiles as $staffProfile)
                                <tr class="casa-table-row">
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.staff.show', $staffProfile) }}" class="font-bold text-casa-text hover:text-casa-primary">
                                            {{ $staffProfile->user->name }}
                                        </a>
                                        <p class="mt-1 text-xs text-casa-muted">{{ $staffProfile->user->email }}</p>
                                        <x-status-badge class="mt-2">{{ ucfirst($staffProfile->staff_type) }}</x-status-badge>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">
                                        <p class="font-semibold text-casa-text">{{ $staffProfile->position ?: __('Therapist') }}</p>
                                        <p class="mt-1 text-xs">{{ $staffProfile->specialization ?: __('No specialization yet') }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            @forelse ($staffProfile->services as $service)
                                                <x-status-badge :tone="$service->is_active ? 'success' : 'dark'">{{ $service->name }}</x-status-badge>
                                            @empty
                                                <span class="text-casa-muted">{{ __('No services') }}</span>
                                            @endforelse
                                        </div>
                                        <p class="mt-2 text-xs text-casa-muted">{{ trans_choice(':count service|:count services', $staffProfile->services_count) }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">{{ trans_choice(':count appointment|:count appointments', $staffProfile->appointments_count) }}</td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <x-status-badge :tone="$staffProfile->user->is_active ? 'success' : 'dark'">
                                                {{ $staffProfile->user->is_active ? __('Active') : __('Inactive') }}
                                            </x-status-badge>
                                            <x-status-badge :tone="$staffProfile->is_bookable ? 'success' : 'warning'">
                                                {{ $staffProfile->is_bookable ? __('Bookable') : __('Not bookable') }}
                                            </x-status-badge>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.staff.edit', $staffProfile) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Edit') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>

                    <div class="mt-5">
                        {{ $staffProfiles->links() }}
                    </div>
                @endif
            </div>
        </x-app-card>

        <x-app-card id="service-catalog">
            <div class="flex flex-col gap-3 border-b border-casa-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="casa-section-label">{{ __('Catalog') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Service catalog') }}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                        {{ __('Keep treatment duration, pricing, and booking visibility close to therapist eligibility decisions.') }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="casa-filter-chip">{{ trans_choice(':count service|:count services', $serviceCatalog->total()) }}</span>
                    <a href="{{ route('admin.services.create') }}" class="casa-button-primary">{{ __('Add service') }}</a>
                </div>
            </div>

            <div class="mt-4">
                @if ($serviceCatalog->isEmpty())
                    <x-empty-state
                        title="{{ __('No services yet') }}"
                        description="{{ __('Add the first treatment before assigning therapist eligibility and appointment workflows.') }}"
                    >
                        <x-slot name="action">
                            <a href="{{ route('admin.services.create') }}" class="casa-button-primary">{{ __('Add service') }}</a>
                        </x-slot>
                    </x-empty-state>
                @else
                    <x-table-shell>
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <th class="px-4 py-3">{{ __('Service') }}</th>
                                <th class="px-4 py-3">{{ __('Duration') }}</th>
                                <th class="px-4 py-3">{{ __('Price') }}</th>
                                <th class="px-4 py-3">{{ __('Usage') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($serviceCatalog as $service)
                                <tr class="casa-table-row">
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.services.show', $service) }}" class="font-bold text-casa-text hover:text-casa-primary">
                                            {{ $service->name }}
                                        </a>
                                        <p class="mt-1 text-xs text-casa-muted">{{ $service->slug }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $service->duration_minutes }} {{ __('min') }}</td>
                                    <td class="px-4 py-4 font-semibold text-casa-text">PHP {{ number_format((float) $service->price, 2) }}</td>
                                    <td class="px-4 py-4 text-casa-muted">
                                        {{ trans_choice(':count therapist|:count therapists', $service->staff_profiles_count) }},
                                        {{ trans_choice(':count appointment|:count appointments', $service->appointments_count) }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-status-badge :tone="$service->is_active ? 'success' : 'dark'">
                                            {{ $service->is_active ? __('Active') : __('Inactive') }}
                                        </x-status-badge>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap gap-3">
                                            <a href="{{ route('admin.services.edit', $service) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Edit') }}</a>
                                            <x-confirm-action
                                                :action="route('admin.services.toggle', $service)"
                                                method="PATCH"
                                                :label="$service->is_active ? __('Deactivate') : __('Activate')"
                                                :confirm-title="$service->is_active ? __('Deactivate service?') : __('Activate service?')"
                                                :confirm-message="$service->is_active ? __('Customers will no longer be able to book this service until it is activated again.') : __('Customers will be able to request this service once it is active.')"
                                                :confirm-button="$service->is_active ? __('Deactivate') : __('Activate')"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>

                    <div class="mt-4">
                        {{ $serviceCatalog->links() }}
                    </div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
