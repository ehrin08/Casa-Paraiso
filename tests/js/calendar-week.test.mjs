import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { manilaDateKey, mondayOf, manilaTime, minutesInManila, positionWeekEvents, therapistColor } from '../../resources/js/calendar-week.js';

const day = '2026-10-01';
const event = (id, start, end, extra = {}) => ({
    id, kind: 'appointment', resource_id: id, starts_at: `${day}T${start}:00+08:00`,
    ends_at: `${day}T${end}:00+08:00`, ...extra,
});

test('Manila dates, Monday boundaries and labels are independent of browser timezone', () => {
    const previous = process.env.TZ;
    try {
        for (const timezone of ['UTC', 'America/Los_Angeles', 'Pacific/Auckland']) {
            process.env.TZ = timezone;
            assert.equal(manilaDateKey('2026-09-30T16:00:00Z'), '2026-10-01');
            assert.equal(mondayOf('2026-10-04'), '2026-09-28');
            assert.equal(mondayOf('2026-10-05'), '2026-10-05');
            assert.equal(manilaTime('2026-10-01T05:00:00Z'), '1:00 PM');
            assert.equal(minutesInManila('2026-10-01T16:00:00Z', day), 1440);
        }
    } finally {
        if (previous === undefined) delete process.env.TZ;
        else process.env.TZ = previous;
    }
});

test('simultaneous therapists get lanes; independent bookings regain full width', () => {
    const rows = positionWeekEvents([
        event('one', '13:00', '14:00'), event('two', '13:30', '14:30'),
        event('three', '14:00', '15:00'), event('four', '16:00', '17:00'),
    ], day);
    assert.deepEqual(rows.map((row) => [row.lane, row.laneCount]), [[0, 2], [1, 2], [0, 2], [0, 1]]);
});

test('half-hour visits and midnight endings retain actual durations and exclude backgrounds', () => {
    const rows = positionWeekEvents([
        event('short', '13:30', '14:00'),
        event('late', '23:00', '23:59', { ends_at: '2026-10-02T00:00:00+08:00' }),
        event('background', '13:00', '23:00', { kind: 'availability' }),
        event('other-day', '13:00', '14:00', { starts_at: '2026-10-02T13:00:00+08:00' }),
    ], day);
    assert.equal(rows.length, 2);
    assert.equal(rows[0].endMinutes - rows[0].startMinutes, 30);
    assert.equal(rows[1].endMinutes, 1440);
    assert.equal(therapistColor('17'), therapistColor(17));
});

function harness() {
    const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const calendar = source.slice(source.indexOf('const dateFromIso'), source.indexOf('window.weeklyRoster'));
    const requests = [];
    const window = { axios: { get: (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve, reject })) } };
    const context = vm.createContext({
        window, Intl, Date, manilaDateKey: (value) => manilaDateKey(value || '2026-10-04T23:00:00+08:00'),
        mondayOf, manilaTime, positionWeekEvents, therapistColor,
    });
    vm.runInContext(calendar, context);
    return { state: window.operationalCalendar({ presentation: 'week', initialWeek: '2026-09-28', feedUrl: '/feed', role: 'admin' }), requests };
}
const flush = () => new Promise(setImmediate);

test('changing calendar mode publishes the current mode for queue action forms', () => {
    const { state, requests } = harness();
    const notifications = [];
    state.$dispatch = (name, detail) => notifications.push({ name, mode: detail.mode });
    state.setMode('availability');
    assert.deepEqual(notifications, [{ name: 'appointment-calendar-mode-changed', mode: 'availability' }]);
    assert.equal(requests[0].options.params.mode, 'availability');
    state.setMode('availability');
    assert.equal(notifications.length, 1);
    state.setMode('bookings');
    assert.deepEqual(notifications[1], { name: 'appointment-calendar-mode-changed', mode: 'bookings' });
});

test('week navigation preserves Monday boundaries, filters, and seven-day feed ranges', () => {
    const { state, requests } = harness();
    state.staffFilter = '8';
    state.serviceFilter = '3';
    state.statusFilter = 'confirmed';
    state.nextWeek();
    assert.equal(state.weekStart, '2026-10-05');
    assert.equal(state.weekEnd, '2026-10-12');
    assert.equal(requests[0].options.params.staff_profile_id, '8');
    assert.equal(requests[0].options.params.service_id, '3');
    assert.equal(requests[0].options.params.status, 'confirmed');
    state.previousWeek();
    assert.equal(state.weekStart, '2026-09-28');
    state.today();
    assert.equal(state.weekStart, '2026-09-28');
    assert.equal(state.selectedDate, '2026-10-04');
    assert.equal(state.dayOptions.length, 7);
    assert.equal(state.dayOptions[0].weekday, 'Mon');
});

test('failed feed clears loading and retry recovers; stale requests cannot overwrite newer week', async () => {
    const { state, requests } = harness();
    const first = state.load();
    requests[0].reject(new Error('offline'));
    await first;
    assert.equal(state.loading, false);
    assert.match(state.error, /could not be loaded/);
    const retry = state.load();
    requests[1].resolve({ data: { resources: [{ id: '8' }], events: [event('new', '13:00', '14:00')] } });
    await retry;
    assert.equal(state.error, '');
    assert.equal(state.events[0].id, 'new');
    const stale = state.load();
    state.nextWeek();
    requests[3].resolve({ data: { resources: [], events: [event('latest', '14:00', '15:00')] } });
    await flush();
    requests[2].resolve({ data: { resources: [], events: [event('stale', '13:00', '14:00')] } });
    await stale;
    assert.equal(state.events[0].id, 'latest');
    assert.equal(state.loading, false);
});

test('availability mode keeps therapist coverage and mobile agenda excludes availability backgrounds', () => {
    const { state, requests } = harness();
    state.selectedDate = day;
    state.events = [event('visit', '13:00', '14:00'), event('coverage', '13:00', '23:00', { kind: 'availability' })];
    assert.equal(state.selectedAgendaEvents.length, 1);
    state.setMode('availability');
    assert.equal(requests[0].options.params.mode, 'availability');
    assert.equal(state.backgroundEvents('coverage').length, 1);
});
