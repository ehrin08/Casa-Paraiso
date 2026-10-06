import assert from 'node:assert/strict';
import test from 'node:test';
import { createOperationsBooking, createCustomerBooking } from '../../resources/js/appointment-booking.js';

const slot = (time = '13:00', count = 1) => ({
    starts_at: '2026-10-05 ' + time + ':00', ends_at: '2026-10-05 14:00:00',
    time, label: time, staff_count: count,
});
const dates = { '2026-10-05': [slot(), slot('15:00')] };
const opsConfig = {
    availabilityUrl: '/availability', availableUrl: '/therapists',
    initialMonth: '2026-10', initialScheduledStart: '2026-10-05T13:00',
    initialCustomerId: '1', initialServiceId: '2', initialStaffId: '3',
    services: [{ id: '2', name: 'Massage', price: 499, duration: 60 }],
    addonOptions: [{ code: 'back', price: 299, duration_minutes: 30 }],
};
const get = async (url) => ({ data: url === '/therapists' ? { therapists: [{ id: 3 }] } : { dates } });
const operations = (config = {}, fetch = get) => createOperationsBooking({ ...opsConfig, ...config }, fetch);
const customer = (config = {}, fetch = get) => createCustomerBooking({
    ...opsConfig, initialSlot: '2026-10-05T13:00', initialScheduledStart: undefined,
    staffOptions: [{ id: 3, name: 'Ana', service_ids: [2] }],
    vouchers: [{ id: 9, addon_code: 'back', addon_name: 'Back Massage', duration_minutes: 30 }],
    ...config,
}, fetch);

test('calendar slots retain canonical local values and Manila start/end labels', async () => {
    const booking = operations();
    await booking.fetchAvailability();
    await booking.refreshTherapists();
    assert.equal(booking.scheduledStart, '2026-10-05T13:00');
    assert.equal(booking.summaryDate, 'Monday, October 5, 2026');
    assert.equal(booking.summaryTime, '1:00 PM');
    assert.equal(booking.summaryEndTime, '2:00 PM');
    booking.scheduleTime = '23:00';
    assert.equal(booking.summaryEndTime, '12:00 AM (next day)');
});

test('wizard validates selections and keeps fields when moving back', async () => {
    const booking = operations({ initialCustomerId: '', initialScheduledStart: null, initialStaffId: '' });
    booking.nextStep();
    assert.equal(booking.step, 1);
    assert.match(booking.stepError, /treatment/);
    booking.customerId = '1';
    booking.nextStep();
    assert.equal(booking.step, 2);
    booking.nextStep();
    assert.equal(booking.step, 2);
    await booking.fetchAvailability();
    booking.selectDate('2026-10-05');
    booking.chooseSlot(slot());
    await booking.refreshTherapists();
    booking.staffId = '3';
    booking.nextStep();
    assert.equal(booking.step, 3);
    booking.previousStep();
    booking.previousStep();
    assert.equal(booking.customerId, '1');
    assert.equal(booking.scheduledStart, '2026-10-05T13:00');
    assert.equal(booking.staffId, '3');
});

test('submit only proceeds from final step with verified availability', async () => {
    const booking = operations();
    let prevented = 0;
    booking.submitBooking({ preventDefault() { prevented++; } });
    assert.equal(prevented, 1);
    assert.equal(booking.submitting, false);
    await booking.fetchAvailability();
    await booking.refreshTherapists();
    booking.nextStep();
    booking.submitBooking({ preventDefault() { prevented++; } });
    assert.equal(prevented, 1);
    assert.equal(booking.submitting, true);
});

test('totals and duration include paid add-ons and complimentary voucher duration', () => {
    const booking = customer({ initialVoucherId: '9' });
    assert.equal(booking.bookingTotal, 499);
    assert.equal(booking.durationMinutes, 90);
    booking.addonCodes = ['back'];
    assert.equal(booking.bookingTotal, 798);
    booking.voucherChanged();
    assert.deepEqual(booking.addonCodes, []);
    assert.equal(booking.bookingTotal, 499);
});

