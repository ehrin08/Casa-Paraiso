<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Operations') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Notifications') }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ __('New customer bookings assigned to your workspace.') }}</p>
        </div>
    </x-slot>

    <x-app-card>
        <x-list-toolbar eyebrow="{{ __('Inbox') }}" title="{{ __('Booking alerts') }}" :count="$notifications->total()">
            @if ($notifications->contains(fn ($notification) => is_null($notification->read_at)))
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')
                    <button class="casa-button-secondary w-full lg:w-auto">{{ __('Mark all as read') }}</button>
                </form>
            @endif
        </x-list-toolbar>

        <div class="mt-5">
            @if ($notifications->isEmpty())
                <x-empty-state title="{{ __('No notifications yet') }}" description="{{ __('New customer bookings will appear here.') }}" />
            @else
                <x-table-shell label="{{ __('Booking notifications') }}">
                    <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                        <tr>
                            <th class="px-4 py-3">{{ __('Booking') }}</th>
                            <th class="px-4 py-3">{{ __('Customer') }}</th>
                            <th class="px-4 py-3">{{ __('Scheduled') }}</th>
                            <th class="px-4 py-3">{{ __('Received') }}</th>
                            <th class="px-4 py-3">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-casa-border text-sm">
                        @foreach ($notifications as $notification)
                            @php
                                $data = $notification->data;
                                $scheduledAt = filled($data['scheduled_start_at'] ?? null) ? \Carbon\Carbon::parse($data['scheduled_start_at']) : null;
                            @endphp
                            <tr @class(['casa-table-row', 'bg-casa-green/5' => is_null($notification->read_at)])>
                                <td class="px-4 py-4">
                                    <strong class="block text-casa-text">{{ $data['service_name'] ?? __('Booking') }}</strong>
                                    <span class="mt-1 block text-xs text-casa-muted">{{ $data['appointment_number'] ?? __('Appointment') }} · {{ __('Therapist: :name', ['name' => $data['therapist_name'] ?? __('Unassigned')]) }}</span>
                                </td>
                                <td class="px-4 py-4 text-casa-muted">{{ $data['customer_name'] ?? __('Customer') }}</td>
                                <td class="px-4 py-4 text-casa-muted">{{ $scheduledAt?->format('M d, Y · g:i A') ?? __('Not scheduled') }}</td>
                                <td class="px-4 py-4 text-casa-muted">{{ $notification->created_at?->diffForHumans() }}</td>
                                <td class="px-4 py-4">
                                    <form method="POST" action="{{ route('notifications.open', $notification) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="font-bold text-casa-primary hover:text-casa-primary-dark">{{ __('Open booking') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table-shell>
                <div class="mt-5">{{ $notifications->links() }}</div>
            @endif
        </div>
    </x-app-card>
</x-app-layout>
