import assert from 'node:assert/strict';
import test from 'node:test';
import { createCustomerAppointmentCalendar } from '../../resources/js/customer-appointment-calendar.js';

function harness(month = '2026-10') {
    const requests = [];
    const state = createCustomerAppointmentCalendar(
        { initialMonth: month, feedUrl: '/calendar', historyUrl: '/customer/appointments/history' },
        (url, options) => new Promise((resolve, reject) => requests.push({ url, ...options, resolve, reject })),
        () => new Date('2026-10-11T00:00:00+08:00'),
    );
    return { state, requests };
}
const event = (id, starts_at, status = 'confirmed') => ({ id, starts_at, status, service_name: 'Island massage', detail_url: '/customer/appointments/' + id });

test('Monday weeks have only the needed rows including leap February', () => {
    for (const [month, first, last, length] of [
        ['2026-10', '2026-09-28', '2026-11-01', 35],
        ['2027-02', '2027-02-01', '2027-02-28', 28],
        ['2028-02', '2028-01-31', '2028-03-05', 35],
        ['2026-03', '2026-02-23', '2026-04-05', 42],
    ]) {
        const { state } = harness(month);
        assert.equal(state.calendarDays[0].date, first);
        assert.equal(state.calendarDays.at(-1).date, last);
        assert.equal(state.calendarDays.length, length);
        assert.equal(state.calendarRows.length, length / 7);
        assert.deepEqual(state.weekDays, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
    }
});

test('Manila today, event grouping and labels do not follow the browser timezone', async () => {
    const previous = process.env.TZ;
    try {
        for (const timezone of ['UTC', 'America/Los_Angeles', 'Pacific/Auckland']) {
            process.env.TZ = timezone;
            const { state, requests } = harness();
            const loading = state.init();
            assert.equal(state.selectedDate, '2026-10-11');
            requests[0].resolve({ data: { events: [event(1, '2026-10-10T16:30:00Z', 'no_show')] } });
            await loading;
            const day = state.calendarDays.find((day) => day.date === '2026-10-11');
            assert.equal(day.total, 1);
            assert.equal(day.isToday, true);
            assert.equal(state.eventTime(day.events[0]), '12:30 AM');
            assert.equal(state.monthLabel, 'October 2026');
            assert.match(state.eventDescription(day.events[0]), /No-show, 12:30 AM/);
        }
    } finally {
        if (previous === undefined) delete process.env.TZ;
        else process.env.TZ = previous;
    }
});

test('busy days expose the earliest two badges and a scoped history link', () => {
    const { state } = harness();
    state.events = [event(3, '2026-10-16T15:00:00+08:00'), event(1, '2026-10-16T05:00:00Z'), event(2, '2026-10-16T14:00:00+08:00')];
    const day = state.calendarDays.find((day) => day.date === '2026-10-16');
    assert.deepEqual(day.events.map((event) => event.id), [1, 2]);
    assert.equal(day.total, 3);
    assert.equal(day.remaining, 1);
    assert.equal(state.eventTime(day.events[0]), '1 PM');
    state.statusFilter = 'confirmed';
    const url = new URL(state.historyUrl(day.date), 'http://localhost');
    assert.equal(url.searchParams.get('date_from'), day.date);
    assert.equal(url.searchParams.get('date_to'), day.date);
    assert.equal(url.searchParams.get('status'), 'confirmed');
    assert.equal(state.hasMonthEvents, true);
});

test('month and keyboard date movement cross years and leap days', async () => {
    const { state, requests } = harness('2026-12');
    const loading = state.nextMonth();
    assert.equal(state.month, '2027-01');
    assert.equal(state.selectedDate, '2027-01-01');
    requests[0].resolve({ data: { events: [] } });
    await loading;
    const previous = state.previousMonth();
    assert.equal(state.month, '2026-12');
    requests[1].resolve({ data: { events: [] } });
    await previous;

    const leap = harness('2028-02');
    let focused = '';
    leap.state.$root = { querySelector: (selector) => ({ focus: () => { focused = selector; } }) };
    leap.state.$nextTick = (callback) => callback();
    leap.state.moveDate('2028-02-28', 1);
    assert.equal(leap.state.selectedDate, '2028-02-29');
    assert.match(focused, /2028-02-29/);
    leap.state.moveDate('2028-02-29', 1);
    assert.equal(leap.state.month, '2028-03');
    assert.equal(leap.requests[0].params.month, '2028-03');
    leap.requests[0].resolve({ data: { events: [] } });
});

test('outdated successes and failures cannot replace the latest month or loading state', async () => {
    const { state, requests } = harness();
    const first = state.init();
    const second = state.nextMonth();
    assert.equal(requests[0].signal.aborted, true);
    requests[0].resolve({ data: { events: [event(1, '2026-10-16T13:00:00+08:00')] } });
    await first;
    assert.deepEqual(state.events, []);
    assert.equal(state.loading, true);
    requests[1].resolve({ data: { events: [event(2, '2026-11-16T13:00:00+08:00')] } });
    await second;
    const third = state.load();
    state.statusFilter = 'cancelled';
    const fourth = state.load();
    requests[3].resolve({ data: { events: [event(3, '2026-11-17T13:00:00+08:00', 'cancelled')] } });
    await fourth;
    requests[2].reject(new Error('old failure'));
    await third;
    assert.equal(state.events[0].id, 3);
    assert.equal(state.error, '');
    assert.equal(state.loading, false);
});

test('failed loads can retry and destroyed calendars ignore pending requests', async () => {
    const { state, requests } = harness();
    const first = state.init();
    requests[0].reject(new Error('offline'));
    await first;
    assert.match(state.error, /could not be loaded/);
    const retry = state.load();
    requests[1].resolve({ data: { events: [] } });
    await retry;
    assert.equal(state.error, '');
    assert.equal(state.loading, false);
    assert.equal(state.hasMonthEvents, false);
    const last = state.load();
    state.destroy();
    assert.equal(requests[2].signal.aborted, true);
    requests[2].resolve({ data: { events: [event(5, '2026-10-16T13:00:00+08:00')] } });
    await last;
    assert.deepEqual(state.events, []);
});
