<x-app-card>
    <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="casa-section-label">{{ __('History') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ $title }}</h2>
        </div>
        <x-status-badge>{{ trans_choice(':count record|:count records', $records->count()) }}</x-status-badge>
    </div>

    <div class="mt-5">
        @if ($records->isEmpty())
            <x-empty-state title="{{ __('No records yet') }}" description="{{ __('Related records will appear here as workflows are completed.') }}" />
        @else
            <x-table-shell>
                <tbody class="divide-y divide-casa-border text-sm">
                    @foreach ($records as $record)
                        <tr>
                            @if ($type === 'appointments')
                                <td class="px-4 py-4 font-semibold text-casa-text">{{ $record->appointment_number }}</td>
                                <td class="px-4 py-4 text-casa-muted">{{ $record->service?->name }}</td>
                                <td class="px-4 py-4 text-casa-muted">{{ ($record->scheduled_start_at ?? $record->requested_start_at)?->format('M d, Y g:i A') }}</td>
                                <td class="px-4 py-4"><x-status-badge>{{ ucfirst(str_replace('_', ' ', $record->status)) }}</x-status-badge></td>
                            @elseif ($type === 'transactions')
                                <td class="px-4 py-4 font-semibold text-casa-text">{{ $record->transaction_number }}</td>
                                <td class="px-4 py-4 text-casa-muted">{{ $record->service?->name ?: __('General service') }}</td>
                                <td class="px-4 py-4 font-semibold text-casa-text">PHP {{ number_format((float) $record->amount, 2) }}</td>
                                <td class="px-4 py-4"><x-status-badge>{{ ucfirst($record->payment_status) }}</x-status-badge></td>
                            @elseif ($type === 'feedback')
                                <td class="px-4 py-4 font-semibold text-casa-text">{{ $record->rating }}/5</td>
                                <td class="px-4 py-4 text-casa-muted">{{ $record->service?->name }}</td>
                                <td class="px-4 py-4 text-casa-muted">{{ str($record->comment)->limit(80) ?: __('No comment') }}</td>
                                <td class="px-4 py-4"><x-status-badge>{{ ucfirst($record->sentiment_label) }}</x-status-badge></td>
                            @else
                                <td class="px-4 py-4 font-semibold text-casa-text">{{ $record->rfmSegment?->name ?: __('Unsegmented') }}</td>
                                <td class="px-4 py-4 text-casa-muted">{{ $record->suggested_offer }}</td>
                                <td class="px-4 py-4 text-casa-muted">{{ __(':days days · :visits paid visits · PHP :total', ['days' => $record->recency_days ?? __('N/A'), 'visits' => $record->frequency_count ?? 0, 'total' => number_format((float) $record->monetary_total, 2)]) }}</td>
                                <td class="px-4 py-4"><x-status-badge>{{ ucfirst($record->lifecycle()) }}</x-status-badge></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </x-table-shell>
        @endif
    </div>
</x-app-card>
