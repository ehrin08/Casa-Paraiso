<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin schedule') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Bookings & therapist coverage') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Run confirmed visits from the service queue and maintain therapist availability from one weekly calendar.') }}
            </p>
        </div>

        <a
            href="{{ route('admin.appointments.create') }}"
            class="casa-button-primary"
            data-panel-link
            data-turbo="false"
        >
            <x-nav-icon name="calendar" class="size-4" />
            {{ __('Add appointment') }}
        </a>
    </x-slot>

    <div class="space-y-5">
        <x-stat-strip :items="[
            ['label' => __('Confirmed'), 'value' => $summary['confirmed'], 'meta' => __('Placed on therapist calendars'), 'tone' => 'green'],
            ['label' => __('Completed'), 'value' => $summary['completed'], 'meta' => __('Finished services'), 'tone' => 'brown'],
            ['label' => __('Cancelled'), 'value' => $summary['cancelled'], 'meta' => __('Visits no longer scheduled'), 'tone' => 'gold'],
        ]" />

        <x-app-card id="service-queue">
            <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="casa-section-label">{{ __('Service queue') }}</p>
                    <h2 class="mt-2 font-display text-2xl font-black text-casa-text">{{ __('Next visits to serve') }}</h2>
                    <p class="mt-2 text-sm text-casa-muted">{{ __('Overdue and ready visits lead the queue, followed by upcoming confirmed appointments.') }}</p>
                </div>
                <span class="casa-filter-chip">{{ trans_choice(':count visit|:count visits', $serviceQueue->total()) }}</span>
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($serviceQueue as $queuedAppointment)
                    @php
                        $hasStarted = $queuedAppointment->scheduled_start_at?->lte(now()) ?? false;
                        $isOverdue = $queuedAppointment->scheduled_end_at?->lt(now()) ?? false;
                    @endphp
                    <article class="grid gap-4 rounded-2xl border p-4 sm:grid-cols-[7.5rem_minmax(0,1fr)_auto] sm:items-center {{ $isOverdue ? 'border-casa-cacao/35 bg-casa-cacao/5' : ($hasStarted ? 'border-casa-palm/35 bg-casa-palm/5' : 'border-casa-border bg-casa-bg') }}">
                        <div>
                            <p class="text-lg font-black text-casa-text">{{ $queuedAppointment->scheduled_start_at?->format('g:i A') }}</p>
                            <p class="mt-1 text-sm font-bold uppercase tracking-[0.05em] text-casa-muted">{{ $queuedAppointment->scheduled_start_at?->format('M d') }}</p>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate font-bold text-casa-text">{{ $queuedAppointment->customerProfile?->user?->name }}</h3>
                                <span class="rounded-full px-2.5 py-1 text-sm font-black uppercase tracking-[0.04em] {{ $isOverdue ? 'bg-casa-cacao text-white' : ($hasStarted ? 'bg-casa-palm text-white' : 'bg-casa-brass/15 text-casa-cacao') }}">
                                    {{ $isOverdue ? __('Overdue') : ($hasStarted ? __('Ready') : __('Upcoming')) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-casa-muted">{{ $queuedAppointment->appointment_number }} · {{ $queuedAppointment->service?->name }} · {{ $queuedAppointment->staffProfile?->user?->name }} · {{ $queuedAppointment->scheduled_start_at?->format('g:i A') }}–{{ $queuedAppointment->scheduled_end_at?->format('g:i A') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 sm:justify-end">
                            <a href="{{ route('admin.appointments.show', $queuedAppointment) }}" class="casa-button-primary">{{ $hasStarted ? __('Finish service') : __('View booking') }}</a>
                            @if ($hasStarted)
                                <x-confirm-action
                                    :action="route('admin.appointments.outcome', $queuedAppointment)"
                                    method="PATCH"
                                    label="{{ __('No-show') }}"
                                    confirm-title="{{ __('Mark this visit as no-show?') }}"
                                    confirm-message="{{ __('The visit will leave the active queue and no transaction will be created.') }}"
                                    confirm-button="{{ __('Mark no-show') }}"
                                    button-class="casa-button-secondary"
                                >
                                    <input type="hidden" name="status" value="no_show">
                                </x-confirm-action>
                            @endif
                        </div>
                    </article>
                @empty
                    <x-empty-state title="{{ __('The service queue is clear') }}" description="{{ __('New customer bookings will appear here as soon as they are confirmed automatically.') }}" />
                @endforelse
            </div>
            {{ $serviceQueue->links() }}
        </x-app-card>

        <x-operational-calendar
            :feed-url="route('admin.appointments.calendar')"
            :initial-week="$initialWeek"
            :initial-mode="$mode"
            role="admin"
            :services="$services"
            :staff-profiles="$staffProfiles"
            :create-url="route('admin.appointments.create')"
        />
    </div>
</x-app-layout>
