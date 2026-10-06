<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="font-display text-3xl font-black text-casa-text">{{ __('Appointments') }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $today->format('l, F j, Y') }} · Asia/Manila</p>
        </div>
        <a href="{{ route('admin.appointments.create') }}" class="casa-button-primary" data-panel-link data-turbo="false">
            <x-nav-icon name="plus" class="size-5" />{{ __('Add appointment') }}
        </a>
    </x-slot>
    <div class="casa-appointments space-y-4">
        <x-stat-strip presentation="cards" aria-label="{{ __('Appointments scheduled today') }}" :items="[
            ['label' => __('Confirmed'), 'value' => $summary['confirmed'], 'icon' => 'calendar', 'tone' => 'green'],
            ['label' => __('Completed'), 'value' => $summary['completed'], 'icon' => 'check', 'tone' => 'green'],
            ['label' => __('Cancelled'), 'value' => $summary['cancelled'], 'icon' => 'cancel', 'tone' => 'red'],
        ]" />
        <section class="casa-card p-4" id="service-queue" aria-labelledby="service-queue-title">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 id="service-queue-title" class="text-xl font-extrabold text-casa-text">{{ __('Next visits to serve') }}</h2>
                <a href="{{ route('admin.appointments.records') }}" class="casa-appointments-all-link">{{ __('View all appointments') }}<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg></a>
            </div>
            @if ($serviceQueue->isEmpty())
                <x-empty-state title="{{ __('The service queue is clear') }}" description="{{ __('There are no confirmed visits for today or earlier unfinished visits. Future bookings appear on the weekly schedule.') }}" />
            @else
                <x-table-shell label="{{ __('Next visits to serve') }}">
                    <thead class="bg-casa-bg text-left text-sm text-casa-muted">
                        <tr>@foreach (['Time', 'Customer', 'Service', 'Therapist', 'Status', 'Actions'] as $heading)<th scope="col" class="px-3 py-3 font-semibold">{{ __($heading) }}</th>@endforeach</tr>
                    </thead>
                    <tbody class="divide-y divide-casa-border text-sm">
                        @foreach ($serviceQueue as $appointment)
                            @php
                                $start = $appointment->scheduled_start_at?->copy()->timezone('Asia/Manila');
                                $hasStarted = $start?->lte(now('Asia/Manila')) ?? false;
                                $isOverdue = $appointment->scheduled_end_at?->lt(now()) ?? false;
                                $completionModal = 'admin-appointment-completion-'.$appointment->id;
                            @endphp
                            <tr class="casa-table-row">
                                <td class="px-3 py-2 whitespace-nowrap font-semibold">
                                    {{ $start?->format('g:i A') }}
                                    @if ($start && ! $start->isSameDay($today))<span class="block text-sm font-normal text-casa-muted">{{ $start->format('M j, Y') }}</span>@endif
                                </td>
                                <td class="px-3 py-2">{{ $appointment->customerProfile?->display_name ?: __('Customer unavailable') }}<span class="block text-sm text-casa-muted">{{ $appointment->appointment_number }}</span></td>
                                <td class="px-3 py-2 text-casa-muted">{{ $appointment->service?->name ?: __('Service unavailable') }}</td>
                                <td class="px-3 py-2">{{ $appointment->staffProfile?->user?->name ?: __('Unassigned') }}</td>
                                <td class="px-3 py-2"><span class="casa-appointment-status">{{ __('Confirmed') }}</span>@if ($isOverdue)<span class="mt-1 block font-semibold text-casa-cacao">{{ __('Overdue') }}</span>@endif</td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2 whitespace-nowrap">
                                        <a href="{{ route('admin.appointments.show', ['appointment' => $appointment, 'preview' => 1]) }}" class="casa-button-secondary" data-panel-link data-turbo="false" aria-label="{{ __('View visit :number', ['number' => $appointment->appointment_number]) }}">{{ __('View') }}</a>
                                        @if ($hasStarted)
                                            <button type="button" class="casa-button-primary" x-data="" x-on:click="$dispatch('open-modal', '{{ $completionModal }}')" aria-label="{{ __('Finish visit :number', ['number' => $appointment->appointment_number]) }}">{{ __('Finish') }}</button>
                                            <x-confirm-action :action="route('admin.appointments.outcome', $appointment)" method="PATCH" label="{{ __('No-show') }}" confirm-title="{{ __('Mark this visit as no-show?') }}" confirm-message="{{ __('The visit will leave the active queue and no transaction will be created.') }}" confirm-button="{{ __('Mark no-show') }}" button-class="casa-button-secondary">
                                                <input type="hidden" name="status" value="no_show">
                                                <input type="hidden" name="return_to" value="index">
                                                <input type="hidden" name="queue_page" value="{{ $serviceQueue->currentPage() }}">
                                                <input type="hidden" name="mode" value="{{ $mode }}" x-data="" x-on:appointment-calendar-mode-changed.window="$el.value = $event.detail.mode">
                                            </x-confirm-action>
                                        @else
                                            <button type="button" class="casa-button-primary" disabled aria-describedby="visit-start-{{ $appointment->id }}">{{ __('Finish') }}</button>
                                            <button type="button" class="casa-button-secondary" disabled aria-describedby="visit-start-{{ $appointment->id }}">{{ __('No-show') }}</button>
                                        @endif
                                    </div>
                                    @if (! $hasStarted)<span id="visit-start-{{ $appointment->id }}" class="mt-1 block text-sm text-casa-muted">{{ __('Available once the visit starts.') }}</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table-shell>
                <div class="mt-3">{{ $serviceQueue->links() }}</div>
            @endif
        </section>
        <x-operational-calendar :feed-url="route('admin.appointments.calendar')" :initial-week="$initialWeek" :initial-mode="$mode" role="admin" presentation="week" :services="$services" :staff-profiles="$staffProfiles" :create-url="route('admin.appointments.create')" />
    </div>
    @foreach ($completionAppointments as $appointment)
            @php $completionModal = 'admin-appointment-completion-'.$appointment->id; @endphp
            <x-modal :name="$completionModal" :show="old('_modal') === $completionModal" maxWidth="5xl" focusable>
                <div class="p-5">@include('admin.appointments.partials.completion-form', ['appointment' => $appointment, 'modalName' => $completionModal, 'queueContext' => ['return_to' => 'index', 'queue_page' => $serviceQueue->currentPage(), 'mode' => $mode]])</div>
            </x-modal>
    @endforeach
</x-app-layout>