test('unverified or failed requests prevent new bookings', async () => {
    const booking = operations({}, async () => { throw new Error('offline'); });
    await booking.fetchAvailability();
    assert.equal(booking.canSubmit, false);
    assert.match(booking.error, /try again/);
    await booking.refreshTherapists();
    assert.equal(booking.canSubmit, false);
    assert.match(booking.therapistError, /try again/);
});

test('unchanged schedules remain usable for historical and inactive assignments', async () => {
    const booking = operations({
        appointmentId: 4, persistedServiceId: '2', persistedStaffId: '3',
        persistedScheduledStart: '2026-10-05T13:00', persistedAddonCodes: ['back'],
        initialAddonCodes: ['back'], scheduleLocked: true,
    }, async () => { throw new Error('offline'); });
    await booking.fetchAvailability();
    assert.equal(booking.canSubmit, true);
    assert.equal(booking.staffIsAvailable('3'), true);
    booking.addonCodes = [];
    assert.equal(booking.canSubmit, false);
});

test('stale month responses cannot restore an invalid selection', async () => {
    const pending = [];
    const booking = customer({}, () => new Promise(resolve => pending.push(resolve)));
    const first = booking.fetchAvailability();
    booking.serviceId = '8';
    const second = booking.fetchAvailability();
    pending[1]({ data: { dates: {} } });
    await second;
    pending[0]({ data: { dates } });
    await first;
    assert.deepEqual(booking.slotsByDate, {});
    assert.equal(booking.selectedSlot, '');
    assert.equal(booking.loading, false);
});

test('stale therapist responses cannot overwrite the latest time', async () => {
    const pending = [];
    const booking = operations({}, () => new Promise(resolve => pending.push(resolve)));
    const first = booking.refreshTherapists();
    booking.scheduleTime = '15:00';
    const second = booking.refreshTherapists();
    pending[1]({ data: { therapists: [{ id: 3 }] } });
    await second;
    pending[0]({ data: { therapists: [] } });
    await first;
    assert.equal(booking.staffId, '3');
    assert.equal(booking.loadingTherapists, false);
});

test('duration changes recheck and clear unavailable slots', async () => {
    const booking = customer({}, async () => ({ data: { dates: {} } }));
    booking.addonCodes = ['back'];
    await booking.addonChanged();
    assert.equal(booking.selectedSlot, '');
    assert.equal(booking.durationMinutes, 90);
});

test('month navigation handles year boundaries and unavailable dates', async () => {
    const booking = customer({ initialMonth: '2026-12', initialSlot: null });
    await booking.nextMonth();
    assert.equal(booking.month, '2027-01');
    assert.equal(booking.calendarDays.filter(day => day.date).length, 31);
    booking.selectDate('2027-01-03');
    assert.equal(booking.selectedSlot, '');
});

test('destroyed forms ignore pending availability and abort work', async () => {
    let resolve;
    const booking = customer({}, () => new Promise(done => { resolve = done; }));
    const request = booking.fetchAvailability();
    booking.destroy();
    resolve({ data: { dates } });
    await request;
    assert.deepEqual(booking.slotsByDate, {});
});

test('native field errors keep the wizard on the affected step', async () => {
    const booking = operations();
    let reported = 0;
    booking.$el = { querySelectorAll() { return [{ matches: () => false, checkValidity: () => false, reportValidity() { reported++; } }]; } };
    booking.nextStep();
    assert.equal(booking.step, 1);
    assert.equal(reported, 1);
    booking.$el = undefined;
    await booking.fetchAvailability();
    await booking.refreshTherapists();
    booking.nextStep();
    booking.nextStep();
    booking.$el = { querySelectorAll() { return [{ matches: () => false, checkValidity: () => false, reportValidity() { reported++; } }]; } };
    let prevented = false;
    booking.submitBooking({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(booking.submitting, false);
});

test('unchanged edit review uses the stored end even when the catalog duration changes', () => {
    const booking = operations({ appointmentId: 4, persistedServiceId: '2', persistedStaffId: '3', persistedScheduledStart: '2026-10-05T13:00', persistedScheduledEnd: '2026-10-05T14:30' });
    assert.equal(booking.durationMinutes, 90);
    assert.equal(booking.summaryEndTime, '2:30 PM');
});
