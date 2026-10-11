import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const cleanupSource = source.slice(source.indexOf('const clearCompletionConfirmation ='), source.indexOf("document.addEventListener('turbo:fetch-request-error'"));

function fixture(active = 'appointment-completed-1', includeConfirmation = true) {
    const removed = [];
    const confirmations = includeConfirmation ? ['wrapper', 'teleported dialog'].map((id) => ({
        dataset: { modalName: 'appointment-completed-1' },
        remove: () => removed.push(id),
    })) : [];
    const state = { active, closes: 0, close() { this.active = null; this.closes++; } };
    let pagehide;
    const context = vm.createContext({
        document: { querySelectorAll: () => confirmations },
        modalStore: () => state,
        window: { addEventListener: (event, callback) => { if (event === 'pagehide') pagehide = callback; } },
    });
    vm.runInContext(cleanupSource, context);
    return { state, removed, pagehide };
}

test('leaving a success page closes its modal and removes both teleported and original content', () => {
    const { state, removed, pagehide } = fixture();
    pagehide();
    assert.equal(state.active, null);
    assert.equal(state.closes, 1);
    assert.deepEqual(removed, ['wrapper', 'teleported dialog']);
});

test('removing a dismissed confirmation preserves an unrelated open modal', () => {
    const { state, removed, pagehide } = fixture('other-modal');
    pagehide();
    assert.equal(state.active, 'other-modal');
    assert.equal(state.closes, 0);
    assert.equal(removed.length, 2);
});

test('ordinary pages have no transient confirmation to remove', () => {
    const { state, removed, pagehide } = fixture('other-modal', false);
    pagehide();
    assert.equal(state.closes, 0);
    assert.equal(removed.length, 0);
});
