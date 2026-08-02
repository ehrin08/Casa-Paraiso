@props([
    'feedUrl',
    'initialWeek',
    'role',
    'initialMode' => 'bookings',
    'services' => collect(),
    'staffProfiles' => collect(),
    'createUrl' => null,
])

@php
    $isOperationsCalendar = in_array($role, ['admin', 'reception'], true);
    $canEditAvailability = $role === 'admin';
    $weeklyCreatePattern = $canEditAvailability ? url('/admin/staff/__STAFF__/weekly-schedules/create') : '';
    $exceptionCreatePattern = $canEditAvailability ? url('/admin/staff/__STAFF__/schedule-exceptions/create') : '';
@endphp

<div
    x-data="operationalCalendar({
        feedUrl: @js($feedUrl),
        createUrl: @js($createUrl),
        weeklyCreatePattern: @js($weeklyCreatePattern),
        exceptionCreatePattern: @js($exceptionCreatePattern),
        role: @js($role),
        canEditAvailability: @js($canEditAvailability),
        initialMode: @js($initialMode),
        initialWeek: @js($initialWeek)
    })"
    x-init="init()"
    class="space-y-5"
    data-operational-calendar
>
    <section class="casa-card p-4 sm:p-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="space-y-3">
                @if ($isOperationsCalendar)
                    <div class="inline-flex rounded-xl border border-casa-border bg-casa-sand/55 p-1" role="group" aria-label="{{ __('Schedule mode') }}">
                        <button type="button" class="min-h-11 rounded-lg px-4 text-sm font-extrabold uppercase tracking-[0.05em] transition" x-on:click="setMode('bookings')" x-bind:aria-pressed="mode === 'bookings'" x-bind:class="mode === 'bookings' ? 'bg-casa-palm text-white shadow-sm' : 'text-casa-muted hover:text-casa-cacao'">
                            {{ __('Bookings') }}
                        </button>
                        <button type="button" class="min-h-11 rounded-lg px-4 text-sm font-extrabold uppercase tracking-[0.05em] transition" x-on:click="setMode('availability')" x-bind:aria-pressed="mode === 'availability'" x-bind:class="mode === 'availability' ? 'bg-casa-cacao text-white shadow-sm' : 'text-casa-muted hover:text-casa-cacao'">
                            {{ __('Availability') }}
                        </button>
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="casa-icon-button" x-on:click="previousWeek()" aria-label="{{ __('Previous week') }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m15 18-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div class="min-w-0 flex-1 text-center sm:min-w-44 sm:flex-none">
                        <p class="text-sm font-extrabold uppercase tracking-[0.05em] text-casa-muted">{{ __('Schedule week') }}</p>
                        <p class="mt-1 font-display text-lg font-black text-casa-text" x-text="weekLabel"></p>
                    </div>
                    <button type="button" class="casa-icon-button" x-on:click="nextWeek()" aria-label="{{ __('Next week') }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <button type="button" class="casa-button-secondary w-full sm:w-auto" x-on:click="today()">{{ __('Today') }}</button>
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-2 xl:flex xl:items-end">
                @if ($isOperationsCalendar)
                    <label class="block min-w-44">
                        <span class="casa-label">{{ __('Therapist') }}</span>
                        <select class="casa-input mt-1.5" x-model="staffFilter" x-on:change="load()">
                            <option value="">{{ __('All therapists') }}</option>
                            @foreach ($staffProfiles as $staffProfile)
                                <option value="{{ $staffProfile->id }}">{{ $staffProfile->user?->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block min-w-44" x-show="mode === 'bookings'">
                        <span class="casa-label">{{ __('Service') }}</span>
                        <select class="casa-input mt-1.5" x-model="serviceFilter" x-on:change="load()">
                            <option value="">{{ __('All services') }}</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}">{{ $service->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label class="block min-w-40" x-show="mode === 'bookings'">
                    <span class="casa-label">{{ __('Status') }}</span>
                    <select class="casa-input mt-1.5" x-model="statusFilter" x-on:change="load()">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (\App\Models\Appointment::STATUSES as $status)
                            <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>

        <div class="mt-5 overflow-x-auto rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-casa-palm" tabindex="0" role="region" aria-label="{{ __('Scrollable days in selected week') }}" data-calendar-scroll>
            <div class="grid min-w-[22rem] grid-cols-7 gap-1.5" role="tablist" aria-label="{{ __('Days in selected week') }}">
                <template x-for="day in dayOptions" x-bind:key="day.date">
                    <button
                        type="button"
                        class="min-h-14 min-w-11 rounded-xl border px-1.5 py-2 text-center transition"
                        x-on:click="selectedDate = day.date"
                        x-on:keydown.right.prevent="moveSelectedDay(day.date, 1)"
                        x-on:keydown.left.prevent="moveSelectedDay(day.date, -1)"
                        x-on:keydown.home.prevent="focusWeekBoundary(false)"
                        x-on:keydown.end.prevent="focusWeekBoundary(true)"
                        x-bind:aria-selected="selectedDate === day.date"
                        x-bind:tabindex="selectedDate === day.date ? 0 : -1"
                        x-bind:data-operational-day="day.date"
                        x-bind:class="selectedDate === day.date ? 'border-casa-palm bg-casa-palm text-white shadow-sm' : day.isToday ? 'border-casa-brass bg-casa-sand/65 text-casa-cacao' : 'border-casa-border bg-casa-paper text-casa-muted hover:border-casa-brass'"
                        role="tab"
                    >
                        <span class="block text-sm font-extrabold uppercase tracking-[0.04em]" x-text="day.weekday"></span>
                        <span class="mt-1 block text-sm font-bold" x-text="day.label"></span>
                    </button>
                </template>
            </div>
        </div>
    </section>

    <p class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-show="error" x-text="error" role="alert"></p>

    <section class="casa-card overflow-hidden" x-bind:aria-busy="loading">
        <div class="flex flex-col gap-2 border-b border-casa-border bg-casa-paper px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="casa-section-label" x-text="mode === 'availability' ? '{{ __('Therapist coverage') }}' : '{{ __('Appointment timeline') }}'"></p>
                <h2 class="mt-1 font-display text-xl font-black text-casa-text" x-text="selectedDateLabel"></h2>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm font-bold text-casa-muted">
                <span class="casa-filter-chip"><span class="me-1 size-2 rounded-full bg-casa-palm"></span>{{ __('Confirmed') }}</span>
                <span class="casa-filter-chip"><span class="me-1 size-2 rounded-full bg-red-500"></span>{{ __('Cancelled / no-show') }}</span>
                <span class="casa-filter-chip"><span class="me-1 size-2 rounded-full bg-casa-cacao"></span>{{ __('Unavailable') }}</span>
                <x-status-badge x-show="loading">{{ __('Loading') }}</x-status-badge>
            </div>
        </div>

        <div class="p-4 lg:hidden">
            @if ($isOperationsCalendar)
                <button
                    type="button"
                    class="casa-button-primary mb-4 w-full"
                    x-show="mode === 'bookings'"
                    x-on:click="chooseBooking(null, { time: '13:00', label: '1:00 PM' })"
                >
                    {{ __('Add appointment on this day') }}
                </button>
            @endif
            <div class="space-y-3" x-show="selectedAgendaEvents.length">
                <template x-for="event in selectedAgendaEvents" x-bind:key="event.id">
                    <a x-bind:href="event.detail_url || '#'" class="block rounded-2xl border border-casa-border bg-casa-paper p-4 shadow-sm transition hover:border-casa-brass">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-extrabold uppercase tracking-[0.05em] text-casa-cacao" x-text="resourceName(event.resource_id)"></p>
                                <p class="mt-1 font-bold text-casa-text" x-text="event.title"></p>
                                <p class="mt-1 text-sm text-casa-muted" x-text="event.subtitle"></p>
                            </div>
                            <span class="casa-badge border-casa-border bg-casa-sand/60 text-casa-cacao" x-text="statusLabel(event.status)"></span>
                        </div>
                        <p class="mt-3 text-sm font-bold text-casa-muted" x-text="eventTimeRange(event)"></p>
                    </a>
                </template>
            </div>
            <x-empty-state x-show="!loading && !selectedAgendaEvents.length" title="{{ __('Nothing scheduled for this day') }}" description="{{ __('Choose another day or add availability from an open calendar time.') }}" />
        </div>

        <div class="hidden overflow-x-auto lg:block" data-calendar-scroll>
            <div class="min-w-max">
                <div class="sticky top-0 z-30 grid border-b border-casa-border bg-casa-paper" x-bind:style="`grid-template-columns:5.5rem repeat(${Math.max(resources.length, 1)}, minmax(13rem, 1fr))`">
                    <div class="sticky start-0 z-40 border-e border-casa-border bg-casa-paper p-3 text-center text-sm font-extrabold uppercase tracking-[0.05em] text-casa-muted">{{ __('Time') }}</div>
                    <template x-for="resource in resources" x-bind:key="resource.id">
                        <div class="border-e border-casa-border px-3 py-2 text-center">
                            <p class="text-sm font-extrabold text-casa-text" x-text="resource.name"></p>
                            <p class="mt-0.5 text-sm font-bold text-casa-muted" x-text="resource.subtitle"></p>
                        </div>
                    </template>
                </div>

                <div class="grid" x-bind:style="`grid-template-columns:5.5rem repeat(${Math.max(resources.length, 1)}, minmax(13rem, 1fr))`">
                    <div class="sticky start-0 z-20 border-e border-casa-border bg-casa-paper">
                        <template x-for="slot in timeSlots" x-bind:key="slot.time">
                            <div class="h-11 border-b border-casa-border/70 px-2 pt-1.5 text-end text-sm font-bold text-casa-muted" x-text="slot.label"></div>
                        </template>
                    </div>

                    <template x-for="resource in resources" x-bind:key="resource.id">
                        <div class="relative border-e border-casa-border bg-casa-paper" x-bind:style="`height:${timelineHeight}px`">
                            <div class="absolute inset-0 z-0">
                                <template x-for="slot in timeSlots" x-bind:key="slot.time">
                                    <div class="h-11 border-b border-casa-border/70">
                                        <button
                                            x-show="slotCanCreate(resource.id, slot)"
                                            type="button"
                                            class="block h-full w-full transition hover:bg-casa-palm/10 focus:bg-casa-palm/10"
                                            x-on:click="chooseBooking(resource, slot)"
                                            x-bind:aria-label="`Create appointment for ${resource.name} on ${selectedDate} at ${slot.label}`"
                                        ></button>
                                        <button
                                            x-show="mode === 'availability' && canEditAvailability"
                                            type="button"
                                            class="block h-full w-full transition hover:bg-casa-brass/10"
                                            x-on:click="chooseAvailability(resource, slot)"
                                            x-bind:aria-label="`Add availability for ${resource.name} on ${selectedDate} at ${slot.label}`"
                                        ></button>
                                    </div>
                                </template>
                            </div>

                            <template x-for="event in backgroundEvents(resource.id)" x-bind:key="event.id">
                                <a
                                    x-bind:href="event.detail_url || '#'"
                                    class="absolute inset-x-1 z-10 overflow-hidden rounded-lg border px-2 py-1 text-sm font-extrabold uppercase tracking-[0.03em]"
                                    x-bind:class="[backgroundClass(event), event.read_only ? 'pointer-events-none' : '']"
                                    x-bind:style="backgroundStyle(event)"
                                    x-bind:aria-label="`${event.title}, ${eventTimeRange(event)}`"
                                >
                                    <span x-text="event.title"></span>
                                </a>
                            </template>

                            <template x-for="event in positionedEvents(resource.id)" x-bind:key="event.id">
                                <a
                                    x-bind:href="event.detail_url"
                                    class="absolute z-20 overflow-hidden rounded-xl border p-2 text-left shadow-sm transition hover:z-30 hover:shadow-casa-card"
                                    x-bind:class="eventClass(event)"
                                    x-bind:style="eventStyle(event)"
                                    x-bind:aria-label="`${statusLabel(event.status)}: ${event.title}, ${eventTimeRange(event)}`"
                                >
                                    <span class="block truncate text-sm font-extrabold" x-text="event.title"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </section>

    @if ($canEditAvailability)
        <x-modal name="calendar-availability-create" maxWidth="2xl" focusable>
            <div class="p-6">
                <p class="casa-section-label">{{ __('Availability editor') }}</p>
                <h2 class="mt-2 font-display text-2xl font-black text-casa-text">{{ __('How should this time apply?') }}</h2>
                <p class="mt-2 text-sm leading-6 text-casa-muted" x-show="availabilitySelection">
                    <span class="font-bold text-casa-text" x-text="availabilitySelection?.staffName"></span>
                    · <span x-text="availabilitySelection?.date"></span>
                    · <span x-text="availabilitySelection?.label"></span>
                </p>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <a x-bind:href="availabilityUrl('weekly')" class="rounded-2xl border border-casa-border bg-casa-sand/50 p-5 transition hover:border-casa-palm hover:bg-casa-paper">
                        <span class="text-sm font-extrabold uppercase tracking-[0.05em] text-casa-palm">{{ __('Repeat weekly') }}</span>
                        <strong class="mt-2 block text-base text-casa-text">{{ __('Recurring shift') }}</strong>
                        <span class="mt-2 block text-sm leading-6 text-casa-muted">{{ __('Create normal working availability for this weekday.') }}</span>
                    </a>
                    <a x-bind:href="availabilityUrl('exception')" class="rounded-2xl border border-casa-border bg-casa-sand/50 p-5 transition hover:border-casa-cacao hover:bg-casa-paper">
                        <span class="text-sm font-extrabold uppercase tracking-[0.05em] text-casa-cacao">{{ __('This date only') }}</span>
                        <strong class="mt-2 block text-base text-casa-text">{{ __('Schedule exception') }}</strong>
                        <span class="mt-2 block text-sm leading-6 text-casa-muted">{{ __('Add availability or block time for this one date.') }}</span>
                    </a>
                </div>
                <button type="button" class="casa-button-secondary mt-6 w-full" x-on:click="$dispatch('close-modal', 'calendar-availability-create')">{{ __('Cancel') }}</button>
            </div>
        </x-modal>
    @endif
</div>
