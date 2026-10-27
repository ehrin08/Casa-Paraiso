import test from 'node:test';
import assert from 'node:assert/strict';
import { nextHeaderHidden, headerMustStay } from '../../resources/js/scroll-header.js';

const state = (overrides) => ({ y: 400, lastY: 300, hidden: false, revealZone: 64, ...overrides });

test('the header stays visible near the top of the page', () => {
    assert.equal(nextHeaderHidden(state({ y: 40, lastY: 0 })), false);
    assert.equal(nextHeaderHidden(state({ y: 64, lastY: 300, hidden: true })), false);
});

test('scrolling down past the threshold hides the header', () => {
    assert.equal(nextHeaderHidden(state({ y: 400, lastY: 300 })), true);
});

test('scrolling up past the threshold reveals the header', () => {
    assert.equal(nextHeaderHidden(state({ y: 300, lastY: 400, hidden: true })), false);
});

test('small scroll jitter keeps the current state', () => {
    assert.equal(nextHeaderHidden(state({ y: 305, lastY: 300, hidden: false })), false);
    assert.equal(nextHeaderHidden(state({ y: 295, lastY: 300, hidden: true })), true);
});

test('focus or an open menu inside the header keeps it visible', () => {
    const button = {};
    const header = (expanded) => ({
        contains: (element) => element === button,
        querySelector: () => (expanded ? button : null),
    });
    assert.equal(headerMustStay(header(false), { activeElement: button }), true);
    assert.equal(headerMustStay(header(true), { activeElement: null }), true);
    assert.equal(headerMustStay(header(false), { activeElement: null }), false);
});
