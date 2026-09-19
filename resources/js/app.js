import './bootstrap';
import { initializeBookingNotifications } from './booking-notifications';
import { initializeSidebar } from './sidebar';

import { session as turboSession } from '@hotwired/turbo';
import Alpine from 'alpinejs';
import QRCode from 'qrcode';
import { Html5Qrcode } from 'html5-qrcode';

turboSession.drive = false;

window.Alpine = Alpine;
initializeSidebar(Alpine);

const modalStoreName = 'casaModal';

const syncBodyScrollLock = () => {
    if (!document.body) {
        return;
    }

    const modalIsOpen = Boolean(Alpine.store(modalStoreName)?.active);
    const panelIsOpen = document.querySelector('[data-panel-host]')?.classList.contains('is-open') ?? false;

    document.body.classList.toggle('overflow-y-hidden', modalIsOpen || panelIsOpen);
};

Alpine.store(modalStoreName, {
    active: null,
    trigger: null,

    open(name, trigger = null) {
        if (typeof name !== 'string' || name.length === 0) {
            return;
        }

        this.active = name;
        this.trigger = trigger instanceof HTMLElement ? trigger : document.activeElement;

        syncBodyScrollLock();
    },

    close(name = this.active) {
        if (name && name !== this.active) {
            return;
        }

        const trigger = this.trigger;

        this.active = null;
        this.trigger = null;

        syncBodyScrollLock();

        window.requestAnimationFrame(() => {
            if (trigger instanceof HTMLElement && trigger.isConnected) {
                trigger.focus({ preventScroll: true });
            }
        });
    },
});

const modalStore = () => Alpine.store(modalStoreName);

window.casaModal = ({ name, initialShow = false, focusable = false }) => ({
    name,
    initialShow,
    focusable,

    get show() {
        return Alpine.store(modalStoreName).active === this.name;
    },

    init() {
        if (this.initialShow) {
            modalStore().open(this.name);
        }

        this.$watch('show', (show) => {
            if (show && this.focusable) {
                window.setTimeout(() => this.firstFocusable()?.focus(), 100);
            }
        });

        if (this.show && this.focusable) {
            window.setTimeout(() => this.firstFocusable()?.focus(), 100);
        }
    },

    close() {
        modalStore().close(this.name);
    },

    focusables() {
        const selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])';

        return [...this.$el.querySelectorAll(selector)].filter((element) => !element.hasAttribute('disabled'));
    },

    firstFocusable() {
        return this.focusables()[0];
    },

    handleTab(event) {
        const focusable = this.focusables();

        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }

        const currentIndex = focusable.indexOf(document.activeElement);
        const nextIndex = event.shiftKey
            ? (currentIndex <= 0 ? focusable.length - 1 : currentIndex - 1)
            : (currentIndex === -1 || currentIndex === focusable.length - 1 ? 0 : currentIndex + 1);

        event.preventDefault();
        focusable[nextIndex].focus();
    },
});

window.addEventListener('open-modal', (event) => {
    modalStore().open(event.detail, event.target);
});

window.addEventListener('close-modal', (event) => {
    modalStore().close(event.detail);
});

const dateFromIso = (value) => value?.slice(0, 10) || '';

const localDate = (value) => new Date(`${value}T00:00:00`);

const dateKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

const addCalendarDays = (value, amount) => {
    const date = localDate(value);
    date.setDate(date.getDate() + amount);

    return dateKey(date);
};

const calendarMinutes = (value, selectedDate) => {
    if (!value) {
        return 0;
    }

    const [hour, minute] = value.slice(11, 16).split(':').map(Number);
    const dayOffset = dateFromIso(value) > selectedDate ? 1440 : 0;

    return (hour * 60) + minute + dayOffset;
};

