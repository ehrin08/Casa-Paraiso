import { manilaDateKey, mondayOf, manilaTime } from './calendar-week.js';

const dayMilliseconds = 86400000;
const addDays = (key, amount) => new Date(Date.parse(`${key}T00:00:00Z`) + amount * dayMilliseconds).toISOString().slice(0, 10);
const dateLabel = (key, options) => new Intl.DateTimeFormat('en-US', { timeZone: 'UTC', ...options }).format(new Date(`${key}T00:00:00Z`));

export function createCustomerAppointmentCalendar(config, get = (url, options) => window.axios.get(url, options), now = () => new Date()) {
    let requestVersion = 0;
    let controller = null;

    return {
        month: config.initialMonth,
        selectedDate: '',
        statusFilter: '',
        events: [],
        loading: false,
        error: '',
        weekDays: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],

        init() {
            const today = manilaDateKey(now());
            this.selectedDate = today.slice(0, 7) === this.month ? today : `${this.month}-01`;
            return this.load();
        },

        destroy() {
            requestVersion++;
            controller?.abort();
        },

        get monthLabel() {
            return dateLabel(`${this.month}-01`, { month: 'long', year: 'numeric' });
        },

        get calendarDays() {
            const first = `${this.month}-01`;
            const [year, month] = this.month.split('-').map(Number);
            const last = new Date(Date.UTC(year, month, 0)).toISOString().slice(0, 10);
            const start = mondayOf(first);
            const end = addDays(mondayOf(last), 6);
            const length = (Date.parse(end) - Date.parse(start)) / dayMilliseconds + 1;
            const today = manilaDateKey(now());
            const byDay = new Map();

            [...this.events]
                .sort((a, b) => Date.parse(a.starts_at) - Date.parse(b.starts_at) || String(a.id).localeCompare(String(b.id)))
                .forEach((event) => {
                    const key = manilaDateKey(event.starts_at);
                    if (!byDay.has(key)) byDay.set(key, []);
                    byDay.get(key).push(event);
                });

            return Array.from({ length }, (_, index) => {
                const date = addDays(start, index);
                const events = byDay.get(date) || [];
                return {
                    date,
                    label: date.slice(0, 7) === this.month ? Number(date.slice(8)) : dateLabel(date, { month: 'short', day: 'numeric' }),
                    inMonth: date.slice(0, 7) === this.month,
                    isToday: date === today,
                    events: events.slice(0, 2),
                    total: events.length,
                    remaining: Math.max(0, events.length - 2),
                };
            });
        },

        get calendarRows() {
            const days = this.calendarDays;
            return Array.from({ length: days.length / 7 }, (_, index) => days.slice(index * 7, index * 7 + 7));
        },

        get hasMonthEvents() {
            return this.events.some((event) => manilaDateKey(event.starts_at).slice(0, 7) === this.month);
        },

        previousMonth() {
            return this.changeMonth(-1);
        },

        nextMonth() {
            return this.changeMonth(1);
        },

        changeMonth(amount) {
            const [year, month] = this.month.split('-').map(Number);
            this.month = new Date(Date.UTC(year, month - 1 + amount, 1)).toISOString().slice(0, 7);
            this.selectedDate = `${this.month}-01`;
            return this.load();
        },

        selectDate(date) {
            this.selectedDate = date;
            if (date.slice(0, 7) !== this.month) {
                this.month = date.slice(0, 7);
                return this.load();
            }
        },

        moveDate(date, amount) {
            const nextDate = addDays(date, amount);
            this.selectDate(nextDate);
            this.$nextTick?.(() => this.$root.querySelector(`[data-customer-calendar-day="${nextDate}"]`)?.focus());
        },

        dayDescription(day) {
            return `${dateLabel(day.date, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}, ${day.total} appointment${day.total === 1 ? '' : 's'}`;
        },

        statusLabel(status) {
            return { confirmed: 'Confirmed', completed: 'Completed', cancelled: 'Cancelled', no_show: 'No-show' }[status] || status;
        },

        eventTime(event) {
            return manilaTime(event.starts_at).replace(':00', '');
        },

        eventDescription(event) {
            return `${event.service_name || event.title}, ${this.statusLabel(event.status)}, ${manilaTime(event.starts_at)}`;
        },

        historyUrl(date) {
            const params = new URLSearchParams({ date_from: date, date_to: date });
            if (this.statusFilter) params.set('status', this.statusFilter);
            return `${config.historyUrl}?${params}`;
        },

        async load() {
            const version = ++requestVersion;
            controller?.abort();
            controller = new AbortController();
            this.loading = true;
            this.error = '';
            this.events = [];

            try {
                const response = await get(config.feedUrl, {
                    params: { month: this.month, status: this.statusFilter || null },
                    signal: controller.signal,
                });
                if (version === requestVersion) this.events = response.data.events || [];
            } catch {
                if (version === requestVersion) this.error = 'Your appointment calendar could not be loaded. Try again.';
            } finally {
                if (version === requestVersion) this.loading = false;
            }
        },
    };
}
