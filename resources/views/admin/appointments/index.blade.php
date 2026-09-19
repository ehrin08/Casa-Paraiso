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

    <div class="space-y-4">
        {{-- Stat strip: full-width above both columns --}}
        <x-stat-strip :items="[
            ['label' => __('Confirmed'), 'value' => $summary['confirmed'], 'meta' => __('Placed on therapist calendars'), 'tone' => 'green'],
            ['label' => __('Completed'), 'value' => $summary['completed'], 'meta' => __('Finished services'), 'tone' => 'brown'],
            ['label' => __('Cancelled'), 'value' => $summary['cancelled'], 'meta' => __('Visits no longer scheduled'), 'tone' => 'gold'],
        ]" />

        {{-- 2-column split: queue left (~30%), calendar right (~70%) --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_2fr] lg:items-stretch">

            {{-- LEFT: Service queue (independently scrollable) --}}
            <div class="lg:sticky lg:top-[4.5rem] lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto lg:h-full" id="service-queue">
                <div class="casa-card casa-app-card p-0 overflow-hidden h-full flex flex-col">

                    {{-- Compact queue header --}}
                    <div class="flex items-center justify-between gap-3 border-b border-casa-border px-4 py-3">
                        <div class="min-w-0">
                            <p class="casa-section-label">{{ __('Service queue') }}</p>
                            <h2 class="mt-0.5 font-display text-base font-black text-casa-text leading-tight">{{ __('Next visits to serve') }}</h2>
                        </div>
                        <span class="casa-filter-chip shrink-0">{{ trans_choice(':count visit|:count visits', $serviceQueue->total()) }}</span>
                    </div>

                    {{-- Queue items --}}
                    <div class="divide-y divide-casa-border flex-1 overflow-y-auto">
                        @forelse ($serviceQueue as $queuedAppointment)
                            @php
                                $hasStarted = $queuedAppointment->scheduled_start_at?->lte(now()) ?? false;
                                $isOverdue  = $queuedAppointment->scheduled_end_at?->lt(now())  ?? false;
                            @endphp
                            <article class="px-4 py-3 transition {{ $isOverdue ? 'bg-casa-cacao/5' : ($hasStarted ? 'bg-casa-palm/5' : '') }}">
                                {{-- Line 1: status pill + customer name + time --}}
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-black uppercase tracking-[0.04em] {{ $isOverdue ? 'bg-casa-cacao text-white' : ($hasStarted ? 'bg-casa-palm text-white' : 'bg-casa-brass/15 text-casa-cacao') }}">
                                        {{ $isOverdue ? __('Overdue') : ($hasStarted ? __('Ready') : __('Upcoming')) }}
                                    </span>
                                    <span class="truncate text-sm font-bold text-casa-text">{{ $queuedAppointment->customerProfile?->user?->name }}</span>
                                    <span class="ms-auto shrink-0 text-xs font-bold text-casa-muted">{{ $queuedAppointment->scheduled_start_at?->format('g:i A') }}</span>
                                </div>

                                {{-- Line 2: meta --}}
                                <p class="mt-1 truncate text-xs text-casa-muted">
                                    {{ $queuedAppointment->appointment_number }}
                                    · {{ $queuedAppointment->service?->name }}
                                    · {{ $queuedAppointment->staffProfile?->user?->name }}
                                    · {{ $queuedAppointment->scheduled_start_at?->format('g:i') }}–{{ $queuedAppointment->scheduled_end_at?->format('g:i A') }}
                                </p>

                                {{-- Actions --}}
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.appointments.show', $queuedAppointment) }}" class="casa-button-primary py-1.5 px-3 text-xs">
                                        {{ $hasStarted ? __('Finish service') : __('View booking') }}
                                    </a>
                                    @if ($hasStarted)
                                        <x-confirm-action
                                            :action="route('admin.appointments.outcome', $queuedAppointment)"
                                            method="PATCH"
                                            label="{{ __('No-show') }}"
                                            confirm-title="{{ __('Mark this visit as no-show?') }}"
                                            confirm-message="{{ __('The visit will leave the active queue and no transaction will be created.') }}"
                                            confirm-button="{{ __('Mark no-show') }}"
                                            button-class="casa-button-secondary py-1.5 px-3 text-xs"
                                        >
                                            <input type="hidden" name="status" value="no_show">
                                        </x-confirm-action>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="px-4 py-6">
                                <x-empty-state
                                    title="{{ __('The service queue is clear') }}"
                                    description="{{ __('New customer bookings will appear here as soon as they are confirmed automatically.') }}"
                                />
                            </div>
                        @endforelse
                    </div>

                    {{-- Pagination --}}
                    @if ($serviceQueue->hasPages())
                        <div class="border-t border-casa-border px-4 py-3">
                            {{ $serviceQueue->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- RIGHT: Operational calendar --}}
            <div class="min-w-0">
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

        </div>
    </div>
</x-app-layout>
