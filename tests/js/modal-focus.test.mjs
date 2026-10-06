import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

// Exercise the existing Alpine modal factory without booting the unrelated app modules.
const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const factorySource = source.slice(source.indexOf('window.casaModal ='), source.indexOf("window.addEventListener('open-modal'"));

function fixture() {
    const document = { activeElement: null };
    const control = (id, disabled = false) => ({
        id,
        hasAttribute: (name) => name === 'disabled' && disabled,
        focus: () => { document.activeElement = controls.find((element) => element.id === id); },
    });
    const controls = [control('amount'), control('disabled', true), control('status'), control('close')];
    const dialog = { dataset: { modalName: 'completion' }, querySelectorAll: () => controls };
    const unrelated = { dataset: { modalName: 'other' }, querySelectorAll: () => [control('wrong-dialog')] };
    document.querySelectorAll = () => [unrelated, dialog];
    const watchers = [];
    const state = { active: 'completion' };
    const context = vm.createContext({
        window: { setTimeout: (callback) => callback() },
        document,
        modalStoreName: 'casaModal',
        Alpine: { store: () => state },
        modalStore: () => state,
    });
    vm.runInContext(factorySource, context);
    const modal = context.window.casaModal({ name: 'completion', focusable: true });
    modal.$el = { querySelectorAll: () => [] }; // Alpine's original wrapper is empty after teleport.
    modal.$watch = (_name, callback) => watchers.push(callback);
    return { modal, document, controls, watchers, state };
}

test('initial focus enters the named teleported dialog and excludes disabled controls', () => {
    const { modal, document } = fixture();
    modal.init();
    assert.equal(document.activeElement.id, 'amount');
    assert.equal(modal.focusables().length, 3);
});

test('opening a closed modal moves focus into its teleported content', () => {
    const { modal, document, watchers, state } = fixture();
    state.active = null;
    modal.init();
    state.active = 'completion';
    watchers[0](true);
    assert.equal(document.activeElement.id, 'amount');
});

test('Tab and Shift+Tab wrap within the dialog instead of reaching background controls', () => {
    const { modal, document, controls } = fixture();
    let prevented = 0;
    document.activeElement = controls[3];
    modal.handleTab({ shiftKey: false, preventDefault: () => prevented++ });
    assert.equal(document.activeElement.id, 'amount');
    modal.handleTab({ shiftKey: true, preventDefault: () => prevented++ });
    assert.equal(document.activeElement.id, 'close');
    assert.equal(prevented, 2);
});

test('Tab from outside the dialog enters its first enabled control', () => {
    const { modal, document } = fixture();
    document.activeElement = { id: 'background-trigger' };
    modal.handleTab({ shiftKey: false, preventDefault() {} });
    assert.equal(document.activeElement.id, 'amount');
});
