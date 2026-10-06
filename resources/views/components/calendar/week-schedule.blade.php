<section class="casa-card casa-week-schedule p-4" x-bind:aria-busy="loading" aria-labelledby="weekly-schedule-title" data-week-schedule>
    <div class="casa-week-toolbar">
        <div>
            <h2 id="weekly-schedule-title" class="text-xl font-extrabold text-casa-text">{{ __('Weekly therapist schedule') }}</h2>
            <p class="mt-1 text-sm text-casa-muted"><span x-text="weekLabel"></span> · {{ __('1:00 PM–midnight · Asia/Manila') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <label class="casa-week-therapist"><span class="sr-only">{{ __('Therapist') }}</span><select class="casa-input" x-model="staffFilter" x-on:change="load()">
                <option value="">{{ __('All therapists') }}</option>
                @foreach ($staffProfiles as $staff)<option value="{{ $staff->id }}">{{ $staff->user?->name }}</option>@endforeach
            </select></label>
            <button type="button" class="casa-icon-button" x-on:click="previousWeek()" aria-label="{{ __('Previous week') }}"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>
            <button type="button" class="casa-button-secondary" x-on:click="today()">{{ __('This week') }}</button>
            <button type="button" class="casa-icon-button" x-on:click="nextWeek()" aria-label="{{ __('Next week') }}"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button>
        </div>
    </div>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
        <div class="flex gap-2" role="group" aria-label="{{ __('Schedule mode') }}">
            <button type="button" class="casa-week-mode" x-bind:aria-pressed="mode === 'bookings'" x-on:click="setMode('bookings')">{{ __('Bookings') }}</button>
            <button type="button" class="casa-week-mode" x-bind:aria-pressed="mode === 'availability'" x-on:click="setMode('availability')">{{ __('Availability') }}</button>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-sm text-casa-muted" x-show="loading" role="status">{{ __('Loading schedule…') }}</span>
            <button type="button" class="casa-button-secondary" x-on:click="filtersOpen = !filtersOpen" x-bind:aria-expanded="filtersOpen" aria-controls="week-schedule-filters">{{ __('Filters') }}<span x-show="serviceFilter || statusFilter" class="ms-1" x-text="Number(Boolean(serviceFilter)) + Number(Boolean(statusFilter))"></span></button>
        </div>
    </div>
    <div id="week-schedule-filters" class="mt-3 grid gap-3 sm:grid-cols-3" x-show="filtersOpen" x-cloak>
        <label><span class="casa-label">{{ __('Service') }}</span><select class="casa-input mt-1" x-model="serviceFilter" x-on:change="load()" x-bind:disabled="mode !== 'bookings'"><option value="">{{ __('All services') }}</option>@foreach ($services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select></label>
        <label><span class="casa-label">{{ __('Status') }}</span><select class="casa-input mt-1" x-model="statusFilter" x-on:change="load()" x-bind:disabled="mode !== 'bookings'"><option value="">{{ __('All statuses') }}</option>@foreach (\App\Models\Appointment::STATUSES as $status)<option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></label>
        <button type="button" class="casa-button-secondary self-end" x-show="staffFilter || serviceFilter || statusFilter" x-on:click="staffFilter = ''; serviceFilter = ''; statusFilter = ''; load()">{{ __('Clear filters') }}</button>
    </div>
    <div class="mt-3 overflow-x-auto rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-casa-palm" x-bind:class="mode === 'availability' ? '' : 'lg:hidden'" tabindex="0" role="region" aria-label="{{ __('Scrollable days in selected week') }}" data-calendar-scroll>
        <div class="grid min-w-[22rem] grid-cols-7 gap-1" role="tablist" aria-label="{{ __('Days in selected week') }}">
            <template x-for="day in dayOptions" x-bind:key="day.date">
                <button type="button" class="casa-week-day-tab" role="tab" x-bind:aria-selected="selectedDate === day.date" x-bind:tabindex="selectedDate === day.date ? 0 : -1" x-bind:data-operational-day="day.date" x-on:click="selectedDate = day.date" x-on:keydown.right.prevent="moveSelectedDay(day.date, 1)" x-on:keydown.left.prevent="moveSelectedDay(day.date, -1)" x-on:keydown.home.prevent="focusWeekBoundary(false)" x-on:keydown.end.prevent="focusWeekBoundary(true)">
                    <span class="block font-semibold" x-text="day.weekday"></span><span class="block text-sm" x-text="day.label"></span>
                </button>
            </template>
        </div>
    </div>
    <div x-show="mode === 'bookings'">
        <div class="casa-week-viewport mt-3 hidden lg:block" tabindex="0" role="region" aria-label="{{ __('Weekly appointments, Monday through Sunday, 1 PM to midnight') }}" data-calendar-scroll>
            <div class="casa-week-grid">
                <div class="casa-week-grid-header">
                    <div class="casa-week-time-heading">{{ __('Time') }}</div>
                    <template x-for="day in dayOptions" x-bind:key="day.date"><div class="casa-week-column-heading" x-bind:class="day.isToday ? 'is-today' : ''"><span class="block font-bold" x-text="day.weekday"></span><span x-text="day.label"></span></div></template>
                </div>
                <div class="casa-week-grid-body">
                    <div class="casa-week-time-column">
                        <template x-for="slot in timeSlots" x-bind:key="slot.time"><div class="casa-week-time" x-bind:style="`height:${slotHeight}px`"><span x-show="slot.minutes % 60 === 0" x-text="slot.label"></span></div></template>
                    </div>
                    <template x-for="day in dayOptions" x-bind:key="day.date">
                        <div class="casa-week-day-column" x-bind:style="`height:${timelineHeight}px`">
                            <template x-for="slot in timeSlots" x-bind:key="slot.time"><div class="casa-week-slot" x-bind:style="`height:${slotHeight}px`"></div></template>
                            <template x-for="event in weekEvents(day.date)" x-bind:key="event.id">
                                <a class="casa-week-event" x-bind:class="weekEventClass(event)" x-bind:href="event.detail_url" x-bind:style="eventStyle(event)" x-bind:aria-label="weekEventDescription(event)" x-bind:title="weekEventDescription(event)">
                                    <span class="block font-semibold" x-text="weekEventTime(event)"></span>
                                    <span class="block font-bold" x-text="event.customer_name || event.title"></span>
                                    <span class="block" x-text="event.service_name || event.subtitle"></span>
                                    <span class="block" x-text="event.staff_name || resourceName(event.resource_id)"></span>
                                    <span class="casa-week-event-status" x-text="statusLabel(event.status)"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        <p class="mt-3 text-sm text-casa-muted hidden lg:block" x-show="!loading && !error && !hasWeekBookings">{{ __('No appointments scheduled this week. Choose another week or add an appointment.') }}</p>
        <div class="mt-3 space-y-3 lg:hidden">
            <h3 class="font-bold text-casa-text" x-text="selectedDateLabel"></h3>
            <button type="button" class="casa-button-secondary w-full" x-on:click="chooseBooking(null, { time: '13:00' })">{{ __('Add appointment on this day') }}</button>
            <template x-for="event in selectedAgendaEvents" x-bind:key="event.id">
                <a class="casa-week-agenda-event" x-bind:href="event.detail_url">
                    <p class="font-semibold" x-text="eventTimeRange(event)"></p>
                    <p class="mt-1 font-bold" x-text="event.customer_name || event.title"></p>
                    <p class="text-sm" x-text="event.service_name || event.subtitle"></p>
                    <p class="text-sm" x-text="event.staff_name || resourceName(event.resource_id)"></p>
                    <span class="casa-appointment-status mt-2" x-text="statusLabel(event.status)"></span>
                </a>
            </template>
            <p class="py-3 text-sm text-casa-muted" x-show="!loading && !error && !selectedAgendaEvents.length">{{ __('Nothing scheduled for this day. Choose another day or add an appointment.') }}</p>
        </div>
    </div>
</section>
