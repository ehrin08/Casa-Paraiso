<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Super administrator') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('System errors') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Review grouped application failures and the individual requests that triggered them.') }}</p>
        </div>
    </x-slot>

    <x-app-card>
        <x-list-toolbar eyebrow="{{ __('Error inbox') }}" title="{{ __('Captured responses') }}" :count="$reports->total()" :reset-url="route('admin.system-errors.index')" :active-filters="collect($filters)->filter(fn ($value) => filled($value))->count()" :collapsible="true" class="casa-list-toolbar--filters">
            <form method="GET" action="{{ route('admin.system-errors.index') }}" class="casa-filter-form">
                <x-filter-search :value="$search" placeholder="{{ __('Reference, route, path, or exception') }}" label="{{ __('Search system errors') }}" />
                <select name="state" class="casa-input" aria-label="{{ __('Filter by state') }}"><option value="">{{ __('All states') }}</option><option value="open" @selected(($filters['state'] ?? null) === 'open')>{{ __('Open') }}</option><option value="resolved" @selected(($filters['state'] ?? null) === 'resolved')>{{ __('Resolved') }}</option></select>
                <select name="family" class="casa-input" aria-label="{{ __('Filter by error family') }}"><option value="">{{ __('All responses') }}</option><option value="4xx" @selected(($filters['family'] ?? null) === '4xx')>4xx</option><option value="5xx" @selected(($filters['family'] ?? null) === '5xx')>5xx</option></select>
                <input type="number" name="status" min="400" max="599" value="{{ $filters['status'] ?? null }}" class="casa-input" placeholder="{{ __('Status') }}" aria-label="{{ __('HTTP status') }}">
                <select name="category" class="casa-input" aria-label="{{ __('Filter by category') }}"><option value="">{{ __('All categories') }}</option>@foreach ([\App\Models\SystemErrorReport::CATEGORY_CLIENT_ERROR => __('Client error'), \App\Models\SystemErrorReport::CATEGORY_SERVER_ERROR => __('Server error'), \App\Models\SystemErrorReport::CATEGORY_FEATURE_UNAVAILABLE => __('Feature unavailable')] as $value => $label)<option value="{{ $value }}" @selected(($filters['category'] ?? null) === $value)>{{ $label }}</option>@endforeach</select>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? null }}" class="casa-input" aria-label="{{ __('From date') }}">
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? null }}" class="casa-input" aria-label="{{ __('To date') }}">
                <button type="submit" class="casa-button-primary">{{ __('Apply filters') }}</button>
            </form>
        </x-list-toolbar>

        <div class="mt-4 flex flex-wrap gap-2 text-xs font-bold text-casa-muted">
            <span class="rounded-full bg-casa-bg px-3 py-2">{{ __('Open server: :count', ['count' => $summary['openServer']]) }}</span>
            <span class="rounded-full bg-casa-bg px-3 py-2">{{ __('Open client: :count', ['count' => $summary['openClient']]) }}</span>
            <span class="rounded-full bg-casa-bg px-3 py-2">{{ __('Unavailable: :count', ['count' => $summary['unavailable']]) }}</span>
        </div>

        <div class="mt-5">
            @if ($reports->isEmpty())
                <x-empty-state title="{{ __('No error reports') }}" description="{{ __('Captured 4xx and 5xx responses will appear here.') }}" />
            @else
                <x-table-shell>
                    <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted"><tr><th class="px-4 py-3">{{ __('State') }}</th><th class="px-4 py-3">{{ __('Response') }}</th><th class="px-4 py-3">{{ __('Location') }}</th><th class="px-4 py-3">{{ __('Last seen') }}</th><th class="px-4 py-3">{{ __('Count') }}</th><th class="px-4 py-3">{{ __('Action') }}</th></tr></thead>
                    <tbody class="divide-y divide-casa-border text-sm">
                        @foreach ($reports as $report)
                            <tr class="casa-table-row">
                                <td class="px-4 py-4"><x-status-badge>{{ $report->resolved_at ? __('Resolved') : __('Open') }}</x-status-badge></td>
                                <td class="px-4 py-4"><p class="font-bold text-casa-text">{{ $report->http_status }} · {{ str($report->category)->replace('_', ' ')->title() }}</p><p class="mt-1 text-xs text-casa-muted">{{ $report->reference }}</p></td>
                                <td class="px-4 py-4 text-casa-muted"><p>{{ $report->last_route_name ?: $report->last_request_path }}</p><p class="mt-1 text-xs">{{ class_basename($report->exception_class) }}{{ $report->source_file ? ' · '.$report->source_file.':'.$report->source_line : '' }}</p></td>
                                <td class="px-4 py-4 text-casa-muted">{{ $report->last_seen_at?->format('M d, Y g:i A') }}</td>
                                <td class="px-4 py-4 font-bold text-casa-text">{{ number_format($report->occurrence_count) }}</td>
                                <td class="px-4 py-4"><a href="{{ route('admin.system-errors.show', $report) }}" class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table-shell>
                <div class="mt-5">{{ $reports->links() }}</div>
            @endif
        </div>
    </x-app-card>
</x-app-layout>
