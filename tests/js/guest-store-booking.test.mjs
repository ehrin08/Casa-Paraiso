import assert from 'node:assert/strict';
import test from 'node:test';
import { createOperationsBooking, createCustomerBooking } from '../../resources/js/appointment-booking.js';

const slot = (time = '13:00', count = 1) => ({ starts_at: '2026-10-05 ' + time + ':00', ends_at: '2026-10-05 14:00:00', time, label: time, staff_count: count });
const dates = { '2026-10-05': [slot(), slot('15:00')] };
const config = { availabilityUrl: '/availability', availableUrl: '/therapists', customerLookupUrl: '/lookup', initialMonth: '2026-10', initialServiceId: '2', services: [{ id: '2', duration: 60, price: 499 }] };
const operations = (options = {}, get = async () => ({ data: {} })) => createOperationsBooking({ ...config, ...options }, get);

test('typed guests advance without an account and retain optional phone on back navigation', () => {
    const booking = operations({ initialCustomerName: '  Walk-in  ', initialCustomerPhone: '09123456789' });
    booking.nextStep();
    assert.equal(booking.step, 2);
    booking.previousStep();
    assert.equal(booking.customerLabel, 'Walk-in');
    assert.equal(booking.customerPhone, '09123456789');
    booking.customerName = '   ';
    assert.equal(booking.treatmentReady, false);
});

test('profile reuse requires selection and editing the name clears identity and saved contact', () => {
    const booking = operations();
    booking.customerName = 'Shared Name';
    const profile = { id: 7, name: 'Shared Name', phone: '111', customer_code: 'CP-GUEST', is_guest: true };
    booking.customerResults = [profile];
    assert.equal(booking.customerId, '');
    booking.selectCustomer(profile);
    assert.equal(booking.customerId, '7');
    assert.equal(booking.customerPhone, '111');
    booking.customerName = 'Different guest';
    booking.customerEdited();
    assert.equal(booking.customerId, '');
    assert.equal(booking.customerPhone, '');
    assert.equal(booking.customerCode, '');
    booking.destroy();
});

test('suggestions support arrows, explicit Enter selection, and Escape containment', () => {
    const booking = operations();
    booking.customerResults = [{ id: 7, name: 'Guest', is_guest: true }, { id: 8, name: 'Enrolled', is_guest: false }];
    let stopped = 0;
    const key = name => ({ key: name, preventDefault() {}, stopPropagation() { stopped++; } });
    booking.customerKeydown(key('ArrowUp'));
    assert.equal(booking.customerActiveIndex, 1);
    booking.customerKeydown(key('ArrowDown'));
    assert.equal(booking.customerActiveIndex, 0);
    booking.customerKeydown(key('Enter'));
    assert.equal(booking.customerId, '7');
    assert.equal(booking.customerSearchOpen, false);
    booking.customerSearchOpen = true;
    booking.customerKeydown(key('Escape'));
    assert.equal(booking.customerSearchOpen, false);
    assert.equal(stopped, 1);
});

test('stale customer searches and destroyed forms cannot replace current results', async () => {
    const pending = [];
    const booking = operations({}, () => new Promise(resolve => pending.push(resolve)));
    booking.customerName = 'First';
    const first = booking.searchCustomers();
    booking.customerName = 'Second';
    const second = booking.searchCustomers();
    pending[1]({ data: { customers: [{ id: 2, name: 'Second' }] } });
    await second;
    pending[0]({ data: { customers: [{ id: 1, name: 'First' }] } });
    await first;
    assert.equal(booking.customerResults[0].id, 2);
    assert.equal(booking.customerName, 'Second');
    const third = booking.searchCustomers();
    booking.destroy();
    pending[2]({ data: { customers: [{ id: 3 }] } });
    await third;
    assert.equal(booking.customerResults[0].id, 2);
});

test('lookup failures preserve typed guest details and allow retry', async () => {
    let fails = true;
    const booking = operations({ initialCustomerName: 'New Guest', initialCustomerPhone: '555' }, async () => {
        if (fails) throw new Error('offline');
        return { data: { customers: [] } };
    });
    await booking.searchCustomers();
    assert.match(booking.customerSearchError, /new guest/);
    assert.equal(booking.customerName, 'New Guest');
    assert.equal(booking.customerPhone, '555');
    assert.equal(booking.treatmentReady, true);
    fails = false;
    await booking.searchCustomers();
    assert.equal(booking.customerSearchError, '');
});

test('store-open dates and all starts stay visible with zero therapist capacity', async () => {
    const unavailable = [slot('13:00', 0), slot('13:30', 0), slot('23:30', 0)];
    const booking = createCustomerBooking(config, async () => ({ data: { dates: {}, store_dates: { '2026-10-05': { slots: unavailable } } } }));
    await booking.fetchAvailability();
    assert.equal(booking.calendarDays.find(day => day.date === '2026-10-05').available, true);
    booking.selectDate('2026-10-05');
    assert.equal(booking.selectedDate, '2026-10-05');
    assert.equal(booking.selectedDateSlots.length, 3);
    assert.equal(booking.hasAvailableTimes, false);
    booking.chooseSlot(unavailable[0]);
    assert.equal(booking.selectedSlot, '');
    booking.selectedSlot = '2026-10-05T13:00';
    booking.startChanged();
    assert.equal(booking.selectedSlot, '');
    assert.equal(booking.scheduleReady, false);
});

test('dropdown starts require verified capacity and refresh assigned therapists', async () => {
    const booking = operations({ initialCustomerName: 'Guest', initialStaffId: '3' }, async url => ({ data: url === '/therapists' ? { therapists: [{ id: 3 }] } : { dates, store_dates: { '2026-10-05': { slots: [slot('12:30', 0), ...dates['2026-10-05']] } } } }));
    await booking.fetchAvailability();
    booking.selectDate('2026-10-05');
    booking.selectedSlot = '2026-10-05T15:00';
    booking.startChanged();
    await booking.refreshTherapists();
    assert.equal(booking.scheduleReady, true);
    assert.equal(booking.selectedSlot, '2026-10-05T15:00');
    booking.selectedSlot = '2026-10-05T12:30';
    booking.startChanged();
    assert.equal(booking.scheduleReady, false);
});

test('unchanged edit assignment remains enabled for submission when availability fails', async () => {
    const booking = operations({ appointmentId: 4, initialCustomerName: 'Guest', initialScheduledStart: '2026-10-05T13:00', initialStaffId: '3', persistedServiceId: '2', persistedScheduledStart: '2026-10-05T13:00', persistedStaffId: '3' }, async () => { throw new Error('offline'); });
    booking.loading = true;
    assert.equal(booking.assignmentDisabled, false);
    await booking.fetchAvailability();
    await booking.refreshTherapists();
    assert.equal(booking.canSubmit, true);
    assert.equal(booking.assignmentDisabled, false);
    booking.selectedSlot = '2026-10-05T15:00';
    assert.equal(booking.assignmentDisabled, true);
    assert.equal(booking.canSubmit, false);
});
