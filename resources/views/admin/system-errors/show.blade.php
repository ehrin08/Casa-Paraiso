<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('System error detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $report->http_status }} · {{ str($report->category)->replace('_', ' ')->title() }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $report->reference }}</p>
        </div>
        <a href="{{ route('admin.system-errors.index') }}" class="casa-button-secondary">{{ __('All errors') }}</a>
    </x-slot>

    <div class="space-y-5">
        <x-app-card>
            <x-stat-strip :items="[
                ['label' => __('State'), 'value' => $report->resolved_at ? __('Resolved') : __('Open'), 'meta' => $report->resolved_at ? __('Reviewed by :name', ['name' => $report->resolvedBy?->name ?? __('Unknown')]) : __('Needs review'), 'tone' => $report->resolved_at ? 'green' : 'gold'],
                ['label' => __('Occurrences'), 'value' => number_format($report->occurrence_count), 'meta' => __('First seen :date', ['date' => $report->first_seen_at?->format('M d, Y')]), 'tone' => 'brown'],
                ['label' => __('Last seen'), 'value' => $report->last_seen_at?->format('M d'), 'meta' => $report->last_request_id, 'tone' => 'green'],
            ]" />
            <div class="mt-6 grid gap-4 rounded-2xl bg-casa-bg p-5 text-sm text-casa-muted lg:grid-cols-2">
                <div><p class="font-black text-casa-text">{{ __('Exception') }}</p><p class="mt-1 break-all">{{ $report->exception_class }}</p></div>
                <div><p class="font-black text-casa-text">{{ __('Source') }}</p><p class="mt-1 break-all">{{ $report->source_file ? $report->source_file.':'.$report->source_line : __('No exception source available') }}</p></div>
                <div><p class="font-black text-casa-text">{{ __('Latest request') }}</p><p class="mt-1 break-all">{{ $report->last_request_method }} {{ $report->last_request_path }}{{ $report->last_route_name ? ' · '.$report->last_route_name : '' }}</p></div>
                <div><p class="font-black text-casa-text">{{ __('Affected user') }}</p><p class="mt-1">{{ $report->lastUser?->name ?? __('Guest or unavailable') }}</p></div>
            </div>
            <div class="mt-5 rounded-2xl border border-casa-border bg-casa-paper p-5"><p class="text-sm font-black text-casa-text">{{ __('Sanitized message') }}</p><p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-casa-muted">{{ $report->message }}</p></div>
            <div class="mt-5 flex flex-wrap gap-3">
                @if ($report->resolved_at)
                    <form method="POST" action="{{ route('admin.system-errors.reopen', $report) }}">@csrf @method('PATCH')<button class="casa-button-secondary">{{ __('Reopen') }}</button></form>
                @else
                    <form method="POST" action="{{ route('admin.system-errors.resolve', $report) }}">@csrf @method('PATCH')<button class="casa-button-secondary">{{ __('Mark resolved') }}</button></form>
                @endif
            </div>
        </x-app-card>

        <x-app-card>
            <h2 class="text-lg font-black text-casa-text">{{ __('Sanitized stack frames') }}</h2>
            <ol class="mt-4 space-y-2 text-sm text-casa-muted">
                @forelse ($report->stack_frames ?? [] as $frame)
                    <li class="rounded-xl bg-casa-bg px-4 py-3 break-all">{{ $frame['file'] ?? __('Unknown file') }}{{ isset($frame['line']) ? ':'.$frame['line'] : '' }}{{ ! empty($frame['class']) ? ' · '.$frame['class'] : '' }}{{ ! empty($frame['function']) ? '::'.$frame['function'].'()' : '' }}</li>
                @empty
                    <li>{{ __('No exception stack was recorded for this response.') }}</li>
                @endforelse
            </ol>
        </x-app-card>

        <x-app-card>
            <h2 class="text-lg font-black text-casa-text">{{ __('Recent occurrences') }}</h2>
            <div class="mt-4">
                <x-table-shell>
                    <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted"><tr><th class="px-4 py-3">{{ __('When') }}</th><th class="px-4 py-3">{{ __('Request') }}</th><th class="px-4 py-3">{{ __('User') }}</th></tr></thead>
                    <tbody class="divide-y divide-casa-border text-sm">@foreach ($occurrences as $occurrence)<tr class="casa-table-row"><td class="px-4 py-4 text-casa-muted">{{ $occurrence->occurred_at?->format('M d, Y g:i A') }}</td><td class="px-4 py-4"><p class="font-semibold text-casa-text">{{ $occurrence->request_method }} {{ $occurrence->request_path }}</p><p class="mt-1 text-xs text-casa-muted">{{ $occurrence->request_id }}</p></td><td class="px-4 py-4 text-casa-muted">{{ $occurrence->user?->name ?? __('Guest or unavailable') }}</td></tr>@endforeach</tbody>
                </x-table-shell>
                <div class="mt-5">{{ $occurrences->links() }}</div>
            </div>
        </x-app-card>
    </div>
</x-app-layout>
