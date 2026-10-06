import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { isAppointmentPreviewUrl, isBookingPanelUrl, visibleDialogControls } from '../../resources/js/booking-dialog.js';

// Run the actual workspace loader with a small DOM fixture and controlled requests.
const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const loaderSource = source.slice(source.indexOf('const panelElements ='), source.indexOf("window.addEventListener('close-workspace-panel'"));
const url = new URL('http://localhost/admin/appointments/12?preview=1');
const response = (label) => ({ ok: true, text: async () => `<div data-appointment-preview>${label}</div>` });

function fixture(fetch) {
    const document = { activeElement: null };
    class Element {
        isConnected = true;
        inert = false;
        tabIndex = 0;
        matches() { return false; }
        closest() { return null; }
        getClientRects() { return [1]; }
        focus() { document.activeElement = this; }
    }
    const trigger = new Element();
    const closeControl = new Element();
    const background = new Element();
    const alreadyInert = new Element();
    alreadyInert.inert = true;
    const classes = new Set();
    const title = { textContent: '' };
    const status = { textContent: '' };
    const content = {
        innerHTML: '',
        querySelector(selector) {
            if (selector === '[data-appointment-preview]' && this.innerHTML.includes('data-appointment-preview')) return {};
            if (selector === 'h1, h2') return { textContent: 'Appointment details' };
            return null;
        },
        querySelectorAll(selector) { return selector === '[data-modal-name]' ? [] : [closeControl]; },
    };
    const dialog = new Element();
    const host = {
        attributes: {},
        classList: {
            add: (...names) => names.forEach((name) => classes.add(name)),
            remove: (...names) => names.forEach((name) => classes.delete(name)),
            contains: (name) => classes.has(name),
            toggle(name, on) { if (on) classes.add(name); else classes.delete(name); },
        },
        setAttribute(name, value) { this.attributes[name] = value; },
        querySelector: (selector) => ({ '[data-panel-content]': content, '[data-panel-status]': status, '[data-panel-title]': title, '.casa-panel': dialog })[selector],
    };
    document.body = { children: [background, alreadyInert, host] };
    host.parentElement = document.body;
    document.querySelector = (selector) => selector === '[data-panel-host]' ? host : background;
    document.activeElement = trigger;
    const window = {
        fetch, location: { href: 'http://localhost/admin/appointments' },
        requestAnimationFrame: (callback) => callback(),
        Alpine: { initTree() {}, destroyTree() {} },
    };
    class DOMParser {
        parseFromString(html) {
            return { querySelector: (selector) => selector === '[data-page-content]' ? { innerHTML: html, querySelector: () => null } : null };
        }
    }
    const context = vm.createContext({
        window, document, Element, HTMLElement: Element, AbortController, DOMParser,
        isAppointmentPreviewUrl, isBookingPanelUrl, visibleDialogControls,
        shouldSkipNavigationUrl: () => false, syncBodyScrollLock() {}, modalStore: () => ({ active: null }),
    });
    vm.runInContext(loaderSource + '\nglobalThis.panelApi = { openPanel, closePanel };', context);
    return { ...context.panelApi, window, document, trigger, closeControl, background, alreadyInert, classes, content, host };
}

test('every preview open fetches fresh HTML without browser caching and restores focus/inert state', async () => {
    const requests = [];
    const f = fixture(async (_url, options) => { requests.push(options); return response(`Load ${requests.length}`); });
    await f.openPanel(url, f.trigger);
    assert.equal(f.classes.has('is-centered'), true);
    assert.equal(f.classes.has('is-appointment-preview'), true);
    assert.equal(f.classes.has('is-booking'), false);
    assert.equal(f.background.inert, true);
    assert.equal(f.document.activeElement, f.closeControl);
    assert.match(f.content.innerHTML, /Load 1/);
    f.closePanel();
    assert.equal(f.document.activeElement, f.trigger);
    assert.equal(f.background.inert, false);
    assert.equal(f.alreadyInert.inert, true);
    await f.openPanel(url, f.trigger);
    assert.equal(requests.length, 2);
    assert.equal(requests[0].cache, 'no-store');
    assert.equal(requests[1].cache, 'no-store');
    assert.match(f.content.innerHTML, /Load 2/);
});

test('dismissal aborts loading and late HTML cannot reopen the preview or navigate away', async () => {
    let resolve;
    let signal;
    const f = fixture((_url, options) => { signal = options.signal; return new Promise((done) => { resolve = done; }); });
    const opening = f.openPanel(url, f.trigger);
    f.closePanel();
    assert.equal(signal.aborted, true);
    resolve(response('Dismissed response'));
    await opening;
    assert.equal(f.classes.has('is-open'), false);
    assert.equal(f.content.innerHTML, '');
    assert.equal(f.window.location.href, 'http://localhost/admin/appointments');
});

test('a newer preview wins when an earlier request finishes late', async () => {
    const requests = [];
    const f = fixture((_url, options) => new Promise((resolve) => requests.push({ resolve, signal: options.signal })));
    const first = f.openPanel(url, f.trigger);
    const second = f.openPanel(new URL('http://localhost/admin/appointments/13?preview=1'), f.trigger);
    assert.equal(requests[0].signal.aborted, true);
    requests[1].resolve(response('Second visit'));
    await second;
    requests[0].resolve(response('First visit'));
    await first;
    assert.match(f.content.innerHTML, /Second visit/);
    assert.doesNotMatch(f.content.innerHTML, /First visit/);
});

test('failed preview loading falls back to the ordinary detail URL', async () => {
    const f = fixture(async () => ({ ok: false, status: 503 }));
    await f.openPanel(url, f.trigger);
    assert.equal(f.window.location.href, url.href);
});