window.operationalCalendar = (config) => ({
    feedUrl: config.feedUrl,
    createUrl: config.createUrl || '',
    weeklyCreatePattern: config.weeklyCreatePattern || '',
    exceptionCreatePattern: config.exceptionCreatePattern || '',
    role: config.role,
    canEditAvailability: Boolean(config.canEditAvailability),
    mode: config.initialMode || 'bookings',
    weekStart: config.initialWeek,
    selectedDate: config.initialDate || config.initialWeek,
    staffFilter: '',
    serviceFilter: '',
    statusFilter: '',
    resources: [],
    events: [],
    loading: false,
    error: '',
    availabilitySelection: null,
    slotHeight: 32,
    openingMinutes: 13 * 60,
    closingMinutes: 24 * 60,

    init() {
        const today = dateKey(new Date());
        const weekEnd = addCalendarDays(this.weekStart, 7);

        if (today >= this.weekStart && today < weekEnd) {
            this.selectedDate = today;
        }

        this.load();
    },

    get weekEnd() {
        return addCalendarDays(this.weekStart, 7);
    },

    get weekLabel() {
        const start = localDate(this.weekStart);
        const end = localDate(addCalendarDays(this.weekStart, 6));
        const startLabel = start.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        const endLabel = end.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });

        return `${startLabel} – ${endLabel}`;
    },

    get dayOptions() {
        return Array.from({ length: 7 }, (_, index) => {
            const date = addCalendarDays(this.weekStart, index);
            const parsed = localDate(date);

            return {
                date,
                weekday: parsed.toLocaleDateString(undefined, { weekday: 'short' }),
                label: parsed.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }),
                isToday: date === dateKey(new Date()),
            };
        });
    },

    get timeSlots() {
        const slots = [];

        for (let minutes = this.openingMinutes; minutes < this.closingMinutes; minutes += 30) {
            const hour = Math.floor(minutes / 60) % 24;
            const minute = minutes % 60;
            const date = new Date(2000, 0, 1, hour, minute);

            slots.push({
                minutes,
                time: `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`,
                label: date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }),
            });
        }

        return slots;
    },

    get timelineHeight() {
        return this.timeSlots.length * this.slotHeight;
    },

    get selectedDateLabel() {
        return localDate(this.selectedDate).toLocaleDateString(undefined, {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        });
    },

    get selectedAgendaEvents() {
        return this.events
            .filter((event) => dateFromIso(event.starts_at) === this.selectedDate
                && (this.mode === 'availability'
                    ? event.kind !== 'availability'
                    : !['availability', 'weekly_availability', 'available_exception', 'unavailable_exception'].includes(event.kind)))
            .sort((a, b) => a.starts_at.localeCompare(b.starts_at));
    },

    resourceName(resourceId) {
        return this.resources.find((resource) => String(resource.id) === String(resourceId))?.name || 'Schedule';
    },

    statusLabel(status) {
        return String(status || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
    },

    eventTimeRange(event) {
        const options = { hour: 'numeric', minute: '2-digit' };
        return `${new Date(event.starts_at).toLocaleTimeString(undefined, options)} – ${new Date(event.ends_at).toLocaleTimeString(undefined, options)}`;
    },

    setMode(mode) {
        if (this.mode === mode) {
            return;
        }

        this.mode = mode;
        this.load();
    },

    previousWeek() {
        this.weekStart = addCalendarDays(this.weekStart, -7);
        this.selectedDate = this.weekStart;
        this.load();
    },

    nextWeek() {
        this.weekStart = addCalendarDays(this.weekStart, 7);
        this.selectedDate = this.weekStart;
        this.load();
    },

    today() {
        const now = new Date();
        const sunday = new Date(now);
        sunday.setDate(now.getDate() - now.getDay());
        this.weekStart = dateKey(sunday);
        this.selectedDate = dateKey(now);
        this.load();
    },

    moveSelectedDay(date, amount) {
        const nextDate = addCalendarDays(date, amount);

        if (nextDate < this.weekStart) {
            this.weekStart = addCalendarDays(this.weekStart, -7);
            this.load();
        } else if (nextDate >= this.weekEnd) {
            this.weekStart = addCalendarDays(this.weekStart, 7);
            this.load();
        }

        this.selectedDate = nextDate;
        window.requestAnimationFrame(() => document.querySelector(`[data-operational-day="${nextDate}"]`)?.focus());
    },

    focusWeekBoundary(useEnd) {
        const date = addCalendarDays(this.weekStart, useEnd ? 6 : 0);
        this.selectedDate = date;
        window.requestAnimationFrame(() => document.querySelector(`[data-operational-day="${date}"]`)?.focus());
    },

    load() {
        this.loading = true;
        this.error = '';

        return window.axios.get(this.feedUrl, {
            params: {
                mode: this.mode,
                start: this.weekStart,
                end: this.weekEnd,
                staff_profile_id: this.staffFilter || null,
                service_id: this.serviceFilter || null,
                status: this.statusFilter || null,
            },
        }).then((response) => {
            this.resources = response.data.resources || [];
            this.events = response.data.events || [];
        }).catch(() => {
            this.error = 'The schedule could not be loaded. Try refreshing this week.';
            this.resources = [];
            this.events = [];
        }).finally(() => {
            this.loading = false;
        });
    },

    backgroundEvents(resourceId) {
        const backgroundKinds = ['availability', 'weekly_availability', 'available_exception', 'unavailable_exception'];

        return this.events.filter((event) => String(event.resource_id) === String(resourceId)
            && dateFromIso(event.starts_at) === this.selectedDate
            && backgroundKinds.includes(event.kind));
    },

    positionedEvents(resourceId) {
        const foreground = this.events
            .filter((event) => String(event.resource_id) === String(resourceId)
                && dateFromIso(event.starts_at) === this.selectedDate
                && !['availability', 'weekly_availability', 'available_exception', 'unavailable_exception'].includes(event.kind))
            .map((event) => ({
                ...event,
                startMinutes: calendarMinutes(event.starts_at, this.selectedDate),
                endMinutes: calendarMinutes(event.ends_at, this.selectedDate),
            }))
            .sort((a, b) => a.startMinutes - b.startMinutes || a.endMinutes - b.endMinutes);
        const laneEnds = [];

        foreground.forEach((event) => {
            let lane = laneEnds.findIndex((end) => end <= event.startMinutes);

            if (lane === -1) {
                lane = laneEnds.length;
                laneEnds.push(event.endMinutes);
            } else {
                laneEnds[lane] = event.endMinutes;
            }

            event.lane = lane;
        });

        const laneCount = Math.max(laneEnds.length, 1);

        return foreground.map((event) => ({ ...event, laneCount }));
    },

    eventStyle(event) {
        const top = ((event.startMinutes - this.openingMinutes) / 30) * this.slotHeight;
        const height = Math.max(((event.endMinutes - event.startMinutes) / 30) * this.slotHeight, 40);
        const width = 100 / event.laneCount;

        return `top:${top}px;height:${height}px;left:calc(${event.lane * width}% + 3px);width:calc(${width}% - 6px)`;
    },

    backgroundStyle(event) {
        const start = calendarMinutes(event.starts_at, this.selectedDate);
        const end = calendarMinutes(event.ends_at, this.selectedDate);
        const top = ((start - this.openingMinutes) / 30) * this.slotHeight;
        const height = Math.max(((end - start) / 30) * this.slotHeight, this.slotHeight);

        return `top:${top}px;height:${height}px`;
    },

    eventClass(event) {
        return {
            confirmed: 'casa-calendar-event-confirmed',
            completed: 'casa-calendar-event-completed',
            cancelled: 'casa-calendar-event-cancelled',
            no_show: 'casa-calendar-event-cancelled',
            booking_blocker: 'casa-calendar-event-blocker',
        }[event.kind === 'booking_blocker' ? event.kind : event.status] || 'casa-calendar-event-confirmed';
    },

    backgroundClass(event) {
        return {
            availability: 'casa-calendar-availability',
            weekly_availability: 'casa-calendar-availability',
            available_exception: 'casa-calendar-availability-exception',
            unavailable_exception: 'casa-calendar-unavailable',
        }[event.kind] || 'casa-calendar-availability';
    },

    slotCanCreate(resourceId, slot) {
        if (resourceId === 'requests' || this.mode !== 'bookings') {
            return false;
        }

        const covered = this.backgroundEvents(resourceId).some((event) => {
            const start = calendarMinutes(event.starts_at, this.selectedDate);
            const end = calendarMinutes(event.ends_at, this.selectedDate);

            return event.kind === 'availability' && start <= slot.minutes && end > slot.minutes;
        });
        const occupied = this.positionedEvents(resourceId)
            .some((event) => event.startMinutes <= slot.minutes && event.endMinutes > slot.minutes);

        return covered && !occupied;
    },

    chooseBooking(resource, slot) {
        if (this.mode !== 'bookings') {
            return;
        }

        const time = slot?.time || '13:00';
        const startsAt = `${this.selectedDate}T${time}`;

        if (this.createUrl) {
            const url = new URL(this.createUrl, window.location.origin);
            url.searchParams.set('scheduled_start_at', startsAt);
            if (resource?.id && resource.id !== 'requests') {
                url.searchParams.set('staff_profile_id', String(resource.id));
            }
            void openPanel(url);
            return;
        }

        window.dispatchEvent(new CustomEvent('calendar-booking-selected', {
            detail: {
                staffId: resource?.id === 'requests' ? '' : String(resource?.id || ''),
                date: this.selectedDate,
                time,
                startsAt,
            },
        }));
        modalStore().open('calendar-appointment-create');
    },

    chooseAvailability(resource, slot) {
        if (!this.canEditAvailability || this.mode !== 'availability' || resource.id === 'requests') {
            return;
        }

        this.availabilitySelection = {
            staffId: resource.id,
            staffName: resource.name,
            date: this.selectedDate,
            time: slot.time,
            label: slot.label,
        };
        modalStore().open('calendar-availability-create');
    },

    availabilityUrl(type) {
        if (!this.availabilitySelection) {
            return '#';
        }

        const selection = this.availabilitySelection;
        const startMinutes = calendarMinutes(`${selection.date}T${selection.time}:00`, selection.date);
        const endMinutes = Math.min(startMinutes + 60, 1440);
        const endsNextDay = endMinutes >= 1440;
        const endHour = Math.floor(endMinutes / 60) % 24;
        const endMinute = endMinutes % 60;
        const endTime = `${String(endHour).padStart(2, '0')}:${String(endMinute).padStart(2, '0')}`;
        const pattern = type === 'weekly' ? this.weeklyCreatePattern : this.exceptionCreatePattern;
        const url = new URL(pattern.replace('__STAFF__', selection.staffId), window.location.origin);

        url.searchParams.set('start_time', selection.time);
        url.searchParams.set('end_time', endTime);
        url.searchParams.set('ends_next_day', endsNextDay ? '1' : '0');

        if (type === 'weekly') {
            url.searchParams.set('day_of_week', localDate(selection.date).getDay());
        } else {
            url.searchParams.set('exception_date', selection.date);
            url.searchParams.set('exception_type', 'unavailable');
        }

        return url.toString();
    },
});

