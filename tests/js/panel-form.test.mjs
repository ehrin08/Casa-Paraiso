import assert from 'node:assert/strict';
import test from 'node:test';
import { isFormPanelUrl, attachPanelReturn, isSafePanelPath, takePanelRestore } from '../../resources/js/panel-form.js';

const prefixes = ['/admin/addons/', '/admin/services/', '/reception/transactions/'];
const url = (path) => new URL('http://localhost' + path);

test('create and edit pages use the form panel, booking wizards keep their own', () => {
    for (const path of ['/admin/addons/create', '/admin/services/4/edit', '/admin/staff/3/schedule-exceptions/create', '/reception/transactions/9/edit/', '/admin/transactions/create?appointment_id=5']) {
        assert.equal(isFormPanelUrl(url(path)), true, path);
    }
    for (const path of ['/admin/appointments/create', '/reception/appointments/4/edit', '/customer/appointments/create', '/admin/services', '/admin/services/4', '/admin/customers/2?modal=1']) {
        assert.equal(isFormPanelUrl(url(path)), false, path);
    }
});

const fakeForm = (method, existing = null) => {
    const children = existing ? [existing] : [];
    return {
        children,
        getAttribute: (name) => (name === 'method' ? method : null),
        querySelector: () => children.find((child) => child.name === '_panel') || null,
        prepend(child) { children.unshift(child); },
        ownerDocument: { createElement: () => ({}) },
    };
};

test('mutating panel forms carry the panel URL; GET filters do not', () => {
    const post = fakeForm('POST');
    const get = fakeForm('GET');
    const reused = fakeForm('post', { name: '_panel', value: '/stale' });
    attachPanelReturn({ querySelectorAll: () => [post, get, reused] }, url('/admin/transactions/create?appointment_id=5'));

    assert.deepEqual(post.children.map(({ type, name, value }) => ({ type, name, value })), [{ type: 'hidden', name: '_panel', value: '/admin/transactions/create?appointment_id=5' }]);
    assert.equal(get.children.length, 0);
    assert.equal(reused.children.length, 1);
    assert.equal(reused.children[0].value, '/admin/transactions/create?appointment_id=5');
});

test('only same-origin paths under panel prefixes are safe', () => {
    assert.equal(isSafePanelPath('/admin/addons/create', prefixes), true);
    for (const path of ['//evil.example/admin/addons/create', 'https://evil.example/admin/addons/create', '/profile', '/admin/addons\\x', 'admin/addons/create', null, 42]) {
        assert.equal(isSafePanelPath(path, prefixes), false, String(path));
    }
});

test('restore is consumed once and ignores unsafe markers', () => {
    const host = (value) => {
        const attributes = { 'data-panel-restore': value };
        return { getAttribute: (name) => attributes[name], removeAttribute: (name) => { delete attributes[name]; } };
    };
    let current = host('/admin/services/4/edit');
    const document = { location: { origin: 'http://localhost' }, querySelector: () => (current && current.getAttribute('data-panel-restore') !== undefined ? current : null) };

    assert.equal(takePanelRestore(document, prefixes)?.href, 'http://localhost/admin/services/4/edit');
    assert.equal(takePanelRestore(document, prefixes), null);

    current = host('//evil.example/admin/services/4/edit');
    assert.equal(takePanelRestore(document, prefixes), null);
});
