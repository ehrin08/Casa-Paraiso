const canonicalStart = (value = '') => String(value).replace(' ', 'T').slice(0, 16);
const sameCodes = (left, right) => [...left].sort().join('|') === [...right].sort().join('|');
const extend = (base, properties) => Object.defineProperties(base, Object.getOwnPropertyDescriptors(properties));
const manilaDate = (value) => new Date(`${canonicalStart(value)}:00+08:00`);
const dateLabel = (value) => value ? new Date(`${value}T12:00:00+08:00`).toLocaleDateString('en-PH', {
    timeZone: 'Asia/Manila', weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
}) : 'Choose a date';
const timeLabel = (value) => value ? manilaDate(value).toLocaleTimeString('en-PH', {
    timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit',
}) : 'Choose a time';

function bookingBase(config, get) {
    return {
        step: Number(config.initialStep || 1),
        furthestStep: Number(config.initialStep || 1),
        stepError: '',
        submitting: false,
        services: config.services || [],
        addonOptions: config.addonOptions || [],
        serviceId: String(config.initialServiceId || ''),
        addonCodes: [...(config.initialAddonCodes || [])],
        month: config.initialMonth || canonicalStart(config.initialScheduledStart || config.initialSlot).slice(0, 7),
        selectedDate: canonicalStart(config.initialScheduledStart || config.initialSlot).slice(0, 10),
        selectedSlot: canonicalStart(config.initialScheduledStart || config.initialSlot),
        slotsByDate: {},
        storeDates: {},
        loading: false,
        error: '',
        availabilityRequest: 0,
        availabilityAbort: null,
        destroyed: false,
        weekDays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        slotPreviewLimit: config.slotPreviewLimit || 2,

        init() {
            this.fetchAvailability();
        },
        destroy() {
            this.destroyed = true;
            this.availabilityRequest++;
            this.availabilityAbort?.abort();
            if (this.therapistRequest !== undefined) this.therapistRequest++;
            this.therapistAbort?.abort();
            clearTimeout(this.customerSearchTimer);
            if (this.customerRequest !== undefined) this.customerRequest++;
            this.customerAbort?.abort();
        },
        get selectedService() { return this.services.find((service) => String(service.id) === this.serviceId) || null; },
        get selectedPaidAddons() { return this.addonOptions.filter((addon) => this.addonCodes.includes(addon.code)); },
        get paidAddonTotal() { return this.selectedPaidAddons.reduce((total, addon) => total + Number(addon.price || 0), 0); },
        get addonDurationMinutes() { return this.selectedPaidAddons.reduce((total, addon) => total + Number(addon.duration_minutes || 0), 0); },
        get voucherDurationMinutes() { return 0; },
        get canUsePersistedSchedule() { return false; },
        get durationMinutes() { return this.canUsePersistedSchedule && this.persistedScheduledEnd ? Math.round((manilaDate(this.persistedScheduledEnd) - manilaDate(this.selectedSlot)) / 60000) : Number(this.selectedService?.duration || 0) + this.addonDurationMinutes + this.voucherDurationMinutes; },
        get bookingTotal() { return Number(this.selectedService?.price || 0) + this.paidAddonTotal; },
        get selectedDateSlots() { return this.storeDates[this.selectedDate]?.slots || this.slotsByDate[this.selectedDate] || []; },
        get selectedSlotData() { return (this.slotsByDate[this.selectedDate] || []).find((slot) => canonicalStart(slot.starts_at) === this.selectedSlot && slot.staff_count > 0); },
        get hasAvailableTimes() { return this.selectedDateSlots.some(slot => slot.staff_count > 0); },
        get verifiedSlot() { return Boolean(this.selectedSlotData && !this.loading && !this.error); },
        get summaryDate() { return dateLabel(this.selectedSlot.slice(0, 10) || this.selectedDate); },
        get selectedDateLabel() { return dateLabel(this.selectedDate); },
        get summaryTime() { return timeLabel(this.selectedSlot); },
        get summaryEndTime() {
            if (!this.selectedSlot) return '';
            const endValue = this.canUsePersistedSchedule ? this.persistedScheduledEnd : this.selectedSlotData?.ends_at;
            const end = endValue ? manilaDate(endValue) : new Date(manilaDate(this.selectedSlot).getTime() + this.durationMinutes * 60000);
            const nextDay = end.toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' }) !== manilaDate(this.selectedSlot).toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' });
            return end.toLocaleTimeString('en-PH', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit' }) + (nextDay ? ' (next day)' : '');
        },
        get selectedSlotLabel() { return this.selectedSlot ? `${this.summaryDate} · ${this.summaryTime}` : ''; },
        get monthLabel() {
            return new Date(`${this.month}-01T12:00:00+08:00`).toLocaleDateString('en-PH', { timeZone: 'Asia/Manila', month: 'long', year: 'numeric' });
        },
        get calendarDays() {
            const [year, month] = this.month.split('-').map(Number);
            const firstDay = new Date(Date.UTC(year, month - 1, 1));
            const count = new Date(Date.UTC(year, month, 0)).getUTCDate();
            const days = Array.from({ length: firstDay.getUTCDay() }, (_, index) => ({ key: `blank-${index}`, date: null, label: '', available: false, previewSlots: [], moreSlots: 0 }));
            for (let day = 1; day <= count; day++) {
                const date = `${this.month}-${String(day).padStart(2, '0')}`;
                const slots = this.slotsByDate[date] || [];
                days.push({ key: date, date, label: day, available: Boolean(this.storeDates[date] || slots.length), previewSlots: slots.slice(0, this.slotPreviewLimit), moreSlots: Math.max(0, slots.length - this.slotPreviewLimit) });
            }
            return days;
        },
        get hasOpenDates() { return Object.keys(this.storeDates).length > 0 || Object.keys(this.slotsByDate).length > 0; },
        get canAdvance() { return this.step === 1 ? this.treatmentReady : this.scheduleReady; },
        get canSubmit() { return this.treatmentReady && this.scheduleReady && !this.submitting; },
        stepFieldsValid(step) {
            const fields = (this.$root || this.$el)?.querySelectorAll(`[data-booking-step="${step}"] input, [data-booking-step="${step}"] select, [data-booking-step="${step}"] textarea`) || [];
            const invalid = [...fields].find(field => !field.matches(':disabled') && !field.checkValidity());
            invalid?.reportValidity();
            return !invalid;
        },
        focusStep() {
            this.$nextTick?.(() => {
                const section = (this.$root || this.$el)?.querySelector(`[data-booking-step="${this.step}"]`);
                section?.querySelector('[data-step-heading]')?.focus({ preventScroll: true });
                section?.closest('.booking-wizard-body')?.scrollTo({ top: 0 });
            });
        },
        goToStep(step) {
            if (step > this.furthestStep || step < 1 || step > 3) return;
            if (step > 1 && (!this.treatmentReady || !this.stepFieldsValid(1))) return;
            if (step > 2 && !this.scheduleReady) return;
            this.step = step;
            this.stepError = '';
            this.focusStep();
        },
        nextStep() {
            if (!this.canAdvance || !this.stepFieldsValid(this.step)) {
                this.stepError = this.step === 1 ? 'Complete the treatment details to continue.' : 'Choose an available time and required therapist to continue.';
                return;
            }
            this.step = Math.min(3, this.step + 1);
            this.furthestStep = Math.max(this.furthestStep, this.step);
            this.stepError = '';
            this.focusStep();
        },
        previousStep() { this.goToStep(this.step - 1); },
        submitBooking(event) {
            if (this.step !== 3) {
                event.preventDefault();
                this.nextStep();
                return;
            }
            if (!this.canSubmit) {
                event.preventDefault();
                this.step = this.treatmentReady ? 2 : 1;
                this.stepError = 'Check your booking selections before saving.';
                this.focusStep();
                return;
            }
            if (!this.stepFieldsValid(3)) { event.preventDefault(); this.stepError = 'Check the notes before saving.'; return; }
            this.submitting = true;
        },
        cancelBooking(event) {
            if (this.$el?.closest('[data-panel-content]')) {
                event.preventDefault();
                window.dispatchEvent(new CustomEvent('close-workspace-panel'));
            }
        },
        selectDate(date) {
            if (!date || (!this.storeDates[date] && !this.slotsByDate[date]?.length) || this.loading || this.error) return;
            this.selectedDate = date;
            this.clearSlot();
        },
        chooseSlot(slot) {
            if (this.loading || this.error || !this.selectedDateSlots.some((item) => item.starts_at === slot.starts_at && item.staff_count > 0)) return;
            this.selectedSlot = canonicalStart(slot.starts_at);
            this.stepError = '';
            this.slotChanged();
        },
        clearSlot() { this.selectedSlot = ''; this.slotChanged(); },
        startChanged() {
            const slot = this.selectedDateSlots.find(item => canonicalStart(item.starts_at) === this.selectedSlot && item.staff_count > 0);
            if (slot) this.chooseSlot(slot);
            else if (!this.canUsePersistedSchedule) this.clearSlot();
        },
        slotChanged() {},
        moreSlotsLabel(day) { return day.moreSlots ? `+${day.moreSlots} more` : ''; },
        changeMonth(amount) {
            const [year, month] = this.month.split('-').map(Number);
            const date = new Date(Date.UTC(year, month - 1 + amount, 1));
            this.month = `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
            this.selectedDate = '';
            this.clearSlot();
            return this.fetchAvailability();
        },
        previousMonth() { return this.changeMonth(-1); },
        nextMonth() { return this.changeMonth(1); },
        preselectDate(date) {
            if (!date) return;
            this.month = date.slice(0, 7);
            this.selectedDate = date;
            this.clearSlot();
            return this.fetchAvailability();
        },
        async moveAvailableDate(date, amount) {
            const next = new Date(`${date}T12:00:00Z`);
            next.setUTCDate(next.getUTCDate() + amount);
            const key = next.toISOString().slice(0, 10);
            if (key.slice(0, 7) !== this.month) {
                this.month = key.slice(0, 7);
                await this.fetchAvailability();
            }
            this.selectDate(key);
            this.$nextTick?.(() => (this.$root || this.$el)?.querySelector(`[data-booking-calendar-day="${key}"]`)?.focus());
        },
        async fetchAvailability() {
            const request = ++this.availabilityRequest;
            this.availabilityAbort?.abort();
            this.availabilityAbort = new AbortController();
            this.error = '';
            this.slotsByDate = {};
            this.storeDates = {};
            if (!this.serviceId || this.scheduleLocked) { this.loading = false; return; }
            this.loading = true;
            try {
                const response = await get(this.availabilityUrl, { params: this.availabilityParams(), signal: this.availabilityAbort.signal });
                if (request !== this.availabilityRequest || this.destroyed) return;
                this.slotsByDate = response.data.dates || {};
                this.storeDates = response.data.store_dates || {};
                if (this.selectedSlot && !this.selectedSlotData && !this.canUsePersistedSchedule) this.clearSlot();
                this.availabilityLoaded();
            } catch (error) {
                if (request !== this.availabilityRequest || this.destroyed) return;
                this.error = error.response?.data?.message || 'Availability could not be loaded. Please try again.';
            } finally {
                if (request === this.availabilityRequest) this.loading = false;
            }
        },
        availabilityLoaded() {},
    };
}

export function createOperationsBooking(config, get = (url, options) => window.axios.get(url, options)) {
    return extend(bookingBase(config, get), {
        availabilityUrl: config.availabilityUrl,
        availableUrl: config.availableUrl,
        appointmentId: config.appointmentId || '',
        customerId: String(config.initialCustomerId || ''),
        customerNames: config.customerNames || {},
        customerName: config.initialCustomerName || config.customerNames?.[config.initialCustomerId] || '',
        customerPhone: config.initialCustomerPhone || '',
        customerCode: config.initialCustomerCode || '',
        customerIsGuest: Boolean(config.initialCustomerIsGuest),
        customerLookupUrl: config.customerLookupUrl,
        customerResults: [],
        customerSearchOpen: false,
        customerSearchLoading: false,
        customerSearchError: '',
        customerActiveIndex: -1,
        customerSearchTimer: null,
        customerRequest: 0,
        customerAbort: null,
        staffId: String(config.initialStaffId || ''),
        staffNames: config.staffNames || {},
        status: config.initialStatus || 'confirmed',
        scheduleLocked: Boolean(config.scheduleLocked),
        persistedServiceId: String(config.persistedServiceId || ''),
        persistedScheduledStart: canonicalStart(config.persistedScheduledStart),
        persistedStaffId: String(config.persistedStaffId || ''),
        persistedScheduledEnd: canonicalStart(config.persistedScheduledEnd),
        persistedAddonCodes: [...(config.persistedAddonCodes || [])],
        persistedVoucherDuration: Number(config.persistedVoucherDuration || 0),
        persistedVoucherName: config.persistedVoucherName || '',
        availableStaffIds: [],
        loadingTherapists: false,
        therapistError: '',
        therapistRequest: 0,
        therapistAbort: null,

        get scheduledStart() { return this.selectedSlot; },
        set scheduledStart(value) { this.selectedSlot = canonicalStart(value); this.selectedDate = this.selectedSlot.slice(0, 10); },
        get scheduleDate() { return this.selectedSlot.slice(0, 10) || this.selectedDate; },
        set scheduleDate(value) { this.selectedDate = value; this.selectedSlot = value ? `${value}T${this.scheduleTime || '13:00'}` : ''; },
        get scheduleTime() { return this.selectedSlot.slice(11, 16); },
        set scheduleTime(value) { this.selectedSlot = this.scheduleDate && value ? `${this.scheduleDate}T${value}` : ''; },
        get voucherDurationMinutes() { return this.persistedVoucherDuration; },
        get treatmentReady() { return Boolean((this.customerId || this.customerName.trim()) && this.selectedService); },
        get customerLabel() { return this.customerName.trim() || this.customerNames[this.customerId] || 'Not selected'; },
        customerEdited() {
            if (this.customerId) this.customerPhone = '';
            this.customerId = '';
            this.customerCode = '';
            this.customerResults = [];
            this.customerActiveIndex = -1;
            this.customerSearchError = '';
            this.customerRequest++;
            this.customerAbort?.abort();
            this.customerSearchLoading = false;
            clearTimeout(this.customerSearchTimer);
            this.customerSearchOpen = this.customerName.trim().length >= 2;
            this.customerSearchLoading = this.customerSearchOpen;
            if (this.customerSearchOpen) this.customerSearchTimer = setTimeout(() => this.searchCustomers(), 250);
        },
        async searchCustomers() {
            const query = this.customerName.trim();
            if (!this.customerLookupUrl || query.length < 2 || this.customerId || this.scheduleLocked) return;
            const request = ++this.customerRequest;
            this.customerAbort?.abort();
            this.customerAbort = new AbortController();
            this.customerSearchLoading = true;
            this.customerSearchError = '';
            try {
                const response = await get(this.customerLookupUrl, { params: { q: query }, signal: this.customerAbort.signal });
                if (request !== this.customerRequest || this.destroyed) return;
                this.customerResults = response.data.customers || [];
                this.customerActiveIndex = -1;
            } catch {
                if (request !== this.customerRequest || this.destroyed) return;
                this.customerSearchError = 'Customer search could not be loaded. You can still book a new guest.';
            } finally {
                if (request === this.customerRequest) this.customerSearchLoading = false;
            }
        },
        selectCustomer(customer) {
            clearTimeout(this.customerSearchTimer);
            this.customerRequest++;
            this.customerAbort?.abort();
            this.customerId = String(customer.id);
            this.customerName = customer.name;
            this.customerPhone = customer.phone || '';
            this.customerCode = customer.customer_code;
            this.customerIsGuest = customer.is_guest;
            this.customerSearchLoading = false;
            this.customerSearchOpen = false;
            this.customerActiveIndex = -1;
        },
        customerKeydown(event) {
            if (event.key === 'Escape' && this.customerSearchOpen) {
                event.preventDefault(); event.stopPropagation(); this.customerSearchOpen = false; return;
            }
            if (['ArrowDown', 'ArrowUp'].includes(event.key) && this.customerResults.length) {
                event.preventDefault();
                this.customerSearchOpen = true;
                const offset = event.key === 'ArrowDown' ? 1 : -1;
                this.customerActiveIndex = this.customerActiveIndex < 0 ? (offset > 0 ? 0 : this.customerResults.length - 1) : (this.customerActiveIndex + offset + this.customerResults.length) % this.customerResults.length;
                this.$nextTick?.(() => (this.$root || this.$el)?.querySelector(`#booking-customer-option-${this.customerActiveIndex}`)?.scrollIntoView({ block: 'nearest' }));
            } else if (event.key === 'Enter' && this.customerSearchOpen && this.customerActiveIndex >= 0) {
                event.preventDefault(); this.selectCustomer(this.customerResults[this.customerActiveIndex]);
            } else if (event.key === 'Tab') this.customerSearchOpen = false;
        },
        get canUsePersistedSchedule() {
            return Boolean(this.appointmentId && this.selectedSlot)
                && this.serviceId === this.persistedServiceId && this.selectedSlot === this.persistedScheduledStart
                && this.staffId === this.persistedStaffId && sameCodes(this.addonCodes, this.persistedAddonCodes);
        },
        get scheduleReady() {
            return this.canUsePersistedSchedule || Boolean(this.verifiedSlot && !this.loadingTherapists && !this.therapistError
                && this.availableStaffIds.includes(this.staffId));
        },
        get assignedStaffName() { return this.staffNames[this.staffId] || 'Not assigned'; },
        get assignmentDisabled() { return !this.canUsePersistedSchedule && (!this.selectedSlot || this.loading || this.loadingTherapists || Boolean(this.therapistError)); },
        availabilityParams() { return { service_id: this.serviceId, month: this.month, addon_codes: [...this.addonCodes], appointment_id: this.appointmentId || null }; },
        availabilityLoaded() { if (this.selectedSlot) this.refreshTherapists(); },
        serviceChanged() { this.furthestStep = Math.min(this.furthestStep, 2); this.invalidateTherapists(); return this.fetchAvailability(); },
        addonChanged() { return this.serviceChanged(); },
        applyCalendarSelection(selection) {
            if (!selection?.startsAt) return;
            this.scheduledStart = selection.startsAt;
            this.month = this.selectedDate.slice(0, 7);
            this.staffId = String(selection.staffId || '');
            return this.fetchAvailability();
        },
        slotChanged() { this.invalidateTherapists(); if (this.selectedSlot) this.refreshTherapists(); },
        invalidateTherapists() {
            this.therapistRequest++;
            this.therapistAbort?.abort();
            this.availableStaffIds = [];
            this.loadingTherapists = false;
            this.therapistError = '';
        },
        async refreshTherapists() {
            this.invalidateTherapists();
            if (!this.serviceId || !this.selectedSlot || this.scheduleLocked) return;
            const request = this.therapistRequest;
            this.therapistAbort = new AbortController();
            this.loadingTherapists = true;
            try {
                const response = await get(this.availableUrl, { params: {
                    service_id: this.serviceId, starts_at: this.selectedSlot, addon_codes: [...this.addonCodes], appointment_id: this.appointmentId || null,
                }, signal: this.therapistAbort.signal });
                if (request !== this.therapistRequest || this.destroyed) return;
                this.availableStaffIds = (response.data.therapists || []).map((staff) => String(staff.id));
                if (!this.availableStaffIds.includes(this.staffId) && !this.canPreservePersistedStaff(this.staffId)) this.staffId = '';
            } catch {
                if (request !== this.therapistRequest || this.destroyed) return;
                this.therapistError = 'Available therapists could not be checked. Please try again.';
            } finally {
                if (request === this.therapistRequest) this.loadingTherapists = false;
            }
        },
        staffIsAvailable(id) { return this.availableStaffIds.includes(String(id)) || this.canPreservePersistedStaff(id); },
        canPreservePersistedStaff(id) { return this.canUsePersistedSchedule && String(id) === this.persistedStaffId; },
    });
}

