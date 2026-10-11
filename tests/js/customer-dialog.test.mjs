import assert from 'node:assert/strict';
import test from 'node:test';
import { isCustomerModalUrl, restoreCustomerDetail, clearCustomerDetailRecovery } from '../../resources/js/customer-dialog.js';

test('only admin customer details explicitly requesting a modal receive modal behavior', () => {
    for (const path of ['/admin/customers/12?modal=1', '/admin/customers/12/?modal=1']) {
        assert.equal(isCustomerModalUrl(new URL('http://localhost' + path)), true);
    }
    for (const path of ['/admin/customers/12', '/admin/customers/12?modal=0', '/admin/customers/create?modal=1', '/admin/customers/12/edit?modal=1', '/staff/customers/12?modal=1', '/reception/customers/12?modal=1', '/customer/appointments/12?modal=1']) {
        assert.equal(isCustomerModalUrl(new URL('http://localhost' + path)), false, path);
    }
});

test('note recovery renders server HTML once and restores the matching Open trigger', () => {
    const trigger = { dataset: { customerOpen: '12' } };
    let recovery = {
        dataset: { customerId: '12' }, innerHTML: '<div data-customer-detail>Saved notes</div>',
        remove() { recovery = null; },
    };
    const document = {
        querySelector: () => recovery,
        querySelectorAll: () => [{ dataset: { customerOpen: '13' } }, trigger],
    };
    const html = [];
    let restoredTrigger;
    const restore = () => restoreCustomerDetail(document, (content) => html.push(content), (value) => { restoredTrigger = value; });
    restore();
    restore();
    assert.deepEqual(html, ['<div data-customer-detail>Saved notes</div>']);
    assert.equal(restoredTrigger, trigger);
});

test('recovery supports customers outside the current filtered page', () => {
    let restoredTrigger;
    let rendered = false;
    const recovery = { dataset: { customerId: '12' }, innerHTML: 'Customer details', remove() {} };
    restoreCustomerDetail({ querySelector: () => recovery, querySelectorAll: () => [] }, () => { rendered = true; }, (value) => { restoredTrigger = value; });
    assert.equal(rendered, true);
    assert.equal(restoredTrigger, null);
});

test('Turbo cache cleanup removes unopened recovery templates', () => {
    let removed = 0;
    clearCustomerDetailRecovery({ querySelectorAll: () => [{ remove() { removed++; } }, { remove() { removed++; } }] });
    assert.equal(removed, 2);
});
