@props(['showStaffCount' => false])
<div class="booking-calendar-layout" aria-busy="false" x-bind:aria-busy="loading">
    <div class="booking-calendar">
        <div class="booking-calendar-bar">
            <button type="button" class="casa-icon-button" x-on:click="previousMonth()" aria-label="{{ __('Previous month') }}">‹</button>
            <h3 x-text="monthLabel"></h3>
            <button type="button" class="casa-icon-button" x-on:click="nextMonth()" aria-label="{{ __('Next month') }}">›</button>
        </div>
        <div class="booking-weekdays"><template x-for="dayName in weekDays" x-bind:key="dayName"><span x-text="dayName"></span></template></div>
        <div class="booking-month-grid" role="group" aria-label="{{ __('Available appointment dates') }}">
            <template x-for="day in calendarDays" x-bind:key="day.key">
                <div>
                    <button type="button" class="booking-calendar-day" x-show="day.date" x-bind:disabled="!day.available || loading || !!error"
                        x-bind:class="{ 'is-open': day.available, 'is-selected': day.date === selectedDate }"
                        x-bind:aria-label="`${day.date}, ${day.available ? 'store open' : 'unavailable'}`"
                        x-bind:aria-pressed="day.date === selectedDate" x-bind:data-booking-calendar-day="day.date"
                        x-on:click="selectDate(day.date)" x-on:keydown.right.prevent="moveAvailableDate(day.date, 1)" x-on:keydown.left.prevent="moveAvailableDate(day.date, -1)"
                        x-on:keydown.down.prevent="moveAvailableDate(day.date, 7)" x-on:keydown.up.prevent="moveAvailableDate(day.date, -7)">
                        <strong x-text="day.label"></strong><span class="booking-date-open" x-show="day.available">{{ __('Open') }}</span>
                        <span class="booking-date-preview" x-show="day.available"><template x-for="slot in day.previewSlots" x-bind:key="slot.starts_at"><span x-text="slot.label"></span></template><span x-show="day.moreSlots" x-text="moreSlotsLabel(day)"></span></span>
                    </button>
                </div>
            </template>
        </div>
        <p class="booking-calendar-legend"><span class="booking-open-dot" aria-hidden="true"></span>{{ __('Open dates') }}<span class="booking-selected-dot" aria-hidden="true"></span>{{ __('Selected date') }}</p>
    </div>
    <section class="booking-time-panel" aria-live="polite">
        <h3>{{ __('Choose a time') }}</h3>
        <p class="booking-time-date" x-text="selectedDate ? selectedDateLabel : 'Choose a highlighted date.'"></p>
        <p class="booking-calendar-message" x-show="!serviceId">{{ __('Choose a treatment first to load available slots.') }}</p>
        <p class="booking-calendar-message" x-show="loading">{{ __('Checking available times…') }}</p>
        <div class="booking-calendar-error" x-show="error" x-cloak role="alert"><p x-text="error"></p><button type="button" class="booking-text-button" x-on:click="fetchAvailability()">{{ __('Retry') }}</button></div>
        <p class="booking-calendar-message" x-show="serviceId && !loading && !error && !hasOpenDates">{{ __('No future store-open dates this month. Try another month.') }}</p>
        <div class="booking-time-select">
            <x-input-label for="booking_start_time" :value="__('Start time')" />
            <select id="booking_start_time" class="casa-input mt-2" x-model="selectedSlot" x-on:change="startChanged()" x-bind:disabled="!selectedDate || loading || !!error" required>
                <option value="">{{ __('Select a start time') }}</option>
                <template x-if="canUsePersistedSchedule && !selectedSlotData"><option x-bind:value="persistedScheduledStart" x-text="summaryTime + ' · Current schedule'"></option></template>
            <template x-for="slot in selectedDateSlots" x-bind:key="slot.starts_at">
                <option x-bind:value="slot.starts_at.replace(' ', 'T').slice(0, 16)" x-bind:disabled="slot.staff_count < 1" x-text="slot.label + (slot.staff_count < 1 ? ' · Unavailable' : {{ $showStaffCount ? "' · ' + slot.staff_count + (slot.staff_count === 1 ? ' therapist' : ' therapists')" : "''" }})"></option>
            </template>
            </select>
        </div>
        <p class="booking-calendar-message" x-show="selectedDate && !hasAvailableTimes && !loading && !error">{{ __('The store is open, but no start times are available for this treatment. Choose another date or change the treatment.') }}</p>
        {{ $assignment ?? '' }}
        @unless ($showStaffCount)<p class="booking-calendar-message">{{ __('An available therapist will be assigned when you confirm.') }}</p>@endunless
        <p class="booking-hours">{{ __('Asia/Manila · 1:00 PM–midnight') }}<br>{{ __('The full treatment must fit within available hours.') }}</p>
    </section>
</div>