export function createCustomerBooking(config, get = (url, options) => window.axios.get(url, options)) {
    return extend(bookingBase(config, get), {
        availabilityUrl: config.availabilityUrl,
        staffOptions: config.staffOptions || [],
        vouchers: config.vouchers || [],
        staffId: String(config.initialStaffId || ''),
        voucherId: String(config.initialVoucherId || ''),
        scheduleLocked: false,
        get treatmentReady() { return Boolean(this.selectedService); },
        get scheduleReady() { return this.verifiedSlot; },
        get eligibleStaff() { return this.staffOptions.filter((staff) => staff.service_ids.map(String).includes(this.serviceId)); },
        get selectedStaff() { return this.staffOptions.find((staff) => String(staff.id) === this.staffId) || null; },
        get selectedVoucher() { return this.vouchers.find((voucher) => String(voucher.id) === this.voucherId) || null; },
        get voucherDurationMinutes() { return Number(this.selectedVoucher?.duration_minutes || 0); },
        availabilityParams() { return { service_id: this.serviceId, month: this.month, preferred_staff_profile_id: this.staffId || null, promotion_suggestion_id: this.voucherId || null, addon_codes: [...this.addonCodes] }; },
        serviceChanged() {
            if (this.staffId && !this.eligibleStaff.some((staff) => String(staff.id) === this.staffId)) this.staffId = '';
            this.clearSlot();
            this.furthestStep = Math.min(this.furthestStep, 2);
            return this.fetchAvailability();
        },
        selectService(id) { this.serviceId = String(id); return this.serviceChanged(); },
        staffChanged() { this.clearSlot(); return this.fetchAvailability(); },
        addonChanged() { this.furthestStep = Math.min(this.furthestStep, 2); return this.fetchAvailability(); },
        voucherChanged() {
            if (this.selectedVoucher?.addon_code) this.addonCodes = this.addonCodes.filter((code) => code !== this.selectedVoucher.addon_code);
            return this.addonChanged();
        },
    });
}
