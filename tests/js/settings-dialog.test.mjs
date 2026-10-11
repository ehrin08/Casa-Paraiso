import test from 'node:test';
import assert from 'node:assert/strict';
import { initializeSettingsChartDialog } from '../../resources/js/settings-dialog.js';

function fixture(initiallyOpen = false) {
    const classes = new Set();
    const doc = new EventTarget();
    doc.documentElement = { classList: { add: value => classes.add(value), remove: value => classes.delete(value) } };
    doc.defaultView = new EventTarget();
    const trigger = new EventTarget();
    trigger.isConnected = true;
    trigger.setAttribute = () => {};
    trigger.focus = () => { doc.activeElement = trigger; };
    const disclosure = { open: initiallyOpen, querySelector: () => trigger };
    const closeButton = new EventTarget();
    const doneButton = new EventTarget();
    const footer = { hidden: true };
    const draft = { value: 'Draft role', files: ['chosen-photo.webp'], focus: () => { doc.activeElement = draft; } };
    const dialog = new EventTarget();
    Object.assign(dialog, {
        open: true, dataset: {}, ownerDocument: doc, modalCalls: 0,
        closest: () => disclosure,
        removeAttribute: () => { dialog.open = false; },
        querySelector: () => draft,
        querySelectorAll: selector => selector.includes('footer') ? [closeButton, doneButton, footer] : [closeButton, doneButton],
        showModal: () => { dialog.open = true; dialog.modalCalls++; },
        close: () => { dialog.open = false; dialog.dispatchEvent(new Event('close')); },
        getBoundingClientRect: () => ({ left: 20, right: 200, top: 20, bottom: 200 }),
    });
    doc.querySelector = () => dialog;
    return { doc, dialog, trigger, disclosure, closeButton, doneButton, draft, classes, footer };
}

test('enhancement initializes once and closing/reopening retains drafts and uploads', () => {
    const f = fixture();
    initializeSettingsChartDialog(f.doc);
    initializeSettingsChartDialog(f.doc);
    assert.equal(f.dialog.open, false);
    assert.equal(f.footer.hidden, false);
    const click = new Event('click', { cancelable: true });
    f.trigger.dispatchEvent(click);
    assert.equal(click.defaultPrevented, true);
    assert.equal(f.dialog.modalCalls, 1);
    assert.equal(f.doc.activeElement, f.draft);
    assert.equal(f.classes.has('settings-chart-modal-open'), true);
    f.doneButton.dispatchEvent(new Event('click'));
    assert.equal(f.dialog.open, false);
    assert.equal(f.disclosure.open, false);
    assert.equal(f.doc.activeElement, f.trigger);
    assert.equal(f.classes.size, 0);
    f.trigger.dispatchEvent(new Event('click'));
    assert.equal(f.draft.value, 'Draft role');
    assert.deepEqual(f.draft.files, ['chosen-photo.webp']);
    f.closeButton.dispatchEvent(new Event('click'));
    assert.equal(f.dialog.open, false);
});

test('server validation opens the modal and Escape restores the page', () => {
    const f = fixture(true);
    initializeSettingsChartDialog(f.doc);
    assert.equal(f.dialog.open, true);
    const cancel = new Event('cancel', { cancelable: true });
    f.dialog.dispatchEvent(cancel);
    assert.equal(cancel.defaultPrevented, true);
    assert.equal(f.doc.activeElement, f.trigger);
    assert.equal(f.dialog.open, false);
});

test('hidden field validation opens the editor and Turbo cache clears modal state', () => {
    const f = fixture();
    initializeSettingsChartDialog(f.doc);
    f.dialog.dispatchEvent(new Event('invalid'));
    assert.equal(f.dialog.open, true);
    f.doc.dispatchEvent(new Event('turbo:before-cache'));
    assert.equal(f.dialog.open, false);
    assert.equal(f.classes.size, 0);
});

test('backdrop dismissal requires a press and release outside the panel', () => {
    const f = fixture(true);
    initializeSettingsChartDialog(f.doc);
    const pointer = (name, x) => {
        const event = new Event(name);
        Object.assign(event, { clientX: x, clientY: 0 });
        f.dialog.dispatchEvent(event);
    };
    pointer('click', 0);
    assert.equal(f.dialog.open, true);
    pointer('pointerdown', 0);
    pointer('click', 0);
    assert.equal(f.dialog.open, false);
});

test('unsupported dialog browsers retain the native disclosure fallback', () => {
    const f = fixture(true);
    f.dialog.showModal = undefined;
    initializeSettingsChartDialog(f.doc);
    assert.equal(f.dialog.open, true);
    assert.equal(f.disclosure.open, true);
    assert.equal(f.dialog.dataset.enhanced, undefined);
});