window.weeklyRoster = (config) => ({
    feedUrl: config.feedUrl, copyUrl: config.copyUrl, shiftUrlPattern: config.shiftUrlPattern, publishUrlPattern: config.publishUrlPattern,
    week: config.initialWeek, staffProfileId: config.staffProfileId || null, resources: [], shifts: [], loading: false, error: '', modalError: '', selection: null, startTime: '13:00', endTime: '14:00', scheduleWeekId: null, publishedAt: null, hasDraft: false,
    get days() { return Array.from({ length: 7 }, (_, index) => { const date = addCalendarDays(this.week, index); return { date, label: localDate(date).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }) }; }); },
    get weekLabel() { const end = addCalendarDays(this.week, 6); return `${localDate(this.week).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} – ${localDate(end).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}`; },
    get times() { return Array.from({ length: 22 }, (_, index) => { const minutes = 780 + (index * 30); return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`; }); },
    get endTimes() { return [...this.times.slice(1), '00:00']; },
    timeLabel(time) { if (time === '00:00') return '12:00 midnight'; return new Date(`2000-01-01T${time}:00`).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }); },
    shiftLabel(shift) { return `${this.timeLabel(shift.start_time)} – ${shift.ends_next_day ? '12:00 midnight' : this.timeLabel(shift.end_time)}`; },
    shiftsFor(staffId, date) { return this.shifts.filter((shift) => String(shift.staff_profile_id) === String(staffId) && shift.schedule_date === date); },
    apply(data) { this.resources = (data.resources || []).filter((staff) => !this.staffProfileId || String(staff.id) === String(this.staffProfileId)); this.shifts = data.draft_shifts || []; this.scheduleWeekId = data.schedule_week_id; this.publishedAt = data.published_at; this.hasDraft = Boolean(data.has_draft); },
    load() { this.loading = true; this.error = ''; return window.axios.get(this.feedUrl, { params: { week: this.week } }).then((response) => this.apply(response.data)).catch((error) => { this.error = error.response?.data?.message || 'The weekly roster could not be loaded.'; }).finally(() => { this.loading = false; }); },
    moveWeek(days) { this.week = addCalendarDays(this.week, days); this.load(); },
    copyPrevious() { this.loading = true; this.error = ''; return window.axios.post(this.copyUrl, { week: this.week }).then((response) => this.apply(response.data)).catch((error) => { this.error = error.response?.data?.message || 'The previous roster could not be copied.'; }).finally(() => { this.loading = false; }); },
    openShift(staff, day) { this.selection = { staff, day }; this.startTime = '13:00'; this.endTime = '14:00'; this.modalError = ''; modalStore().open('weekly-roster-shift'); },
    saveShift() { if (!this.selection || !this.scheduleWeekId) return; this.modalError = ''; const url = this.shiftUrlPattern.replace('__WEEK__', this.scheduleWeekId); const endsNextDay = this.endTime === '00:00'; window.axios.post(url, { staff_profile_id: this.selection.staff.id, schedule_date: this.selection.day.date, start_time: this.startTime, end_time: this.endTime, ends_next_day: endsNextDay }).then((response) => { this.apply(response.data); modalStore().close('weekly-roster-shift'); }).catch((error) => { this.modalError = error.response?.data?.message || Object.values(error.response?.data?.errors || {}).flat()[0] || 'The shift could not be saved.'; }); },
    removeShift(shift) { if (!window.confirm(`Remove ${this.shiftLabel(shift)} from this draft?`)) return; const url = this.shiftUrlPattern.replace('__WEEK__', this.scheduleWeekId) + `/${shift.id}`; window.axios.delete(url).then((response) => this.apply(response.data)).catch((error) => { this.error = error.response?.data?.message || 'The draft shift could not be removed.'; }); },
    publish() { if (!this.scheduleWeekId || !window.confirm('Publish this team roster? Customers will be able to book the published hours.')) return; this.loading = true; this.error = ''; const url = this.publishUrlPattern.replace('__WEEK__', this.scheduleWeekId); window.axios.post(url).then((response) => this.apply(response.data)).catch((error) => { this.error = error.response?.data?.message || 'The roster could not be published.'; }).finally(() => { this.loading = false; }); },
});

window.customerAppointmentCalendar = (config) => ({
    feedUrl: config.feedUrl,
    month: config.initialMonth,
    selectedDate: '',
    statusFilter: '',
    events: [],
    loading: false,
    error: '',
    weekDays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],

    init() {
        const today = dateKey(new Date());
        this.selectedDate = today.slice(0, 7) === this.month ? today : `${this.month}-01`;
        this.load();
    },

    get monthLabel() {
        const [year, month] = this.month.split('-').map(Number);
        return new Date(year, month - 1, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    },

    get calendarDays() {
        const [year, month] = this.month.split('-').map(Number);
        const first = new Date(year, month - 1, 1);
        const gridStart = new Date(first);
        gridStart.setDate(first.getDate() - first.getDay());

        return Array.from({ length: 42 }, (_, index) => {
            const date = new Date(gridStart);
            date.setDate(gridStart.getDate() + index);
            const key = dateKey(date);
            const events = this.events.filter((event) => dateFromIso(event.starts_at) === key);

            return {
                date: key,
                label: date.getDate(),
                inMonth: date.getMonth() === month - 1,
                events,
                statuses: [...new Set(events.map((event) => event.status))].slice(0, 3),
            };
        });
    },

    get selectedEvents() {
        return this.events
            .filter((event) => dateFromIso(event.starts_at) === this.selectedDate)
            .sort((a, b) => a.starts_at.localeCompare(b.starts_at));
    },

    get selectedDateLabel() {
        return localDate(this.selectedDate).toLocaleDateString(undefined, {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
        });
    },

    previousMonth() {
        const [year, month] = this.month.split('-').map(Number);
        const date = new Date(year, month - 2, 1);
        this.month = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        this.selectedDate = `${this.month}-01`;
        this.load();
    },

    nextMonth() {
        const [year, month] = this.month.split('-').map(Number);
        const date = new Date(year, month, 1);
        this.month = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        this.selectedDate = `${this.month}-01`;
        this.load();
    },

    selectDate(date) {
        this.selectedDate = date;
    },

    moveDate(date, amount) {
        const nextDate = addCalendarDays(date, amount);
        const nextMonth = nextDate.slice(0, 7);

        this.selectedDate = nextDate;

        if (nextMonth !== this.month) {
            this.month = nextMonth;
            this.load();
        }

        window.requestAnimationFrame(() => document.querySelector(`[data-customer-calendar-day="${nextDate}"]`)?.focus());
    },

    load() {
        this.loading = true;
        this.error = '';

        return window.axios.get(this.feedUrl, { params: { month: this.month, status: this.statusFilter || null } })
            .then((response) => {
                this.events = response.data.events || [];
            }).catch(() => {
                this.error = 'Your appointment calendar could not be loaded. Try again.';
                this.events = [];
            }).finally(() => {
                this.loading = false;
            });
    },

    eventTime(event) {
        return new Date(event.starts_at).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    },
});

window.adminAppointmentForm = (config) => ({
    availableUrl: config.availableUrl,
    appointmentId: config.appointmentId || '',
    serviceId: config.initialServiceId || '',
    scheduledStart: config.initialScheduledStart || '',
    staffId: config.initialStaffId || '',
    persistedServiceId: config.persistedServiceId || '',
    persistedScheduledStart: config.persistedScheduledStart || '',
    persistedStaffId: config.persistedStaffId || '',
    staffNames: config.staffNames || {},
    addonOptions: config.addonOptions || [],
    addonCodes: config.initialAddonCodes || [],
    availableStaffIds: null,
    loadingTherapists: false,
    therapistError: '',

    init() {
        if (this.serviceId && this.scheduledStart) {
            this.refreshTherapists();
        }
    },

    applyCalendarSelection(selection) {
        if (!selection?.startsAt) {
            return;
        }

        this.scheduledStart = selection.startsAt;
        this.staffId = selection.staffId || '';
        this.refreshTherapists();
    },

    get assignedStaffName() {
        return this.staffNames[String(this.staffId)] || 'Choose therapist';
    },

    get scheduleSummary() {
        if (!this.scheduledStart) {
            return 'Choose a date and time';
        }

        const date = new Date(this.scheduledStart);
        const label = Number.isNaN(date.getTime())
            ? this.scheduledStart.replace('T', ' · ')
            : date.toLocaleString(undefined, {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            });

        return `${label} · ${this.assignedStaffName}${this.addonDurationMinutes ? ` · +${this.addonDurationMinutes} min add-ons` : ''}`;
    },

    get selectedPaidAddons() {
        return this.addonOptions.filter((addon) => this.addonCodes.includes(addon.code));
    },

    get paidAddonTotal() {
        return this.selectedPaidAddons.reduce((total, addon) => total + Number(addon.price || 0), 0);
    },

    get addonDurationMinutes() {
        return this.selectedPaidAddons.reduce((total, addon) => total + Number(addon.duration_minutes || 0), 0);
    },

    addonChanged() {
        this.refreshTherapists();
    },

    refreshTherapists() {
        this.therapistError = '';

        if (!this.serviceId || !this.scheduledStart) {
            this.availableStaffIds = null;
            return Promise.resolve();
        }

        this.loadingTherapists = true;

        return window.axios.get(this.availableUrl, {
            params: {
                service_id: this.serviceId,
                starts_at: this.scheduledStart,
                appointment_id: this.appointmentId || null,
                addon_codes: this.addonCodes,
            },
        }).then((response) => {
            this.availableStaffIds = (response.data.therapists || []).map((staff) => String(staff.id));

            if (this.staffId
                && !this.availableStaffIds.includes(String(this.staffId))
                && !this.canPreservePersistedStaff(this.staffId)) {
                this.staffId = '';
            }
        }).catch(() => {
            this.availableStaffIds = null;
            this.therapistError = 'Available therapists could not be checked. The schedule will still be validated when saved.';
        }).finally(() => {
            this.loadingTherapists = false;
        });
    },

    staffIsAvailable(id) {
        return this.availableStaffIds === null
            || this.availableStaffIds.includes(String(id))
            || this.canPreservePersistedStaff(id);
    },

    canPreservePersistedStaff(id) {
        return Boolean(this.appointmentId)
            && String(id) === String(this.persistedStaffId)
            && String(this.serviceId) === String(this.persistedServiceId)
            && String(this.scheduledStart) === String(this.persistedScheduledStart);
    },
});

window.customerCalendarBooking = (config) => ({
    availabilityUrl: config.availabilityUrl,
    services: config.services || [],
    staffOptions: config.staffOptions || [],
    vouchers: config.vouchers || [],
    addonOptions: config.addonOptions || [],
    serviceId: config.initialServiceId || '',
    staffId: config.initialStaffId || '',
    voucherId: config.initialVoucherId || '',
    addonCodes: config.initialAddonCodes || [],
    month: config.initialMonth,
    selectedDate: '',
    selectedSlot: config.initialSlot || '',
    preferredDate: '',
    slotsByDate: {},
    loading: false,
    error: '',
    weekDays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
    slotPreviewLimit: config.slotPreviewLimit || 2,

    init() {
        if (this.selectedSlot) {
            this.selectedDate = this.selectedSlot.slice(0, 10);
        }

        if (this.serviceId) {
            this.fetchAvailability();
        }
    },

    get monthLabel() {
        const [year, month] = this.month.split('-').map(Number);
        return new Date(year, month - 1, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    },

    get calendarDays() {
        const [year, month] = this.month.split('-').map(Number);
        const firstDay = new Date(year, month - 1, 1);
        const daysInMonth = new Date(year, month, 0).getDate();
        const days = [];

        for (let index = 0; index < firstDay.getDay(); index += 1) {
            days.push({ key: `blank-${index}`, label: '', date: null, available: false, previewSlots: [], moreSlots: 0 });
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const slots = this.slotsByDate[date] || [];

            days.push({
                key: date,
                label: day,
                date,
                available: slots.length > 0,
                previewSlots: slots.slice(0, this.slotPreviewLimit),
                moreSlots: Math.max(0, slots.length - this.slotPreviewLimit),
            });
        }

        return days;
    },

    get selectedDateSlots() {
        return this.selectedDate ? (this.slotsByDate[this.selectedDate] || []) : [];
    },

    get selectedService() {
        return this.services.find((service) => String(service.id) === String(this.serviceId)) || null;
    },

    get eligibleStaff() {
        return this.staffOptions.filter((staff) => staff.service_ids.map(String).includes(String(this.serviceId)));
    },

    get selectedStaff() {
        return this.staffOptions.find((staff) => String(staff.id) === String(this.staffId)) || null;
    },

    get selectedVoucher() {
        return this.vouchers.find((voucher) => String(voucher.id) === String(this.voucherId)) || null;
    },

    get selectedPaidAddons() {
        return this.addonOptions.filter((addon) => this.addonCodes.includes(addon.code));
    },

    get paidAddonTotal() {
        return this.selectedPaidAddons.reduce((total, addon) => total + Number(addon.price || 0), 0);
    },

    get addonDurationMinutes() {
        return this.selectedPaidAddons.reduce((total, addon) => total + Number(addon.duration_minutes || 0), 0);
    },

    addonChanged() {
        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    voucherChanged() {
        if (this.selectedVoucher?.addon_code) {
            this.addonCodes = this.addonCodes.filter((code) => code !== this.selectedVoucher.addon_code);
        }

        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    get selectedDateLabel() {
        if (!this.selectedDate) {
            return 'Choose a highlighted date.';
        }

        return new Date(`${this.selectedDate}T00:00:00`).toLocaleDateString(undefined, {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
        });
    },

    get selectedSlotLabel() {
        if (!this.selectedSlot) {
            return '';
        }

        return new Date(this.selectedSlot.replace(' ', 'T')).toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    },

    serviceChanged() {
        if (this.staffId && !this.eligibleStaff.some((staff) => String(staff.id) === String(this.staffId))) {
            this.staffId = '';
        }

        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    selectService(id) {
        this.serviceId = String(id);
        this.serviceChanged();
    },

    staffChanged() {
        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    previousMonth() {
        const [year, month] = this.month.split('-').map(Number);
        const date = new Date(year, month - 2, 1);
        this.month = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    nextMonth() {
        const [year, month] = this.month.split('-').map(Number);
        const date = new Date(year, month, 1);
        this.month = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        this.selectedDate = '';
        this.selectedSlot = '';
        this.fetchAvailability();
    },

    selectDate(date) {
        this.selectedDate = date;
        this.selectedSlot = '';
    },

    preselectDate(date) {
        if (!date) {
            return;
        }

        this.preferredDate = date;
        this.month = date.slice(0, 7);
        this.selectedDate = '';
        this.selectedSlot = '';

        if (this.serviceId) {
            this.fetchAvailability();
        }
    },

    moveAvailableDate(date, amount) {
        const nextDate = addCalendarDays(date, amount);
        const nextMonth = nextDate.slice(0, 7);
        const focusDate = () => {
            if (!(this.slotsByDate[nextDate] || []).length) {
                return;
            }

            this.selectDate(nextDate);
            window.requestAnimationFrame(() => document.querySelector(`[data-booking-calendar-day="${nextDate}"]`)?.focus());
        };

        if (nextMonth !== this.month) {
            this.month = nextMonth;
            this.selectedDate = '';
            this.selectedSlot = '';
            this.fetchAvailability().then(focusDate);

            return;
        }

        focusDate();
    },

    chooseSlot(slot) {
        this.selectedSlot = slot.starts_at;
    },

    moreSlotsLabel(day) {
        return day.moreSlots ? `+${day.moreSlots} more` : '';
    },

    fetchAvailability() {
        this.error = '';
        this.slotsByDate = {};

        if (!this.serviceId) {
            return Promise.resolve();
        }

        this.loading = true;

        return window.axios.get(this.availabilityUrl, {
            params: {
                service_id: this.serviceId,
                preferred_staff_profile_id: this.staffId || null,
                promotion_suggestion_id: this.voucherId || null,
                addon_codes: this.addonCodes,
                month: this.month,
            },
        }).then((response) => {
            this.slotsByDate = response.data.dates || {};

            if (this.preferredDate && (this.slotsByDate[this.preferredDate] || []).length) {
                this.selectedDate = this.preferredDate;
                this.preferredDate = '';
            }

            if (this.selectedDate && !this.slotsByDate[this.selectedDate]) {
                this.selectedDate = '';
                this.selectedSlot = '';
            }
        }).catch(() => {
            this.error = 'Availability could not be loaded. Try another service or month.';
        }).finally(() => {
            this.loading = false;
        });
    },
});

window.attendanceScanner = ({ scanUrl }) => ({
    working: false, error: '', notice: '', scanner: null,
    async start() {
        this.error = ''; this.notice = '';
        if (!navigator.mediaDevices?.getUserMedia) { this.error = 'This browser cannot access a camera. Ask an administrator to record a correction.'; return; }
        this.working = true;
        try {
            const permissionStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            permissionStream.getTracks().forEach((track) => track.stop());
            this.scanner?.clear().catch(() => {});
            this.scanner = new Html5Qrcode('attendance-camera');
            await this.scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 220, height: 220 } }, async (payload) => {
                await this.scanner.stop();
                const response = await window.axios.post(scanUrl, { payload });
                this.notice = response.data.message; this.working = false;
                window.setTimeout(() => window.location.reload(), 900);
            }, () => {});
        } catch (error) {
            this.working = false;
            this.error = error?.response?.data?.message || 'Camera access was not available. Check permission and try again.';
        }
    },
});

window.attendanceStation = (config) => ({
    qr: config.initialQr, error: '', countdown: '', timer: null,
    async init() { await this.render(); this.timer = window.setInterval(() => this.tick(), 1000); },
    async render() { const canvas = this.$root.querySelector('#attendance-qr'); if (canvas && this.qr?.payload) await QRCode.toCanvas(canvas, this.qr.payload, { width: 260, margin: 1 }); },
    tick() { const seconds = Math.max(0, Math.ceil((new Date(this.qr.expires_at).getTime() - Date.now()) / 1000)); this.countdown = `0:${String(seconds).padStart(2, '0')}`; if (seconds === 0) this.refreshQr(); },
    async refreshQr() { try { this.qr = (await window.axios.get(config.qrUrl)).data.qr; await this.render(); } catch { this.error = 'The live QR could not refresh.'; } },
});

window.organizationChartEditor = () => ({
    team: [],

    init() {
        try {
            const team = JSON.parse(this.$root.dataset.initialTeam || '[]');
            this.team = Array.isArray(team) ? team : [];
        } catch {
            this.team = [];
        }

        if (this.team.length === 0) {
            this.addRole();
        }
    },

    addRole() {
        if (this.team.length >= 8) {
            return;
        }

        this.team.push({ title: '', name: '', description: '', photoUrl: '' });
    },

    removeRole(index) {
        this.team.splice(index, 1);

        if (this.team.length === 0) {
            this.addRole();
        }
    },
});

window.orgPhotoUpload = (existingUrl) => ({
    previewUrl: existingUrl || '',
    hasExisting: !!existingUrl,
    removing: false,

    handleFileChange(event) {
        const file = event.target.files[0];
        if (file) {
            this.previewUrl = URL.createObjectURL(file);
            this.removing = false;
        }
    },
});

/* ── Org chart scroll animation (landing page) ── */
if (typeof IntersectionObserver !== 'undefined') {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!prefersReducedMotion) {
        const orgObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        orgObserver.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.15 },
        );

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-org-animate]').forEach((el) => {
                orgObserver.observe(el);
            });
        });
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-org-animate]').forEach((el) => {
                el.classList.add('is-visible');
            });
        });
    }
}

Alpine.start();

document.addEventListener('DOMContentLoaded', initializeBookingNotifications);

let loadingRevealTimer = null;
let loadingFallbackTimer = null;

const loadingElement = () => document.querySelector('[data-page-loading]');

const revealPageLoading = () => {
    const element = loadingElement();

    if (!element) {
        return;
    }

    element.classList.add('is-visible');
    element.setAttribute('aria-hidden', 'false');

    window.clearTimeout(loadingFallbackTimer);
    loadingFallbackTimer = window.setTimeout(() => {
        hidePageLoading();
    }, 12000);
};

const showPageLoading = (delay = 0) => {
    window.clearTimeout(loadingRevealTimer);

    if (delay > 0) {
        loadingRevealTimer = window.setTimeout(revealPageLoading, delay);
        return;
    }

    revealPageLoading();
};

const hidePageLoading = () => {
    const element = loadingElement();

    window.clearTimeout(loadingRevealTimer);
    window.clearTimeout(loadingFallbackTimer);

    loadingRevealTimer = null;
    loadingFallbackTimer = null;

    if (!element) {
        return;
    }

    element.classList.remove('is-visible');
    element.setAttribute('aria-hidden', 'true');
};

const isModifiedClick = (event) => {
    return event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
};

const linkUrl = (link) => {
    try {
        return new URL(link.href, window.location.href);
    } catch {
        return null;
    }
};

const formUrl = (form) => {
    try {
        return new URL(form.action || window.location.href, window.location.href);
    } catch {
        return null;
    }
};

const shouldSkipNavigationUrl = (url) => {
    if (!url || url.origin !== window.location.origin) {
        return true;
    }

    if (url.pathname.includes('/export') || url.pathname.includes('/availability')) {
        return true;
    }

    return url.pathname === window.location.pathname
        && url.search === window.location.search
        && Boolean(url.hash);
};

const isFastLink = (link) => {
    if (!(link instanceof HTMLAnchorElement) || link.hasAttribute('data-turbo')) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    if (link.hasAttribute('download') || link.hasAttribute('data-panel-link') || link.hasAttribute('data-no-turbo')) {
        return false;
    }

    if (link.closest('[data-turbo="false"]')) {
        return false;
    }

    const url = linkUrl(link);

    return !shouldSkipNavigationUrl(url) && url.href !== window.location.href;
};

const isFastGetForm = (form) => {
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-turbo')) {
        return false;
    }

    if (form.method.toLowerCase() !== 'get' || (form.target && form.target !== '_self')) {
        return false;
    }

    if (form.hasAttribute('data-no-turbo') || form.closest('[data-turbo="false"]')) {
        return false;
    }

    return !shouldSkipNavigationUrl(formUrl(form));
};

const prepareFastNavigation = (root = document) => {
    root.querySelectorAll('a[href]').forEach((link) => {
        if (isFastLink(link)) {
            link.setAttribute('data-turbo', 'true');
        }
    });

    root.querySelectorAll('form').forEach((form) => {
        if (isFastGetForm(form)) {
            form.setAttribute('data-turbo', 'true');
        } else if (!form.hasAttribute('data-turbo') && form.method.toLowerCase() !== 'get') {
            form.setAttribute('data-turbo', 'false');
        }
    });
};

const shouldHandleLink = (link, event) => {
    if (!link || event.defaultPrevented || isModifiedClick(event)) {
        return false;
    }

    if (link.getAttribute('data-turbo') === 'true') {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    if (link.hasAttribute('download') || link.hasAttribute('data-no-loading')) {
        return false;
    }

    const url = linkUrl(link);

    if (shouldSkipNavigationUrl(url)) {
        return false;
    }

    return url.href !== window.location.href;
};

const closestLink = (target, selector = 'a[href]') => {
    return target instanceof Element ? target.closest(selector) : null;
};

const panelElements = () => {
    const host = document.querySelector('[data-panel-host]');

    return {
        host,
        content: host?.querySelector('[data-panel-content]'),
        status: host?.querySelector('[data-panel-status]'),
        title: host?.querySelector('[data-panel-title]'),
        dialog: host?.querySelector('.casa-panel'),
    };
};

const panelCache = new Map();
const panelCacheTtl = 60000;
let panelTrigger = null;

const panelFocusTarget = (trigger) => {
    if (trigger instanceof HTMLElement && trigger.isConnected) {
        return trigger;
    }

    return document.querySelector('#main-content');
};

const closeModalWithin = (root) => {
    if (!root || !modalStore().active) {
        return;
    }

    const containsActiveModal = [...root.querySelectorAll('[data-modal-name]')]
        .some((element) => element.getAttribute('data-modal-name') === modalStore().active);

    if (containsActiveModal) {
        modalStore().close();
    }
};

const panelPathPrefixes = [
    '/admin/appointments/',
    '/admin/customers/',
    '/admin/staff/',
    '/admin/services/',
    '/admin/transactions/',
    '/admin/promotions/',
    '/admin/feedback/',
    '/staff/appointments/',
    '/staff/customers/',
    '/staff/transactions/',
    '/staff/feedback/',
    '/customer/appointments/',
    '/customer/feedback/',
];

const isPanelEligibleUrl = (url) => {
    if (!url || shouldSkipNavigationUrl(url)) {
        return false;
    }

    return panelPathPrefixes.some((prefix) => url.pathname.startsWith(prefix));
};

const isPanelLink = (link) => {
    const { host } = panelElements();

    if (!host || !link || link.hasAttribute('data-no-panel')) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    return link.hasAttribute('data-panel-link');
};

const setPanelLoading = (url) => {
    const { host, content, status, title, dialog } = panelElements();

    if (!host) {
        return;
    }

    host.classList.add('is-open', 'is-loading');
    host.setAttribute('aria-hidden', 'false');
    syncBodyScrollLock();

    if (title) {
        title.textContent = 'Loading';
    }

    if (status) {
        status.textContent = `Opening ${url.pathname}`;
    }

    if (content) {
        closeModalWithin(content);
        content.innerHTML = '';
    }

    window.setTimeout(() => dialog?.focus(), 20);
};

const closePanel = ({ restoreFocus = true } = {}) => {
    const { host, content } = panelElements();
    const trigger = panelTrigger;
    panelTrigger = null;

    if (!host) {
        return;
    }

    host.classList.remove('is-open', 'is-loading');
    host.setAttribute('aria-hidden', 'true');
    closeModalWithin(content);
    syncBodyScrollLock();

    if (content) {
        content.innerHTML = '';
    }

    if (restoreFocus) {
        window.requestAnimationFrame(() => panelFocusTarget(trigger)?.focus({ preventScroll: true }));
    }
};

const panelPageHtml = (doc) => {
    const header = doc.querySelector('[data-page-header]');
    const main = doc.querySelector('[data-page-content]');

    if (!main) {
        return null;
    }

    return `
        <div class="casa-panel-page">
            ${header ? `<header>${header.innerHTML}</header>` : ''}
            <main>${main.innerHTML}</main>
        </div>
    `;
};

const renderPanelHtml = (pageHtml) => {
    const { host, content } = panelElements();

    if (!host || !content) {
        return false;
    }

    closeModalWithin(content);
    content.innerHTML = pageHtml;
    host.classList.add('is-open');
    host.classList.remove('is-loading');
    host.setAttribute('aria-hidden', 'false');
    syncBodyScrollLock();

    const heading = content.querySelector('h1, h2');
    const { title } = panelElements();

    if (title) {
        title.textContent = heading?.textContent?.trim() || 'Workspace panel';
    }

    window.Alpine?.initTree(content);
    content.querySelector('input, select, textarea, button, a')?.focus({ preventScroll: true });
    return true;
};

const openPanel = async (url, trigger = document.activeElement) => {
    let { host, content } = panelElements();

    if (!host || !content || !isPanelEligibleUrl(url)) {
        panelTrigger = null;
        window.location.href = url.href;
        return;
    }

    if (!host.classList.contains('is-open') || !(panelTrigger instanceof HTMLElement && panelTrigger.isConnected)) {
        panelTrigger = trigger instanceof HTMLElement ? trigger : null;
    }

    const cached = panelCache.get(url.href);
    const cachedHtml = cached?.html;
    const cacheIsFresh = cached && Date.now() - cached.fetchedAt < panelCacheTtl;

    if (cachedHtml) {
        if (!renderPanelHtml(cachedHtml)) {
            window.location.href = url.href;
            return;
        }

        if (cacheIsFresh) {
            return;
        }
    } else {
        setPanelLoading(url);
    }

    try {
        const response = await window.fetch(url.href, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Casa-Panel': '1',
            },
        });

        if (!response.ok) {
            throw new Error(`Panel request failed with ${response.status}`);
        }

        const text = await response.text();
        const doc = new DOMParser().parseFromString(text, 'text/html');
        const pageHtml = panelPageHtml(doc);

        if (!pageHtml) {
            window.location.href = url.href;
            return;
        }

        panelCache.set(url.href, { html: pageHtml, fetchedAt: Date.now() });

        ({ host, content } = panelElements());

        if (!host || !content) {
            window.location.href = url.href;
            return;
        }

        renderPanelHtml(pageHtml);
    } catch {
        if (!cachedHtml) {
            window.location.href = url.href;
        }
    }
};

prepareFastNavigation();

document.addEventListener('turbo:before-render', (event) => {
    prepareFastNavigation(event.detail.newBody);
});

document.addEventListener('turbo:visit', () => {
    performance.mark('casa-web-navigation-start');
    showPageLoading(150);
});

document.addEventListener('turbo:load', () => {
    initializeBookingNotifications();
    prepareFastNavigation();
    hidePageLoading();
    if (performance.getEntriesByName('casa-web-navigation-start').length) {
        performance.measure('casa-web-navigation', { start: 'casa-web-navigation-start', end: performance.now() });
        performance.clearMarks('casa-web-navigation-start');
    }
});

document.addEventListener('turbo:before-cache', () => {
    closePanel({ restoreFocus: false });
    hidePageLoading();
});

document.addEventListener('turbo:fetch-request-error', hidePageLoading);

document.addEventListener('click', (event) => {
    const closeTrigger = event.target instanceof Element ? event.target.closest('[data-panel-close], [data-panel-backdrop]') : null;

    if (closeTrigger) {
        event.preventDefault();
        closePanel();
        return;
    }

    const link = closestLink(event.target);

    if (!link || event.defaultPrevented || isModifiedClick(event) || !isPanelLink(link)) {
        return;
    }

    const url = linkUrl(link);

    if (!url) {
        return;
    }

    event.preventDefault();
    openPanel(url, link);
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    if (modalStore().active) {
        event.preventDefault();
        event.stopPropagation();
        modalStore().close();
        return;
    }

    const { host } = panelElements();

    if (host?.classList.contains('is-open')) {
        closePanel();
    }
});

document.addEventListener('click', (event) => {
    const link = closestLink(event.target);

    if (shouldHandleLink(link, event)) {
        showPageLoading();
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading')) {
        return;
    }

    if (form.method.toLowerCase() !== 'get') {
        panelCache.clear();
    }

    if (form.getAttribute('data-turbo') === 'true') {
        return;
    }

    if (form.target && form.target !== '_self') {
        return;
    }

    if (shouldSkipNavigationUrl(formUrl(form))) {
        return;
    }

    showPageLoading();
});

window.addEventListener('pageshow', () => {
    prepareFastNavigation();
    hidePageLoading();
});

/*
 * Turbo is intentionally opt-in so state-changing forms and specialized panel
 * links retain their normal Laravel behavior. Eligible links and GET forms are
 * prepared above on the initial document and before every Turbo body swap.
 */
