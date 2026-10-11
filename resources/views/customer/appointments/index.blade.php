<x-app-layout page-title="My appointments">
    <x-slot name="header">
        <div class="customer-appointments-heading">
            <p class="customer-appointments-eyebrow">{{ __('Your wellness space') }}</p>
            <h1>{{ __('A little time for yourself.') }}</h1>
            <p class="customer-appointments-subtitle">{{ __('View your visits and plan your next moment of rest.') }}</p>
        </div>
        <div class="customer-appointments-actions">
            <a href="{{ route('customer.appointments.create') }}" class="casa-button-primary" data-panel-link data-turbo="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                {{ __('Book appointment') }}
            </a>
            <a href="{{ route('customer.appointments.history') }}" class="casa-button-secondary">{{ __('View history') }}</a>
        </div>
    </x-slot>

    <div class="customer-appointments" data-customer-appointments>
        <x-stat-strip presentation="cards" :items="[
            ['label' => __('Upcoming'), 'value' => $summary['upcoming'], 'tone' => 'green', 'icon' => 'calendar'],
            ['label' => __('Cancelled'), 'value' => $summary['cancelled'], 'tone' => 'red', 'icon' => 'cancel'],
            ['label' => __('Completed'), 'value' => $summary['completed'], 'tone' => 'green', 'icon' => 'check'],
        ]" />

        <div class="customer-appointments-panels">
            <section class="customer-appointments-card customer-appointments-calendar"
                x-data="customerAppointmentCalendar({ feedUrl: @js(route('customer.appointments.calendar')), historyUrl: @js(route('customer.appointments.history')), initialMonth: @js($initialMonth) })"
                x-bind:aria-busy="loading ? 'true' : 'false'" aria-label="{{ __('Your appointment calendar') }}" data-customer-appointment-calendar>
                <div class="customer-appointments-calendar-toolbar" x-cloak>
                    <div class="customer-appointments-month-navigation">
                        <button type="button" class="customer-appointments-icon-button" x-on:click="previousMonth()" aria-label="{{ __('Previous month') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                        <h2 x-text="monthLabel" aria-live="polite"></h2>
                        <button type="button" class="customer-appointments-icon-button" x-on:click="nextMonth()" aria-label="{{ __('Next month') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                    </div>
                    <label class="customer-appointments-status-filter">
                        <span class="sr-only">{{ __('Filter appointment status') }}</span>
                        <select class="casa-input" x-model="statusFilter" x-on:change="load()">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach (\App\Models\Appointment::STATUSES as $status)
                                <option value="{{ $status }}">{{ $status === 'no_show' ? __('No-show') : ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="customer-appointments-calendar-error" x-show="error" x-cloak role="alert">
                    <p x-text="error"></p>
                    <button type="button" class="casa-button-secondary" x-on:click="load()">{{ __('Retry') }}</button>
                </div>
                <p class="customer-appointments-calendar-message" role="status" aria-live="polite" x-cloak x-show="loading || (!error && !hasMonthEvents)" x-text="loading ? 'Loading your appointments…' : (statusFilter ? 'No matching appointments this month.' : 'No appointments this month.')"></p>

                <div class="customer-appointments-calendar-scroll" tabindex="0" role="region" aria-label="{{ __('Scrollable appointment month') }}" data-calendar-scroll x-cloak>
                    <div class="customer-appointments-month-grid" role="grid" aria-label="{{ __('Appointment month') }}">
                        <div class="customer-appointments-weekdays" role="row">
                            <template x-for="dayName in weekDays" x-bind:key="dayName"><span role="columnheader" x-text="dayName"></span></template>
                        </div>
                        <template x-for="row in calendarRows" x-bind:key="row[0].date">
                            <div class="customer-appointments-week" role="row">
                                <template x-for="(day, dayIndex) in row" x-bind:key="day.date">
                                    <div class="customer-appointments-day" x-bind:class="{ 'is-adjacent': !day.inMonth, 'is-selected': selectedDate === day.date }" role="gridcell" x-bind:aria-selected="selectedDate === day.date">
                                        <button type="button" class="customer-appointments-date" x-on:click="selectDate(day.date)"
                                            x-on:keydown.right.prevent="moveDate(day.date, 1)" x-on:keydown.left.prevent="moveDate(day.date, -1)"
                                            x-on:keydown.down.prevent="moveDate(day.date, 7)" x-on:keydown.up.prevent="moveDate(day.date, -7)"
                                            x-on:keydown.home.prevent="moveDate(day.date, -dayIndex)" x-on:keydown.end.prevent="moveDate(day.date, 6 - dayIndex)"
                                            x-bind:aria-label="dayDescription(day)" x-bind:aria-current="day.isToday ? 'date' : null"
                                            x-bind:tabindex="selectedDate === day.date ? 0 : -1" x-bind:data-customer-calendar-day="day.date">
                                            <span x-bind:class="{ 'is-today': day.isToday }" x-text="day.label"></span>
                                        </button>
                                        <div class="customer-appointments-day-events">
                                            <template x-for="event in day.events" x-bind:key="event.id">
                                                <a class="customer-appointments-event" x-bind:data-status="event.status" x-bind:href="event.detail_url" x-bind:aria-label="eventDescription(event)" x-bind:title="eventDescription(event)">
                                                    <span x-text="statusLabel(event.status)"></span><span x-text="eventTime(event)"></span>
                                                </a>
                                            </template>
                                            <a class="customer-appointments-more" x-show="day.remaining" x-bind:href="historyUrl(day.date)" x-bind:aria-label="'View all ' + day.total + ' appointments on ' + day.date" x-text="'+' + day.remaining + ' more'"></a>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="customer-appointments-legend" aria-label="{{ __('Appointment statuses') }}" x-cloak>
                    @foreach (\App\Models\Appointment::STATUSES as $status)
                        <span><i data-status="{{ $status }}" aria-hidden="true"></i>{{ $status === 'no_show' ? __('No-show') : ucfirst($status) }}</span>
                    @endforeach
                </div>
                <noscript><p class="customer-appointments-empty">{{ __('Open your appointment history to view your visits without JavaScript.') }} <a href="{{ route('customer.appointments.history') }}">{{ __('View history') }}</a></p></noscript>
            </section>

            <aside class="customer-appointments-aside" aria-label="{{ __('Your visits') }}">
                <section class="customer-appointments-card customer-appointments-visit" data-next-visit>
                    <div class="customer-appointments-card-heading">
                        <h2>{{ __('Your next visit') }}</h2>
                        @if ($nextVisit)<span class="customer-appointments-badge">{{ __('Confirmed') }}</span>@endif
                    </div>
                    @if ($nextVisit)
                        @php
                            $timezone = config('casa.business_hours.timezone', 'Asia/Manila');
                            $nextStart = $nextVisit->scheduled_start_at->copy()->timezone($timezone);
                            $nextEnd = $nextVisit->scheduled_end_at?->copy()->timezone($timezone);
                        @endphp
                        <h3>{{ $nextVisit->service?->name ?? __('Spa service') }}</h3>
                        <ul class="customer-appointments-visit-schedule">
                            <li><x-nav-icon name="calendar" /><span>{{ $nextStart->format('l, F j') }}</span></li>
                            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round"/></svg><span>{{ $nextStart->format('g:i A') }}@if ($nextEnd) – {{ $nextEnd->format('g:i A') }}@if (! $nextStart->isSameDay($nextEnd)) {{ __('(next day)') }}@endif @endif</span></li>
                            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/></svg><span>{{ $timezone }}</span></li>
                        </ul>
                        <dl class="customer-appointments-visit-facts">
                            <div><dt>{{ __('Therapist') }}</dt><dd>{{ $nextVisit->staffProfile?->user?->name ?: __('Unassigned') }}</dd></div>
                            <div><dt>{{ __('Duration') }}</dt><dd>{{ $nextEnd ? (int) $nextStart->diffInMinutes($nextEnd).' '.__('minutes') : __('Not recorded') }}</dd></div>
                        </dl>
                        <a href="{{ route('customer.appointments.show', $nextVisit) }}" class="casa-button-primary customer-appointments-card-action">{{ __('View details') }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
                    @else
                        <p class="customer-appointments-empty">{{ __('You have no upcoming visits. Make time for a little rest when you’re ready.') }}</p>
                        <a href="{{ route('customer.appointments.create') }}" class="casa-button-primary customer-appointments-card-action" data-panel-link data-turbo="false">{{ __('Book appointment') }}</a>
                    @endif
                </section>

                <section class="customer-appointments-card customer-appointments-feedback" data-feedback-visit>
                    <h2>{{ __('How was your last visit?') }}</h2>
                    @if ($feedbackVisit)
                        <p class="customer-appointments-muted">{{ __('Share feedback on your completed treatment.') }}</p>
                        <h3>{{ $feedbackVisit->service?->name ?? __('Spa service') }}</h3>
                        <p class="customer-appointments-feedback-date"><x-nav-icon name="calendar" /><span>{{ ($feedbackVisit->scheduled_start_at ?? $feedbackVisit->requested_start_at)?->copy()->timezone(config('casa.business_hours.timezone', 'Asia/Manila'))->format('F j, Y') }}</span></p>
                        <a href="{{ route('customer.feedback.create', ['appointment_id' => $feedbackVisit->id]) }}" class="casa-button-secondary customer-appointments-card-action">{{ __('Share feedback') }}</a>
                    @else
                        <p class="customer-appointments-empty">{{ __('There are no completed visits awaiting feedback. After your next treatment, you can share your experience here.') }}</p>
                        <a href="{{ route('customer.feedback.index') }}" class="casa-button-secondary customer-appointments-card-action">{{ __('My feedback') }}</a>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
