import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const pollerSource = readFileSync(new URL('../../resources/js/live-poller.js', import.meta.url), 'utf8')
    .replace('export const createPoller', 'const createPoller');
const source = pollerSource + readFileSync(new URL('../../resources/js/live-regions.js', import.meta.url), 'utf8')
    .replace("import { createPoller } from './live-poller';", '')
    .replace('export const LIVE_POLL_INTERVAL_MS', 'const LIVE_POLL_INTERVAL_MS')
    .replace('export const createLiveState', 'globalThis.createLiveState');
const flush = () => new Promise(setImmediate);

function region(name, scope, html) {
    return {
        dataset: { liveRegion: name, ...(scope ? { liveScope: scope } : {}) },
        innerHTML: html,
        isConnected: true,
        dirty: false,
        events: [],
        contains(node) { return node?.owner === this; },
        querySelector(selector) { return selector === 'form[data-live-dirty]' && this.dirty ? {} : null; },
        dispatchEvent(event) { this.events.push(event.type); },
    };
}

function harness(regions) {
    const listeners = new Map();
    const timers = new Map();
    const states = [];
    const pages = [];
    let timerId = 0;
    let modal = null;
    let panelOpen = false;
    const addEventListener = (name, callback) => listeners.set(name, [...(listeners.get(name) ?? []), callback]);
    const doc = {
        hidden: false,
        body: { owner: null },
        activeElement: null,
        addEventListener,
        querySelector(selector) {
            if (selector.startsWith('meta')) return { getAttribute: () => '/live/state' };
            if (selector === '[data-panel-host].is-open') return panelOpen ? {} : null;
            return null;
        },
        querySelectorAll: () => regions,
    };
    doc.activeElement = doc.body;
    const win = {
        addEventListener,
        location: { href: '/admin/appointments?mode=bookings' },
        Alpine: { store: () => ({ active: modal }) },
        CustomEvent: class { constructor(type) { this.type = type; } },
        setTimeout(callback, delay) { timers.set(++timerId, { callback, delay }); return timerId; },
        clearTimeout: (id) => timers.delete(id),
    };
    const get = () => new Promise((resolve) => states.push((scopes) => resolve({ data: { scopes } })));
    const fetchPage = (url) => new Promise((resolve) => pages.push({ url, resolve }));
    const parse = (html) => ({ querySelector: (selector) => {
        const name = selector.match(/"(.+)"/)[1];
        const match = html.match(new RegExp(`<${name}>(.*?)</${name}>`));
        return match ? { innerHTML: match[1] } : null;
    } });
    const context = vm.createContext({ AbortController });
    vm.runInContext(source, context);
    const live = context.createLiveState({ win, doc, get, fetchPage, parse });

    return {
        live, doc, timers, states, pages,
        setModal: (value) => { modal = value; },
        setPanel: (value) => { panelOpen = value; },
        async poll(scopes) {
            states.at(-1)(scopes);
            await flush();
        },
        tick() {
            const [id, timer] = timers.entries().next().value;
            assert.equal(timer.delay, 15_000);
            timers.delete(id);
            timer.callback();
        },
    };
}

test('the first poll sets a baseline and later changes swap only matching regions', async () => {
    const queue = region('service-queue', 'appointments', 'old queue');
    const inbox = region('notifications-list', 'notifications', 'old inbox');
    const h = harness([queue, inbox]);
    const heard = [];
    h.live.subscribe((changed) => heard.push(changed));
    h.live.start();
    await h.poll({ appointments: 'a1', notifications: 'n1' });
    assert.equal(h.pages.length, 0);
    assert.deepEqual(heard.map((item) => [...item]), [[]]);

    h.tick();
    await h.poll({ appointments: 'a2', notifications: 'n1' });
    assert.deepEqual([...heard[1]], ['appointments']);
    assert.equal(h.pages[0].url, '/admin/appointments?mode=bookings');
    h.pages[0].resolve('<service-queue>new queue</service-queue><notifications-list>new inbox</notifications-list>');
    await flush();
    assert.equal(queue.innerHTML, 'new queue');
    assert.equal(inbox.innerHTML, 'old inbox');
    assert.deepEqual(queue.events, ['casa:live-region-updated']);
    assert.equal(h.timers.size, 1);
});

test('busy regions wait and are swapped on the next poll', async () => {
    const queue = region('service-queue', 'appointments', 'old');
    const h = harness([queue]);
    h.live.start();
    await h.poll({ appointments: 'a1' });

    h.doc.activeElement = { owner: queue };
    h.tick();
    await h.poll({ appointments: 'a2' });
    assert.equal(h.pages.length, 0, 'focus inside the region holds the swap');

    h.doc.activeElement = h.doc.body;
    queue.dirty = true;
    h.tick();
    await h.poll({ appointments: 'a2' });
    assert.equal(h.pages.length, 0, 'an edited form holds the swap');

    queue.dirty = false;
    h.setModal('admin-appointment-completion-4');
    h.tick();
    await h.poll({ appointments: 'a2' });
    assert.equal(h.pages.length, 0, 'an open modal holds the swap');

    h.setModal(null);
    h.setPanel(true);
    h.tick();
    await h.poll({ appointments: 'a2' });
    assert.equal(h.pages.length, 0, 'an open panel holds the swap');

    h.setPanel(false);
    h.tick();
    await h.poll({ appointments: 'a2' });
    assert.equal(h.pages.length, 1, 'the pending swap runs once the region is free');
    h.pages[0].resolve('<service-queue>new</service-queue>');
    await flush();
    assert.equal(queue.innerHTML, 'new');
});

test('expired sessions stop polling and pages without live content never poll', async () => {
    const h = harness([region('service-queue', 'appointments', 'old')]);
    h.live.start();
    h.states.at(-1)(undefined);
    await flush();
    assert.equal(h.timers.size, 0);

    const idle = harness([region('attendance-status', null, 'card')]);
    idle.live.start();
    assert.equal(idle.states.length, 0);
});

test('refreshRegions swaps a named region on demand', async () => {
    const card = region('attendance-status', null, 'Not clocked in');
    const h = harness([card]);
    const done = h.live.refreshRegions(['attendance-status']);
    h.pages[0].resolve('<attendance-status>Clocked in</attendance-status>');
    await done;
    assert.equal(card.innerHTML, 'Clocked in');
});
