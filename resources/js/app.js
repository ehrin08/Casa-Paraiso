import './bootstrap';
import { initializeBookingNotifications } from './booking-notifications';
import { initializeSidebar } from './sidebar';
import { manilaDateKey, mondayOf, manilaTime, positionWeekEvents, therapistColor } from './calendar-week';
import { createOperationsBooking, createCustomerBooking } from './appointment-booking';
import { isBookingPanelUrl, isAppointmentPreviewUrl, visibleDialogControls, trapBookingFocus } from './booking-dialog';
import { isCustomerModalUrl, restoreCustomerDetail, clearCustomerDetailRecovery } from './customer-dialog';
import { initializeSettingsChartDialog } from './settings-dialog';
import { initializeScheduleDialog } from './schedule-dialog';
import { createCustomerAppointmentCalendar } from './customer-appointment-calendar';

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

window.casaModal = ({ name, initialShow = false, focusable = false, returnFocus = null }) => ({
    name,
    initialShow,
    focusable,

    get show() {
        return Alpine.store(modalStoreName).active === this.name;
    },

    init() {
        if (this.initialShow) {
            modalStore().open(this.name, returnFocus ? document.querySelector(returnFocus) : null);
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
        const dialog = [...document.querySelectorAll('[role="dialog"][data-modal-name]')]
            .find((element) => element.dataset.modalName === this.name);

        return [...(dialog ?? this.$el).querySelectorAll(selector)].filter((element) => !element.hasAttribute('disabled'));
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
    weeklyPresentation: config.presentation === 'week',
    filtersOpen: false,
    requestVersion: 0,
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
    slotHeight: config.presentation === 'week' ? 44 : 32,
    openingMinutes: 13 * 60,
    closingMinutes: 24 * 60,

    init() {
        const today = this.weeklyPresentation ? manilaDateKey() : dateKey(new Date());
        const weekEnd = addCalendarDays(this.weekStart, 7);

        if (!config.initialDate && today >= this.weekStart && today < weekEnd) {
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
                isToday: date === (this.weeklyPresentation ? manilaDateKey() : dateKey(new Date())),
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
            .filter((event) => (this.weeklyPresentation ? manilaDateKey(event.starts_at) : dateFromIso(event.starts_at)) === this.selectedDate
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
        if (this.weeklyPresentation) {
            return `${manilaTime(event.starts_at)} – ${manilaTime(event.ends_at)}`;
        }
        const options = { hour: 'numeric', minute: '2-digit' };
        return `${new Date(event.starts_at).toLocaleTimeString(undefined, options)} – ${new Date(event.ends_at).toLocaleTimeString(undefined, options)}`;
    },

    setMode(mode) {
        if (this.mode === mode) {
            return;
        }

        this.mode = mode;
        this.$dispatch?.('appointment-calendar-mode-changed', { mode });
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
        if (this.weeklyPresentation) {
            this.weekStart = mondayOf(manilaDateKey());
            this.selectedDate = manilaDateKey();
            this.load();
            return;
        }
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
        const version = ++this.requestVersion;
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
            if (version !== this.requestVersion) return;
            this.resources = response.data.resources || [];
            this.events = response.data.events || [];
        }).catch(() => {
            if (version !== this.requestVersion) return;
            this.error = 'The schedule could not be loaded. Try refreshing this week.';
            this.resources = [];
            this.events = [];
        }).finally(() => {
            if (version === this.requestVersion) this.loading = false;
        });
    },

    weekEvents(day) {
        return positionWeekEvents(this.events, day, this.openingMinutes, this.closingMinutes);
    },

    get hasWeekBookings() {
        return this.dayOptions.some((day) => this.weekEvents(day.date).length);
    },

    weekEventClass(event) {
        return `casa-week-event-tone-${therapistColor(event.resource_id)}`;
    },

    weekEventTime(event) {
        return manilaTime(event.starts_at);
    },

    weekEventDescription(event) {
        return `${this.eventTimeRange(event)}, ${event.customer_name || event.title}, ${event.service_name || event.subtitle}, ${event.staff_name || this.resourceName(event.resource_id)}, ${this.statusLabel(event.status)}`;
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

window.customerAppointmentCalendar = createCustomerAppointmentCalendar;

window.adminAppointmentForm = createOperationsBooking;
window.customerCalendarBooking = createCustomerBooking;

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
    nextKey: 0,

    init() {
        try {
            const team = JSON.parse(this.$root.dataset.initialTeam || '[]');
            this.team = Array.isArray(team) ? team.map(role => ({ ...role, key: this.nextKey++, removePhoto: Boolean(role.removePhoto) })) : [];
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

        this.team.push({ key: this.nextKey++, title: '', name: '', description: '', photo: '', photoUrl: '', removePhoto: false });
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
document.addEventListener('DOMContentLoaded', () => initializeSettingsChartDialog());
document.addEventListener('turbo:load', () => initializeSettingsChartDialog());
document.addEventListener('DOMContentLoaded', () => initializeScheduleDialog());
document.addEventListener('turbo:load', () => initializeScheduleDialog());

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
let panelRequest = 0;
let panelAbort = null;
const panelInertState = new Map();

const setPanelPresentation = (booking, preview = false, customer = false) => {
    const { host } = panelElements();
    host?.classList.toggle('is-booking', booking);
    host?.classList.toggle('is-appointment-preview', preview);
    host?.classList.toggle('is-customer-detail', customer);
    host?.classList.toggle('is-centered', booking || preview || customer);
    if (!booking && !preview && !customer) {
        panelInertState.forEach((wasInert, element) => { element.inert = wasInert; });
        panelInertState.clear();
        return;
    }
    let current = host;
    while (current?.parentElement) {
        [...current.parentElement.children].filter((element) => element !== current && !element.matches('script, style, link')).forEach((element) => {
            if (!panelInertState.has(element)) panelInertState.set(element, element.inert);
            element.inert = true;
        });
        current = current.parentElement;
        if (current === document.body) break;
    }
};

const clearPanelContent = (content) => {
    if (!content) return;
    closeModalWithin(content);
    window.Alpine?.destroyTree(content);
    content.innerHTML = '';
};

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
    '/reception/appointments/',
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
    setPanelPresentation(isBookingPanelUrl(url), isAppointmentPreviewUrl(url), isCustomerModalUrl(url));
    host.setAttribute('aria-hidden', 'false');
    syncBodyScrollLock();

    if (title) {
        title.textContent = isBookingPanelUrl(url) ? (url.pathname.endsWith('/edit') ? 'Edit appointment' : 'Create appointment') : (isAppointmentPreviewUrl(url) ? 'Appointment details' : (isCustomerModalUrl(url) ? 'Customer details' : 'Loading'));
    }

    if (status) {
        status.textContent = isBookingPanelUrl(url) ? 'Loading booking form…' : (isAppointmentPreviewUrl(url) ? 'Loading appointment details…' : (isCustomerModalUrl(url) ? 'Loading customer details…' : `Opening ${url.pathname}`));
    }

    if (content) {
        clearPanelContent(content);
    }

    dialog?.focus();
};

const closePanel = ({ restoreFocus = true } = {}) => {
    const { host, content } = panelElements();
    const trigger = panelTrigger;
    panelTrigger = null;
    panelRequest++;
    panelAbort?.abort();

    if (!host) {
        return;
    }

    host.classList.remove('is-open', 'is-loading');
    host.setAttribute('aria-hidden', 'true');
    setPanelPresentation(false);
    syncBodyScrollLock();

    if (content) {
        clearPanelContent(content);
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

    if (main.querySelector('[data-booking-form]')) {
        return `<div class="casa-panel-page" data-booking-page><main>${main.innerHTML}</main></div>`;
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

    clearPanelContent(content);
    content.innerHTML = pageHtml;
    setPanelPresentation(Boolean(content.querySelector('[data-booking-page]')), Boolean(content.querySelector('[data-appointment-preview]')), Boolean(content.querySelector('[data-customer-detail]')));
    host.classList.add('is-open');
    host.classList.remove('is-loading');
    host.setAttribute('aria-hidden', 'false');
    syncBodyScrollLock();

    const heading = content.querySelector('h1, h2');
    const { title } = panelElements();

    if (title) {
        title.textContent = content.querySelector('[data-booking-title]')?.dataset.bookingTitle || heading?.textContent?.trim() || 'Workspace panel';
    }

    window.Alpine?.initTree(content);
    const { dialog } = panelElements();
    window.requestAnimationFrame(() => {
        if (!host.classList.contains('is-open')) return;
        const focusRoot = content.querySelector('[data-customer-detail]') ? dialog : content;
        (content.querySelector('[data-customer-note-invalid]') || visibleDialogControls(focusRoot)[0] || dialog)?.focus({ preventScroll: true });
    });
    return true;
};

const openPanel = async (url, trigger = document.activeElement) => {
    let { host, content } = panelElements();
    const request = ++panelRequest;
    panelAbort?.abort();
    panelAbort = new AbortController();

    if (!host || !content || !isPanelEligibleUrl(url)) {
        panelTrigger = null;
        window.location.href = url.href;
        return;
    }

    if (!host.classList.contains('is-open') || !(panelTrigger instanceof HTMLElement && panelTrigger.isConnected)) {
        panelTrigger = trigger instanceof HTMLElement ? trigger : null;
    }

    const freshDetails = isAppointmentPreviewUrl(url) || isCustomerModalUrl(url);
    const cached = freshDetails ? null : panelCache.get(url.href);
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
            signal: panelAbort.signal,
            cache: freshDetails ? 'no-store' : 'default',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Casa-Panel': '1',
            },
        });

        if (!response.ok) {
            throw new Error(`Panel request failed with ${response.status}`);
        }

        const text = await response.text();
        if (request !== panelRequest || !host.classList.contains('is-open')) return;
        const doc = new DOMParser().parseFromString(text, 'text/html');
        const pageHtml = panelPageHtml(doc);

        if (!pageHtml) {
            window.location.href = url.href;
            return;
        }

        if (!freshDetails) {
            panelCache.set(url.href, { html: pageHtml, fetchedAt: Date.now() });
        }

        ({ host, content } = panelElements());

        if (!host || !content) {
            window.location.href = url.href;
            return;
        }

        renderPanelHtml(pageHtml);
    } catch {
        if (request !== panelRequest || panelAbort.signal.aborted) return;
        if (!cachedHtml) {
            window.location.href = url.href;
        }
    }
};

window.addEventListener('close-workspace-panel', () => closePanel());

const restoreCustomerModal = () => restoreCustomerDetail(document, renderPanelHtml, (trigger) => { panelTrigger = trigger; });
document.addEventListener('DOMContentLoaded', restoreCustomerModal);
if (document.readyState !== 'loading') restoreCustomerModal();

prepareFastNavigation();

document.addEventListener('turbo:before-render', (event) => {
    prepareFastNavigation(event.detail.newBody);
});

document.addEventListener('turbo:visit', () => {
    performance.mark('casa-web-navigation-start');
    showPageLoading(150);
});

document.addEventListener('turbo:load', () => {
    restoreCustomerModal();
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
    clearCustomerDetailRecovery(document);
    clearCompletionConfirmation();
    hidePageLoading();
});

// Completion confirmations belong only to the successful redirect, never a restored page.
const clearCompletionConfirmation = () => {
    const confirmations = [...document.querySelectorAll('[data-transient-modal]')];
    if (confirmations.some((element) => element.dataset.modalName === modalStore().active)) {
        modalStore().close();
    }
    confirmations.forEach((element) => element.remove());
};

window.addEventListener('pagehide', clearCompletionConfirmation);

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
    const { host, dialog } = panelElements();
    if (event.key === 'Tab' && host?.classList.contains('is-open') && host.classList.contains('is-centered') && !modalStore().active) {
        trapBookingFocus(event, dialog);
        return;
    }
    if (event.key !== 'Escape') {
        return;
    }

    if (modalStore().active) {
        event.preventDefault();
        event.stopPropagation();
        modalStore().close();
        return;
    }

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
