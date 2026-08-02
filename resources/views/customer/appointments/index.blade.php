<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-eyebrow">{{ __('Customer lounge') }}</p>
            <h1 class="mt-3 font-editorial text-4xl font-semibold text-casa-text">{{ __('My appointment calendar') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Follow confirmed, completed, and cancelled visits in one calm monthly view.') }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('customer.appointments.history') }}" class="casa-button-secondary">{{ __('My appointments') }}</a>
            <a href="{{ route('customer.appointments.create') }}" class="casa-button-primary" data-panel-link data-turbo="false">
                <x-nav-icon name="calendar" class="size-4" />
                {{ __('Book appointment') }}
            </a>
        </div>
    </x-slot>

    <div x-data="customerAppointmentCalendar({ feedUrl: @js(route('customer.appointments.calendar')), initialMonth: @js($initialMonth) })" x-init="init()" class="space-y-4" data-customer-appointment-calendar>
        <x-stat-strip :items="[
            ['label' => __('Upcoming'), 'value' => $summary['upcoming'], 'meta' => __('Confirmed visits ahead'), 'tone' => 'green'],
            ['label' => __('Cancelled'), 'value' => $summary['cancelled'], 'meta' => __('Visits no longer scheduled'), 'tone' => 'gold'],
            ['label' => __('Completed'), 'value' => $summary['completed'], 'meta' => __('Finished visits'), 'tone' => 'dark'],
        ]" />

        <section class="casa-editorial-card p-4 sm:p-5" x-bind:aria-busy="loading">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex w-full min-w-0 items-center gap-2 sm:w-auto">
                    <button type="button" class="casa-icon-button" x-on:click="previousMonth()" aria-label="{{ __('Previous month') }}"><svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m15 18-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                    <h2 class="min-w-0 flex-1 text-center font-editorial text-2xl font-semibold leading-tight text-casa-text sm:min-w-44" x-text="monthLabel"></h2>
                    <button type="button" class="casa-icon-button" x-on:click="nextMonth()" aria-label="{{ __('Next month') }}"><svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                </div>
                <label class="block sm:min-w-44"><span class="sr-only">{{ __('Filter appointment status') }}</span><select class="casa-input" x-model="statusFilter" x-on:change="load()"><option value="">{{ __('All appointment statuses') }}</option>@foreach (\App\Models\Appointment::STATUSES as $status)<option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></label>
            </div>

            <p class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-show="error" x-text="error" role="alert"></p>

            <div class="mt-5 overflow-x-auto rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-casa-palm" tabindex="0" role="region" aria-label="{{ __('Scrollable appointment month') }}" data-calendar-scroll>
                <div class="min-w-[22rem]">
                    <div class="grid grid-cols-7 gap-1 text-center text-sm font-extrabold uppercase tracking-[0.05em] text-casa-muted sm:gap-2"><template x-for="dayName in weekDays" x-bind:key="dayName"><span x-text="dayName"></span></template></div>
                    <div class="mt-2 grid grid-cols-7 gap-1 sm:gap-2" role="grid" aria-label="{{ __('Appointment month') }}">
                        <template x-for="day in calendarDays" x-bind:key="day.date">
                            <button type="button" class="min-h-16 rounded-xl border p-2 text-left transition sm:min-h-24 sm:p-2.5" x-on:click="selectDate(day.date)" x-on:keydown.right.prevent="moveDate(day.date, 1)" x-on:keydown.left.prevent="moveDate(day.date, -1)" x-on:keydown.down.prevent="moveDate(day.date, 7)" x-on:keydown.up.prevent="moveDate(day.date, -7)" x-bind:aria-label="`${day.date}, ${day.events.length} appointments`" x-bind:tabindex="selectedDate === day.date ? 0 : -1" x-bind:data-customer-calendar-day="day.date" x-bind:class="selectedDate === day.date ? 'border-casa-palm bg-casa-palm text-white shadow-md' : day.inMonth ? 'border-casa-border bg-casa-paper text-casa-text hover:border-casa-brass' : 'border-casa-border/50 bg-casa-sand/30 text-casa-muted/45'" role="gridcell">
                                <span class="text-sm font-extrabold" x-text="day.label"></span>
                                <span class="mt-2 flex flex-wrap gap-1" x-show="day.statuses.length">
                                    <template x-for="status in day.statuses" x-bind:key="status">
                                        <span class="inline-flex items-center justify-center size-2.5 rounded-full ring-1 ring-white/70" x-bind:class="{'bg-casa-palm': status === 'confirmed', 'bg-casa-cacao': status === 'completed', 'bg-red-500': status === 'cancelled' || status === 'no_show'}">
                                            <span class="sr-only" x-text="status.replaceAll('_', ' ')"></span>
                                        </span>
                                    </template>
                                </span>
                                <span class="mt-2 hidden text-sm font-extrabold uppercase tracking-[0.04em] sm:block" x-show="day.events.length" x-text="`${day.events.length} visit${day.events.length === 1 ? '' : 's'}`"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </section>
    </div>

</x-app-layout>
