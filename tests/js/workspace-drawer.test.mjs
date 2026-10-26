import test from 'node:test';
import assert from 'node:assert/strict';
import { createDrawerStore } from '../../resources/js/workspace-drawer.js';

const element = (name) => ({ name, focused: 0, focus() { this.focused++; doc.activeElement = this; } });
let doc;

const setup = ({ current = true } = {}) => {
    const classes = new Set();
    const toggle = element('toggle');
    const first = element('first');
    const active = element('active');
    const last = element('last');
    const drawer = {
        querySelector: (selector) => (selector === '[aria-current="page"]' ? (current ? active : null) : first),
        querySelectorAll: () => [first, active, last],
    };
    doc = {
        activeElement: null,
        documentElement: { classList: { add: (c) => classes.add(c), remove: (c) => classes.delete(c) } },
        querySelector: (selector) => (selector === '[data-workspace-drawer]' ? drawer : selector === '[data-drawer-toggle]' ? toggle : null),
    };
    const store = createDrawerStore(doc, (callback) => callback());
    return { store, classes, toggle, first, active, last, drawer };
};

test('opening locks scrolling and focuses the current page link', () => {
    const { store, classes, toggle, active } = setup();
    store.open(toggle);
    assert.equal(store.isOpen, true);
    assert.ok(classes.has('casa-drawer-open'));
    assert.equal(active.focused, 1);
});

test('opening without a current page focuses the first link', () => {
    const { store, toggle, first } = setup({ current: false });
    store.open(toggle);
    assert.equal(first.focused, 1);
});

test('closing unlocks scrolling and returns focus to the trigger', () => {
    const { store, classes, toggle } = setup();
    store.open(toggle);
    store.close();
    assert.equal(store.isOpen, false);
    assert.ok(!classes.has('casa-drawer-open'));
    assert.equal(toggle.focused, 1);
});

test('navigation resets close without restoring stale focus', () => {
    const { store, toggle } = setup();
    store.open(toggle);
    store.close({ restoreFocus: false });
    assert.equal(store.isOpen, false);
    assert.equal(toggle.focused, 0);
});

test('toggle alternates and closing twice is harmless', () => {
    const { store, toggle } = setup();
    store.toggle(toggle);
    assert.equal(store.isOpen, true);
    store.toggle();
    store.close();
    assert.equal(store.isOpen, false);
    assert.equal(toggle.focused, 1);
});

test('tab focus wraps inside the open drawer', () => {
    const { store, toggle, first, last, drawer } = setup();
    store.open(toggle);
    let prevented = 0;
    doc.activeElement = last;
    store.trapFocus({ shiftKey: false, preventDefault: () => prevented++ }, drawer);
    assert.equal(doc.activeElement, first);
    store.trapFocus({ shiftKey: true, preventDefault: () => prevented++ }, drawer);
    assert.equal(doc.activeElement, last);
    assert.equal(prevented, 2);
});
