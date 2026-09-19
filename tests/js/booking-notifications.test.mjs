import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/booking-notifications.js', import.meta.url), 'utf8')
    .replace('export const initializeBookingNotifications', 'globalThis.initializeBookingNotifications');
const flush = () => new Promise(setImmediate);

function harness({ schedule = false, root = true } = {}) {
    const listeners = new Map();
    const timers = new Map();
    const requests = [];
    const value = { textContent: '4' };
    const badge = { querySelector: () => value, classList: { toggle() {} }, setAttribute() {} };
    const state = { schedule, root: root ? { dataset: { bookingNotificationsCountUrl: '/count', bookingNotificationsReadUrl: '/read' } } : null };
    const addEventListener = (name, callback) => listeners.set(name, callback);
    const document = {
        hidden: false,
        addEventListener,
        querySelector(selector) {
            if (selector === '[data-booking-notifications]') return state.root;
            if (selector.includes('aria-current')) return state.schedule ? {} : null;
            return { getAttribute: () => 'csrf' };
        },
        querySelectorAll: () => [badge],
    };
    const request = (method, url, options) => new Promise((resolve, reject) => {
        requests.push({ method, url, options, resolve: (count) => resolve({ data: { count } }), reject });
    });
    let timerId = 0;
    const window = {
        addEventListener,
        location: { href: '/dashboard' },
        axios: {
            get: (url, options) => request('GET', url, options),
            patch: (url, data, options) => request('PATCH', url, options),
        },
        setTimeout(callback, delay) { timers.set(++timerId, { callback, delay }); return timerId; },
        clearTimeout: (id) => timers.delete(id),
    };
    const context = vm.createContext({ document, window, AbortController });
    vm.runInContext(source, context);
    return {
        document, window, state, timers, requests, value,
        start: context.initializeBookingNotifications,
        emit: (name) => listeners.get(name)?.(),
        tick() {
            const [id, timer] = timers.entries().next().value;
            assert.equal(timer.delay, 30_000);
            timers.delete(id);
            timer.callback();
        },
    };
}

test('polls without overlapping requests and displays newly arrived bookings', async () => {
    const h = harness();
    h.start();
    assert.equal(h.requests.length, 1);
    assert.equal(h.timers.size, 0);
    h.requests[0].resolve(2);
    await flush();
    assert.equal(h.value.textContent, '2');
    h.tick();
    assert.equal(h.requests.length, 2);
    assert.equal(h.timers.size, 0);
    h.requests[1].resolve(3);
    await flush();
    assert.equal(h.value.textContent, '3');
    assert.equal(h.timers.size, 1);
});

test('hidden tabs abort requests, ignore stale responses, and refresh on return', async () => {
    const h = harness();
    h.start();
    h.document.hidden = true;
    h.emit('visibilitychange');
    assert.equal(h.requests[0].options.signal.aborted, true);
    h.requests[0].resolve(99);
    await flush();
    assert.equal(h.value.textContent, '4');
    assert.equal(h.timers.size, 0);
    h.document.hidden = false;
    h.emit('visibilitychange');
    assert.equal(h.requests.length, 2);
});

test('reinitialization cancels previous polling and leaves only one timer', async () => {
    const h = harness();
    h.start();
    h.start();
    h.requests[1].resolve(1);
    h.requests[0].resolve(9);
    await flush();
    assert.equal(h.value.textContent, '1');
    assert.equal(h.timers.size, 1);
});

test('schedule entry acknowledges once; later polls preserve newly arrived unread items', async () => {
    const h = harness({ schedule: true });
    h.start();
    assert.equal(h.requests[0].method, 'PATCH');
    assert.equal(h.requests[0].options.headers['X-CSRF-TOKEN'], 'csrf');
    h.requests[0].resolve(0);
    await flush();
    h.tick();
    assert.equal(h.requests[1].method, 'GET');
    h.requests[1].resolve(1);
    await flush();
    assert.equal(h.value.textContent, '1');
    h.emit('turbo:before-visit');
    assert.equal(h.timers.size, 0);
    h.emit('online');
    assert.equal(h.requests.length, 2);
    h.start();
    assert.equal(h.requests[2].method, 'PATCH');
});

test('pages without notification controls never poll', () => {
    const h = harness({ root: false });
    h.start();
    h.emit('online');
    assert.equal(h.requests.length, 0);
    assert.equal(h.timers.size, 0);
});

test('transient errors retain badge and retry; expired sessions stop polling', async () => {
    const h = harness();
    h.start();
    h.requests[0].reject({ response: { status: 503 } });
    await flush();
    assert.equal(h.value.textContent, '4');
    h.tick();
    h.requests[1].reject({ response: { status: 401 } });
    await flush();
    assert.equal(h.timers.size, 0);
    assert.equal(h.value.textContent, '4');
});

test('invalid count responses never clear the last known badge', async () => {
    const h = harness();
    h.start();
    h.requests[0].resolve(undefined);
    await flush();
    assert.equal(h.value.textContent, '4');
    assert.equal(h.timers.size, 0);
});
