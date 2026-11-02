import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/live-poller.js', import.meta.url), 'utf8')
    .replace('export const createPoller', 'globalThis.createPoller');
const flush = () => new Promise(setImmediate);

function harness({ canRun = () => true } = {}) {
    const listeners = new Map();
    const timers = new Map();
    const runs = [];
    let timerId = 0;
    const target = () => ({
        addEventListener: (name, callback) => listeners.set(name, [...(listeners.get(name) ?? []), callback]),
        removeEventListener: (name, callback) => listeners.set(name, (listeners.get(name) ?? []).filter((item) => item !== callback)),
    });
    const doc = { hidden: false, ...target() };
    const win = {
        ...target(),
        setTimeout(callback, delay) { timers.set(++timerId, { callback, delay }); return timerId; },
        clearTimeout: (id) => timers.delete(id),
    };
    const context = vm.createContext({ AbortController });
    vm.runInContext(source, context);
    const run = (signal) => new Promise((resolve, reject) => runs.push({ signal, resolve, reject }));
    const poller = context.createPoller({ interval: 15_000, run, canRun, win, doc });

    return {
        doc, timers, runs, poller,
        emit: (name) => (listeners.get(name) ?? []).forEach((callback) => callback()),
        tick() {
            const [id, timer] = timers.entries().next().value;
            assert.equal(timer.delay, 15_000);
            timers.delete(id);
            timer.callback();
        },
    };
}

test('runs immediately, then every interval without overlapping', async () => {
    const h = harness();
    h.poller.start();
    assert.equal(h.runs.length, 1);
    h.poller.refreshNow();
    assert.equal(h.runs.length, 1);
    h.runs[0].resolve();
    await flush();
    assert.equal(h.runs.length, 2, 'a refresh requested mid-flight runs once the request finishes');
    h.runs[1].resolve();
    await flush();
    h.tick();
    assert.equal(h.runs.length, 3);
});

test('hidden tabs and Turbo visits abort and pause polling', async () => {
    const h = harness();
    h.poller.start();
    h.doc.hidden = true;
    h.emit('visibilitychange');
    assert.equal(h.runs[0].signal.aborted, true);
    h.runs[0].resolve();
    await flush();
    assert.equal(h.timers.size, 0);
    h.doc.hidden = false;
    h.emit('visibilitychange');
    assert.equal(h.runs.length, 2);
    h.emit('turbo:before-visit');
    assert.equal(h.runs[1].signal.aborted, true);
    h.emit('online');
    assert.equal(h.runs.length, 2);
    h.poller.start();
    assert.equal(h.runs.length, 3);
});

test('expired sessions and false results stop polling; transient errors retry', async () => {
    const h = harness();
    h.poller.start();
    h.runs[0].reject({ response: { status: 503 } });
    await flush();
    assert.equal(h.timers.size, 1);
    h.tick();
    h.runs[1].reject({ response: { status: 419 } });
    await flush();
    assert.equal(h.timers.size, 0);
    h.emit('online');
    assert.equal(h.runs.length, 2);

    const other = harness();
    other.poller.start();
    other.runs[0].resolve(false);
    await flush();
    assert.equal(other.timers.size, 0);
});

test('canRun gates polling and destroy removes listeners', async () => {
    let allowed = false;
    const h = harness({ canRun: () => allowed });
    h.poller.start();
    assert.equal(h.runs.length, 0);
    allowed = true;
    h.emit('pageshow');
    assert.equal(h.runs.length, 1);
    h.poller.destroy();
    assert.equal(h.runs[0].signal.aborted, true);
    h.emit('pageshow');
    h.poller.refreshNow();
    assert.equal(h.runs.length, 1);
    assert.equal(h.timers.size, 0);
});
