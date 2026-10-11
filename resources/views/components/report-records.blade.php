@props(['type', 'records', 'rewardNames' => []])

<x-table-shell class="reports-record-table" label="{{ __('Scrollable report records') }}">
    <thead><tr>
        @if ($type === 'transactions')
            <x-sortable-th sort="paid_at" scope="col">{{ __('Date') }}</x-sortable-th>
            <x-sortable-th sort="number" scope="col">{{ __('Reference ID') }}</x-sortable-th>
            <x-sortable-th sort="customer" scope="col">{{ __('Customer') }}</x-sortable-th>
            <x-sortable-th sort="service" scope="col">{{ __('Service') }}</x-sortable-th>
            <x-sortable-th sort="method" scope="col">{{ __('Payment method') }}</x-sortable-th>
            <x-sortable-th sort="status" scope="col">{{ __('Status') }}</x-sortable-th>
            <x-sortable-th sort="amount" scope="col">{{ __('Amount') }}</x-sortable-th>
        @elseif ($type === 'customers')
            <x-sortable-th sort="code" scope="col">{{ __('Code') }}</x-sortable-th>
            <x-sortable-th sort="name" scope="col">{{ __('Customer') }}</x-sortable-th>
            <th scope="col">{{ __('Email / Phone') }}</th>
            <x-sortable-th sort="joined" scope="col">{{ __('Joined') }}</x-sortable-th>
            <x-sortable-th sort="status" scope="col">{{ __('Status') }}</x-sortable-th>
            <x-sortable-th sort="appointments" scope="col">{{ __('Appointments') }}</x-sortable-th>
            <x-sortable-th sort="transactions" scope="col">{{ __('Transactions') }}</x-sortable-th>
            <x-sortable-th sort="feedback" scope="col">{{ __('Feedback') }}</x-sortable-th>
            <x-sortable-th sort="promotions" scope="col">{{ __('Rewards') }}</x-sortable-th>
        @elseif ($type === 'promotions')
            <x-sortable-th sort="customer" scope="col">{{ __('Customer') }}</x-sortable-th>
            <x-sortable-th sort="segment" scope="col">{{ __('Customer group') }}</x-sortable-th>
            <th scope="col">{{ __('Reward') }}</th>
            <x-sortable-th sort="monetary" scope="col">{{ __('Customer activity') }}</x-sortable-th>
            <x-sortable-th sort="status" scope="col">{{ __('Status') }}</x-sortable-th>
        @elseif ($type === 'feedback')
            <x-sortable-th sort="customer" scope="col">{{ __('Customer') }}</x-sortable-th>
            <x-sortable-th sort="service" scope="col">{{ __('Service') }}</x-sortable-th>
            <x-sortable-th sort="rating" scope="col">{{ __('Rating') }}</x-sortable-th>
            <x-sortable-th sort="sentiment" scope="col">{{ __('Sentiment') }}</x-sortable-th>
            <x-sortable-th sort="submitted" scope="col">{{ __('Submitted') }}</x-sortable-th>
        @else
            <x-sortable-th sort="schedule" scope="col">{{ __('Date') }}</x-sortable-th>
            <x-sortable-th sort="number" scope="col">{{ __('Reference ID') }}</x-sortable-th>
            <x-sortable-th sort="customer" scope="col">{{ __('Customer') }}</x-sortable-th>
            <x-sortable-th sort="service" scope="col">{{ __('Service') }}</x-sortable-th>
            <x-sortable-th sort="status" scope="col">{{ __('Status') }}</x-sortable-th>
        @endif
    </tr></thead>
    <tbody>
        @foreach ($records as $record)
            <tr>
                @if ($type === 'transactions')
                    <td>{{ $record->paid_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?: __('Not paid') }}</td><td>{{ $record->transaction_number }}</td><td>{{ $record->customerProfile?->display_name }}</td><td>{{ $record->service?->name }}</td><td>{{ ucfirst(str_replace('_', ' ', $record->payment_method ?? '')) ?: __('Not recorded') }}</td><td><x-status-badge>{{ ucfirst($record->payment_status) }}</x-status-badge></td><td class="reports-amount">₱{{ number_format((float) $record->amount, fmod((float) $record->amount, 1) === 0.0 ? 0 : 2) }}</td>
                @elseif ($type === 'customers')
                    <td>{{ $record->customer_code }}</td><td>{{ $record->display_name }}</td><td>{{ $record->display_email ?: ($record->display_phone ?: __('No account')) }}</td><td>{{ $record->created_at?->timezone('Asia/Manila')->format('M j, Y') }}</td><td><x-status-badge :tone="$record->user?->is_active ? 'success' : 'dark'">{{ $record->is_guest ? __('Guest') : ($record->user?->is_active ? __('Active') : __('Inactive')) }}</x-status-badge></td><td>{{ trans_choice(':count appointment|:count appointments', $record->appointments_count) }}</td><td>{{ trans_choice(':count transaction|:count transactions', $record->transactions_count) }}</td><td>{{ trans_choice(':count feedback|:count feedback', $record->feedback_count) }}</td><td>{{ trans_choice(':count reward|:count rewards', $record->promotion_suggestions_count) }}</td>
                @elseif ($type === 'promotions')
                    <td>{{ $record->customerProfile?->display_name }}</td><td>{{ $record->rfmSegment?->name }}</td><td>{{ $rewardNames[$record->addon_code] ?? $record->suggested_offer }}</td><td>{{ __(':days days · :visits paid visits', ['days' => $record->recency_days ?? __('N/A'), 'visits' => $record->frequency_count ?? 0]) }}</td><td><x-status-badge>{{ ucfirst($record->lifecycle()) }}</x-status-badge></td>
                @elseif ($type === 'feedback')
                    <td>{{ $record->customerProfile?->display_name }}</td><td>{{ $record->service?->name }}</td><td>{{ $record->rating }}/5</td><td><x-status-badge>{{ ucfirst($record->sentiment_label ?? 'Unclassified') }}</x-status-badge></td><td>{{ $record->submitted_at?->timezone('Asia/Manila')->format('M j, Y') }}</td>
                @else
                    <td>{{ ($record->scheduled_start_at ?? $record->requested_start_at)?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td><td>{{ $record->appointment_number }}</td><td>{{ $record->customerProfile?->display_name }}</td><td>{{ $record->service?->name }}</td><td><x-status-badge>{{ ucfirst(str_replace('_', ' ', $record->status)) }}</x-status-badge></td>
                @endif
            </tr>
        @endforeach
    </tbody>
</x-table-shell>
