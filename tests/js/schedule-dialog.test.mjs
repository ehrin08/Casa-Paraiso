import test from 'node:test';
import assert from 'node:assert/strict';
import { durationLabel, timeMinutes, isScheduleEditorUrl, createScheduleDialog, resetScheduleSubmission } from '../../resources/js/schedule-dialog.js';

test('midnight is the end of the business day and malformed times are rejected', () => {
    assert.equal(durationLabel('13:00', '00:00'), '11 hours');
    assert.equal(durationLabel('23:30', '00:00'), '30 minutes');
    assert.equal(durationLabel('14:00', '15:30'), '1 hour 30 minutes');
    assert.equal(durationLabel('18:00', '14:00'), 'Choose an end time after the start.');
    assert.ok(Number.isNaN(timeMinutes('99:00')));
    assert.ok(Number.isNaN(timeMinutes('13:99')));
    assert.ok(Number.isNaN(timeMinutes('bad')));
});

test('only the same-origin read-only schedule editor routes open as dialogs', () => {
    const origin = 'http://localhost:18002';
    for (const path of ['/admin/staff-schedule-roster/copy?week=2026-10-18', '/admin/staff-schedule-roster/2/bulk', '/admin/staff-schedule-roster/2/review', '/admin/staff-schedule-roster/2/shifts/create', '/admin/staff-schedule-roster/2/shifts/7/edit']) {
        assert.equal(isScheduleEditorUrl(path, origin), true, path);
    }
    for (const path of ['https://other.example/admin/staff-schedule-roster/copy', '/admin/staff-schedule-roster/2/publish', '/admin/staff-schedule-roster/2/shifts/7', '/admin/staff-schedule-roster/copy/extra', '/admin/staff-schedules']) {
        assert.equal(isScheduleEditorUrl(path, origin), false, path);
    }
});

function harness(fetchHtml) {
    const content = {
        innerHTML: '', body: { children: [], replaceChildren(...items) { this.children = items; } },
        replaceChildren() { this.innerHTML = ''; },
        querySelector() { return this.body; },
    };
    const dialog = {
        open: false, ownerDocument: { createElement(tag) { return { tag, setAttribute() {} }; } },
        showModal() { this.open = true; },
        close() { this.open = false; controller.reset(); },
    };
    let enhanced = 0;
    let focused = 0;
    let restored = 0;
    const trigger = { isConnected: true, focus() { restored++; } };
    const controller = createScheduleDialog({ dialog, content, fetchHtml, enhance() { enhanced++; }, focus() { focused++; } });
    return { controller, content, dialog, trigger, counts: () => ({ enhanced, focused, restored }) };
}

test('opening an editor fetches fresh HTML and restores focus after closing', async () => {
    const urls = [];
    const state = harness(async (url) => { urls.push(url); return '<form>Current draft</form>'; });
    await state.controller.open('/editor', state.trigger);
    assert.equal(state.dialog.open, true);
    assert.equal(state.content.innerHTML, '<form>Current draft</form>');
    assert.deepEqual(state.counts(), { enhanced: 1, focused: 1, restored: 0 });
    state.controller.close();
    assert.equal(state.content.innerHTML, '');
    assert.equal(state.counts().restored, 1);
    await state.controller.open('/editor', state.trigger);
    assert.deepEqual(urls, ['/editor', '/editor']);
});

test('dismissed responses cannot reopen or populate a dialog', async () => {
    let resolve;
    let signal;
    const state = harness((_url, requestSignal) => { signal = requestSignal; return new Promise((done) => { resolve = done; }); });
    const pending = state.controller.open('/editor', state.trigger);
    state.controller.close();
    resolve('<form>Late content</form>');
    await pending;
    assert.equal(signal.aborted, true);
    assert.equal(state.dialog.open, false);
    assert.equal(state.content.innerHTML, '');
    assert.equal(state.counts().enhanced, 0);
});

test('an older response cannot replace the newly selected editor', async () => {
    const resolvers = [];
    const signals = [];
    const state = harness((_url, signal) => { signals.push(signal); return new Promise((resolve) => { resolvers.push(resolve); }); });
    const first = state.controller.open('/first', state.trigger);
    const second = state.controller.open('/second', state.trigger);
    resolvers[1]('<form>Second</form>');
    await second;
    resolvers[0]('<form>First</form>');
    await first;
    assert.equal(signals[0].aborted, true);
    assert.equal(state.content.innerHTML, '<form>Second</form>');
    assert.equal(state.counts().enhanced, 1);
});

test('failed loading retains a close control and offers the normal full page', async () => {
    const state = harness(async () => { throw new Error('network unavailable'); });
    await state.controller.open('/editor', state.trigger);
    assert.equal(state.dialog.open, true);
    assert.match(state.content.innerHTML, /data-schedule-dialog-close/);
    assert.equal(state.content.body.children[1].href, '/editor');
    assert.equal(state.content.body.children[1].textContent, 'Open full editor');
});

test('returning through browser history restores submit labels and original disabled state', () => {
    const save = { textContent: 'Saving…', disabled: true, dataset: { scheduleOriginalLabel: 'Save draft shift', scheduleOriginalDisabled: 'false' } };
    const blocked = { textContent: 'Publishing…', disabled: true, dataset: { scheduleOriginalLabel: 'Publish', scheduleOriginalDisabled: 'true' } };
    resetScheduleSubmission({ querySelectorAll: () => [save, blocked] });
    assert.equal(save.disabled, false);
    assert.equal(save.textContent, 'Save draft shift');
    assert.equal(blocked.disabled, true);
    assert.equal(blocked.textContent, 'Publish');
    assert.deepEqual(save.dataset, {});
});
