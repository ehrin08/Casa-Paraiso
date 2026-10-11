<x-app-layout>
    <x-slot name="header"><div class="notifications-heading"><h1 class="font-display text-3xl font-black text-casa-text">{{ __('Notifications') }}</h1><p>{{ __('Customer booking alerts') }}</p></div></x-slot>
    <div class="notifications-workspace" data-notifications-workspace>
        <section class="notifications-inbox" aria-label="{{ __('Booking alerts') }}">
            <x-list-toolbar class="notifications-toolbar">
                <div class="notifications-controls">
                    <nav class="notifications-tabs" aria-label="{{ __('Notification filters') }}">
                        @foreach (['all' => __('All'), 'unread' => __('Unread')] as $status => $label)
                            <a href="{{ route('notifications.index', ['q' => $filters['q'], 'status' => $status]) }}" @class(['notifications-tab', 'is-active' => $filters['status'] === $status]) @if ($filters['status'] === $status) aria-current="page" @endif>{{ $label }} ({{ $counts[$status] }})</a>
                        @endforeach
                    </nav>
                    <form method="GET" action="{{ route('notifications.index') }}" class="notifications-search" role="search">
                        <input type="hidden" name="status" value="{{ $filters['status'] }}">
                        <label class="sr-only" for="notification-search">{{ __('Search by customer, service or booking ID') }}</label>
                        <input id="notification-search" class="casa-input" type="search" name="q" maxlength="150" value="{{ $filters['q'] }}" placeholder="{{ __('Search by customer, service or booking ID') }}">
                        <button type="submit" aria-label="{{ __('Search notifications') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg></button>
                    </form>
                </div>
            </x-list-toolbar>
            @if ($filters['q'] !== '' || $counts['unread'] > 0)
                <div class="notifications-tools">
                    @if ($filters['q'] !== '')<a href="{{ route('notifications.index', ['status' => $filters['status']]) }}">{{ __('Clear search') }}</a>@endif
                    @if ($counts['unread'] > 0)<form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button type="submit">{{ __('Mark all as read') }}</button></form>@endif
                </div>
            @endif
            @if ($notifications->isEmpty())
                <x-empty-state :title="$filters['q'] !== '' ? __('No matching notifications') : ($filters['status'] === 'unread' ? __('No unread notifications') : __('No notifications yet'))" :description="$filters['q'] !== '' ? __('Try another customer, service or booking number.') : ($filters['status'] === 'unread' ? __('You are caught up on your booking alerts.') : __('New customer bookings will appear here.'))" />
            @else
                <x-table-shell label="{{ __('Booking notifications') }}" class="notifications-table">
                    <thead class="sr-only"><tr><th scope="col">{{ __('Booking and customer') }}</th><th scope="col">{{ __('Service and therapist') }}</th><th scope="col">{{ __('Scheduled and received') }}</th><th scope="col">{{ __('Action') }}</th></tr></thead>
                    <tbody>
                        @foreach ($notifications as $notification)
                            @php
                                $data = $notification->data;
                                $appointment = $appointments->get($data['appointment_id'] ?? 0);
                                $scheduledAt = filled($data['scheduled_start_at'] ?? null) ? \Carbon\Carbon::parse($data['scheduled_start_at'])->timezone('Asia/Manila') : null;
                                $receivedAt = $notification->created_at?->copy()->timezone('Asia/Manila');
                                $unread = is_null($notification->read_at);
                                $selected = $selectedNotification?->id === $notification->id;
                            @endphp
                            <tr @class(['notifications-row', 'is-selected' => $selected]) data-notification-row="{{ $notification->id }}">
                                <td class="notifications-booking">
                                    <span class="notifications-read-indicator"><span @class(['notifications-dot', 'is-unread' => $unread]) aria-hidden="true"></span><span class="sr-only">{{ $unread ? __('Unread') : __('Read') }}</span></span>
                                    <div><a href="{{ route('notifications.index', $filters + ['page' => $notifications->currentPage(), 'selected' => $notification->id]) }}#notification-detail" @if ($selected) aria-current="true" @endif aria-label="{{ __('Preview booking :number', ['number' => $data['appointment_number'] ?? __('Appointment')]) }}">{{ $data['appointment_number'] ?? __('Appointment') }}</a><span>{{ $data['customer_name'] ?? __('Customer unavailable') }}</span>@if ($appointment?->customerProfile?->display_email)<span class="notifications-muted">{{ $appointment->customerProfile->display_email }}</span>@endif</div>
                                </td>
                                <td><span>{{ $data['service_name'] ?? __('Service unavailable') }}</span><span class="notifications-muted">{{ __('with :name', ['name' => $data['therapist_name'] ?? __('Unassigned')]) }}</span></td>
                                <td class="notifications-dates">
                                    @if ($scheduledAt)<time datetime="{{ $scheduledAt->toIso8601String() }}"><span>{{ $scheduledAt->format('D, M j, Y') }}</span><span>{{ $scheduledAt->format('g:i A') }}</span></time>@else<span>{{ __('Not scheduled') }}</span>@endif
                                    @if ($receivedAt)<span class="notifications-muted">{{ __('Received :time', ['time' => $receivedAt->isToday() ? $receivedAt->format('g:i A') : $receivedAt->format('M j, g:i A')]) }}</span>@endif
                                </td>
                                <td class="notifications-action"><x-notification-open :notification="$notification" :available="(bool) $appointment" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table-shell>
                <div class="notifications-pagination">{{ $notifications->links() }}</div>
            @endif
        </section>
        <section class="notifications-detail" id="notification-detail" aria-labelledby="notification-detail-title" data-selected-notification="{{ $selectedNotification?->id }}">
            @if ($selectedNotification)
                @php
                    $receivedAt = $selectedNotification->created_at?->copy()->timezone('Asia/Manila');
                    $start = $selectedAppointment?->scheduled_start_at?->copy()->timezone('Asia/Manila');
                    $end = $selectedAppointment?->scheduled_end_at?->copy()->timezone('Asia/Manila');
                    $unread = is_null($selectedNotification->read_at);
                @endphp
                <div class="notifications-detail-heading"><div><p>{{ __('Booking alert') }}</p><span>{{ __('Received :time', ['time' => $receivedAt ? ($receivedAt->isToday() ? __('today at ').$receivedAt->format('g:i A') : $receivedAt->format('M j, Y').' '.__('at').' '.$receivedAt->format('g:i A')) : __('time unavailable')]) }}</span></div><span @class(['notifications-status', 'is-unread' => $unread])><span aria-hidden="true"></span>{{ $unread ? __('Unread') : __('Read') }}</span></div>
                <h2 id="notification-detail-title">{{ __('Booking :number', ['number' => $selectedAppointment?->appointment_number ?? $selectedNotification->data['appointment_number'] ?? __('unavailable')]) }}</h2>
                @if ($selectedAppointment)
                    <dl class="notifications-details">
                        <x-notification-detail-item label="{{ __('Customer') }}" icon="profile"><strong>{{ $selectedAppointment->customerProfile?->display_name ?? __('Customer unavailable') }}</strong>@if ($selectedAppointment->customerProfile?->display_email)<span class="notifications-muted">{{ $selectedAppointment->customerProfile->display_email }}</span>@endif @if ($selectedAppointment->customerProfile?->display_phone)<span class="notifications-muted">{{ $selectedAppointment->customerProfile->display_phone }}</span>@endif</x-notification-detail-item>
                        <x-notification-detail-item label="{{ __('Service') }}" icon="treatment"><strong>{{ $selectedAppointment->service?->name ?? __('Service unavailable') }}</strong><span class="notifications-muted">{{ $start && $end ? __(':minutes minutes', ['minutes' => (int) $start->diffInMinutes($end)]) : __('Duration unavailable') }}</span></x-notification-detail-item>
                        <x-notification-detail-item label="{{ __('Therapist') }}" icon="profile">{{ $selectedAppointment->staffProfile?->user?->name ?? __('Unassigned') }}</x-notification-detail-item>
                        <x-notification-detail-item label="{{ __('Schedule') }}" icon="calendar">@if ($start)<time datetime="{{ $start->toIso8601String() }}">{{ $start->format('l, F j, Y') }}</time><span>{{ $start->format('g:i A') }}@if ($end) – {{ $end->isSameDay($start) ? $end->format('g:i A') : $end->format('M j, g:i A') }}@endif</span>@else<span>{{ __('Not scheduled') }}</span>@endif</x-notification-detail-item>
                        <x-notification-detail-item label="{{ __('Received') }}" icon="clock">{{ $receivedAt ? $receivedAt->format('l, F j, Y').' '.__('at').' '.$receivedAt->format('g:i A') : __('Time unavailable') }}</x-notification-detail-item>
                    </dl>
                    <div class="notifications-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6M8 13h8m-8 4h6"/></svg><div><h3>{{ __('Customer note') }}</h3><p>{{ $selectedAppointment->customer_notes ?: __('No customer note provided.') }}</p></div></div>
                @else
                    <div class="notifications-unavailable"><x-nav-icon name="notifications"/><p>{{ __('This booking is unavailable or is no longer assigned to your workspace.') }}</p></div>
                @endif
                <div class="notifications-detail-actions">
                    <x-notification-open :notification="$selectedNotification" :available="(bool) $selectedAppointment" primary />
                    @if ($start)<a href="{{ route($calendarRoute, ['calendar_date' => $start->toDateString(), 'mode' => 'bookings']) }}#booking-calendar" class="casa-button-secondary"><x-nav-icon name="calendar"/>{{ __('View in calendar') }}</a>@else<button type="button" class="casa-button-secondary" disabled>{{ __('View in calendar') }}</button>@endif
                </div>
            @else
                <h2 id="notification-detail-title">{{ __('Booking details') }}</h2><div class="notifications-unavailable"><x-nav-icon name="notifications"/><p>{{ __('Select a booking alert to see its details here.') }}</p></div>
            @endif
        </section>
    </div>
</x-app-layout>
