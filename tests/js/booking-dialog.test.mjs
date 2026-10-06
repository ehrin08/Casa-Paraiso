import assert from 'node:assert/strict';
import test from 'node:test';
import { isBookingPanelUrl, isAppointmentPreviewUrl, trapBookingFocus, visibleDialogControls } from '../../resources/js/booking-dialog.js';

const control = (options = {}) => ({
    tabIndex: 0, matches: () => false, getClientRects: () => [1], closest: () => null,
    focus() { this.focused = true; }, ...options,
});

test('booking presentation stays scoped to booking create/edit pages', () => {
    for (const role of ['admin', 'reception', 'customer']) {
        assert.equal(isBookingPanelUrl(new URL('http://localhost/' + role + '/appointments/create?scheduled_start_at=2026-10-05')), true);
    }
    assert.equal(isBookingPanelUrl(new URL('http://localhost/admin/appointments/12/edit')), true);
    assert.equal(isBookingPanelUrl(new URL('http://localhost/admin/appointments/12')), false);
    assert.equal(isBookingPanelUrl(new URL('http://localhost/admin/services/create')), false);
});

test('appointment preview presentation requires an explicit admin detail preview URL', () => {
    assert.equal(isAppointmentPreviewUrl(new URL('http://localhost/admin/appointments/12?preview=1')), true);
    assert.equal(isAppointmentPreviewUrl(new URL('http://localhost/admin/appointments/12/?preview=1')), true);
    for (const path of ['/admin/appointments/12', '/admin/appointments/12?preview=0', '/admin/appointments/records?preview=1', '/admin/appointments/create?preview=1', '/admin/appointments/12/edit?preview=1', '/reception/appointments/12?preview=1', '/admin/customers/12?preview=1']) {
        assert.equal(isAppointmentPreviewUrl(new URL('http://localhost' + path)), false, path);
    }
});

test('focus navigation excludes hidden steps and disabled fieldsets', () => {
    const first = control();
    const last = control();
    const dialog = { querySelectorAll: () => [first, control({ getClientRects: () => [] }), control({ matches: () => true }), control({ tabIndex: -1 }), last], ownerDocument: { activeElement: last } };
    assert.deepEqual(visibleDialogControls(dialog), [first, last]);
    let prevented = 0;
    trapBookingFocus({ shiftKey: false, preventDefault() { prevented++; } }, dialog);
    assert.equal(first.focused, true);
    dialog.ownerDocument.activeElement = first;
    trapBookingFocus({ shiftKey: true, preventDefault() { prevented++; } }, dialog);
    assert.equal(last.focused, true);
    assert.equal(prevented, 2);
});

test('a heading focus enters the current visible controls on Tab', () => {
    const next = control();
    const dialog = { querySelectorAll: () => [next], ownerDocument: { activeElement: {} } };
    trapBookingFocus({ shiftKey: false, preventDefault() {} }, dialog);
    assert.equal(next.focused, true);
});
