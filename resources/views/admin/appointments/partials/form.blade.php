@php
    $fixedStatus = $fixedStatus ?? null;
    $storedStart = $appointment->scheduled_start_at ?? $appointment->requested_start_at;
    $scheduleLocked = $appointment->exists && ($appointment->status !== \App\Models\Appointment::STATUS_CONFIRMED || $storedStart?->lte(now('Asia/Manila')));
    $initialScheduledStart = $scheduleLocked ? $storedStart?->format('Y-m-d\TH:i') : old('scheduled_start_at', ($appointment->exists || request()->filled('scheduled_start_at')) ? $storedStart?->format('Y-m-d\TH:i') : null);
    $initialMonth = substr($initialScheduledStart ?: optional($appointment->scheduled_start_at)->format('Y-m-d\TH:i') ?: now('Asia/Manila')->addDay()->format('Y-m-d'), 0, 7);
    $staffNames = $staffProfiles->mapWithKeys(fn ($staff) => [(string) $staff->id => $staff->user?->name])->all();
    $initialCustomerId = (string) ($scheduleLocked ? $appointment->customer_profile_id : old('customer_profile_id', $appointment->customer_profile_id));
    $selectedCustomer = $customers->firstWhere('id', $initialCustomerId) ?? ($initialCustomerId === (string) $appointment->customer_profile_id ? $appointment->customerProfile : null);
    $initialCustomerName = $selectedCustomer?->display_name ?? old('customer_name', '');
    $initialCustomerPhone = $selectedCustomer?->display_phone ?? old('customer_phone', '');
    $initialAddonCodes = $scheduleLocked ? $appointment->addons->pluck('addon_code')->all() : old('addon_codes', $appointment->addons->pluck('addon_code')->all());
    $availableTherapistsUrl = $availableTherapistsUrl ?? route('admin.appointments.available-therapists');
    $availabilityUrl = $availabilityUrl ?? route('admin.appointments.availability');
    $customerLookupUrl = route(request()->routeIs('reception.*') ? 'reception.customers.lookup' : 'admin.customers.lookup');
    $cancelUrl = $cancelUrl ?? route('admin.appointments.index');
    $serviceOptions = $services->map(fn ($service) => ['id' => (string) $service->id, 'name' => $service->name, 'price' => (float) $service->price, 'duration' => $service->duration_minutes])->values();
    $initialStep = $errors->hasAny(['customer_profile_id', 'customer_name', 'customer_phone', 'service_id', 'preferred_staff_profile_id', 'addon_codes', 'addon_codes.*']) ? 1 : ($errors->hasAny(['scheduled_start_at', 'requested_start_at', 'staff_profile_id']) ? 2 : ($errors->any() ? 3 : 1));
    $voucherDuration = $appointment->promotionSuggestion?->addon_code ? (int) (collect($addons)->firstWhere('code', $appointment->promotionSuggestion->addon_code)['duration_minutes'] ?? 0) : 0;
@endphp

<form method="POST" action="{{ $action }}" class="casa-appointment-form booking-wizard" data-booking-form data-booking-title="{{ $appointment->exists ? __('Edit appointment') : __('Create appointment') }}" data-turbo="false"
    x-data="adminAppointmentForm({
        availabilityUrl: @js($availabilityUrl), availableUrl: @js($availableTherapistsUrl),
        appointmentId: @js($appointment->id), initialMonth: @js($initialMonth), initialStep: @js($initialStep),
        initialServiceId: @js((string) ($scheduleLocked ? $appointment->service_id : old('service_id', $appointment->service_id))), initialScheduledStart: @js($initialScheduledStart),
        initialStaffId: @js((string) ($scheduleLocked ? $appointment->staff_profile_id : old('staff_profile_id', $appointment->staff_profile_id))),
        persistedServiceId: @js($appointment->exists ? (string) $appointment->service_id : ''),
        persistedScheduledStart: @js($appointment->exists ? optional($appointment->scheduled_start_at ?? $appointment->requested_start_at)->format('Y-m-d\TH:i') : ''),
        persistedScheduledEnd: @js($appointment->exists ? optional($appointment->scheduled_end_at)->format('Y-m-d\TH:i') : ''),
        persistedStaffId: @js($appointment->exists ? (string) $appointment->staff_profile_id : ''),
        persistedAddonCodes: @js($appointment->addons->pluck('addon_code')->all()), persistedVoucherDuration: @js($voucherDuration), persistedVoucherName: @js($appointment->promotionSuggestion?->addonName()),
        staffNames: @js($staffNames), services: @js($serviceOptions), customerLookupUrl: @js($customerLookupUrl),
        initialCustomerId: @js($initialCustomerId), initialCustomerName: @js($initialCustomerName), initialCustomerPhone: @js($initialCustomerPhone),
        initialCustomerCode: @js($selectedCustomer?->customer_code), initialCustomerIsGuest: @js($selectedCustomer?->is_guest),
        initialStatus: @js($fixedStatus ?? old('status', $appointment->status)), scheduleLocked: @js($scheduleLocked),
        addonOptions: @js($addons), initialAddonCodes: @js($initialAddonCodes)
    })" x-bind:novalidate="true" x-on:submit="submitBooking($event)" x-on:calendar-booking-selected.window="applyCalendarSelection($event.detail)">
    @csrf
    <input type="hidden" name="_booking_addons_present" value="1">
    @if ($method !== 'POST') @method($method) @endif
    <input type="hidden" name="scheduled_start_at" value="{{ $initialScheduledStart }}" x-bind:value="scheduledStart">
    @if ($scheduleLocked)
        @foreach (['customer_profile_id', 'service_id', 'staff_profile_id', 'preferred_staff_profile_id'] as $field)<input type="hidden" name="{{ $field }}" value="{{ $appointment->$field }}">@endforeach
        @foreach ($appointment->addons as $addon)<input type="hidden" name="addon_codes[]" value="{{ $addon->addon_code }}">@endforeach
    @endif

    <x-booking-stepper />
    <div class="booking-wizard-body">
        <p class="booking-step-error" x-show="stepError" x-cloak role="alert" x-text="stepError"></p>
        <section data-booking-step="1" x-show="step === 1">
            <div class="booking-step-heading"><h2 data-step-heading tabindex="-1">{{ __('Treatment details') }}</h2><p>{{ __('Choose the customer and care for this visit.') }}</p></div>
            @if ($scheduleLocked)<p class="booking-info">{{ __('This appointment is historical. Booking details are read-only; notes can be updated in the final step.') }}</p>@endif
            <fieldset @disabled($scheduleLocked) class="booking-fields">
                <x-booking-customer />
                <div class="booking-field-row">
                    <div><x-input-label for="service_id" :value="__('Service')" /><select id="service_id" name="service_id" class="casa-input mt-2" required x-model="serviceId" x-on:change="serviceChanged()"><option value="">{{ __('Select service') }}</option>@foreach ($services as $service)<option value="{{ $service->id }}" @selected((int) old('service_id', $appointment->service_id) === $service->id)>{{ $service->name }} · {{ $service->duration_minutes }} min</option>@endforeach</select><x-input-error class="mt-2" :messages="$errors->get('service_id')" /></div>
                <div class="booking-preference"><x-input-label for="preferred_staff_profile_id" :value="__('Customer therapist preference (optional)')" /><select id="preferred_staff_profile_id" name="preferred_staff_profile_id" class="casa-input mt-2"><option value="">{{ __('No preference') }}</option>@foreach ($staffProfiles as $staff)<option value="{{ $staff->id }}" @selected((int) old('preferred_staff_profile_id', $appointment->preferred_staff_profile_id) === $staff->id)>{{ $staff->user->name }}</option>@endforeach</select><p class="mt-2 text-sm text-casa-muted">{{ __('A preference does not override eligibility or conflicts.') }}</p><x-input-error class="mt-2" :messages="$errors->get('preferred_staff_profile_id')" /></div>
                </div>
                <x-booking-addons :addons="$addons" :selected-codes="$initialAddonCodes" />
            </fieldset>
        </section>

        <section data-booking-step="2" x-show="step === 2">
            <div class="booking-step-heading"><h2 data-step-heading tabindex="-1">{{ __('Date and time') }}</h2><p>{{ __('Choose an open date, start time, and available therapist.') }}</p></div>
            @if ($appointment->exists)
                <div class="booking-info" x-show="canUsePersistedSchedule"><strong>{{ __('Current schedule') }}</strong><span x-text="summaryDate + ' · ' + summaryTime + '–' + summaryEndTime + ' · ' + assignedStaffName"></span><span>{{ __('You can keep this schedule for notes or permitted status changes.') }}</span></div>
            @endif
            @unless ($scheduleLocked)<x-booking-calendar :show-staff-count="true"><x-slot:assignment>
            <fieldset class="booking-therapist-field">
                <x-input-label for="staff_profile_id" :value="__('Assigned therapist')" />
                <select id="staff_profile_id" name="staff_profile_id" class="casa-input mt-2" x-model="staffId" x-bind:disabled="assignmentDisabled"><option value="">{{ __('Select assigned therapist') }}</option>@foreach ($staffProfiles as $staff)<option value="{{ $staff->id }}" @selected((int) old('staff_profile_id', $appointment->staff_profile_id) === $staff->id) x-bind:disabled="!staffIsAvailable('{{ $staff->id }}')">{{ $staff->user->name }}</option>@endforeach</select>
                <p class="mt-2 text-sm text-casa-muted" x-show="loadingTherapists">{{ __('Checking therapist availability…') }}</p>
                <div class="booking-calendar-error" x-show="therapistError" x-cloak role="alert"><p x-text="therapistError"></p><button type="button" class="booking-text-button" x-on:click="refreshTherapists()">{{ __('Retry') }}</button></div>
                <x-input-error class="mt-2" :messages="$errors->get('staff_profile_id')" />
            </fieldset>
            </x-slot:assignment></x-booking-calendar>@endunless
            <x-input-error class="mt-3" :messages="$errors->get('scheduled_start_at')" /><x-input-error class="mt-3" :messages="$errors->get('requested_start_at')" />
            @unless ($scheduleLocked)<noscript><div class="mt-4"><x-input-label for="appointment_date" :value="__('Appointment date and time')" /><input id="appointment_date" name="scheduled_start_at" type="datetime-local" class="casa-input mt-2" value="{{ $initialScheduledStart ?: optional($appointment->scheduled_start_at)->format('Y-m-d\TH:i') }}" required><p class="mt-2 text-sm text-casa-muted">{{ __('The server will verify this time when saved.') }}</p></div></noscript>@endunless
        </section>

        <section data-booking-step="3" x-show="step === 3">
            <div class="booking-step-heading"><h2 data-step-heading tabindex="-1">{{ __('Review and save') }}</h2><p>{{ __('Check the booking and add any notes before saving.') }}</p></div>
            <div class="booking-review-layout">
                <x-booking-review :operations="true" />
                <div class="booking-fields">
                    @if ($fixedStatus)<input type="hidden" name="status" value="{{ $fixedStatus }}">@else<div><x-input-label for="status" :value="__('Status')" /><select id="status" name="status" class="casa-input mt-2" x-model="status">@foreach ($appointment->allowedTargetStatuses() as $option)<option value="{{ $option }}" @selected(old('status', $appointment->status) === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>@endforeach</select><x-input-error class="mt-2" :messages="$errors->get('status')" /></div>@endif
                    <div><x-input-label for="customer_notes" :value="__('Customer notes (optional)')" /><textarea id="customer_notes" name="customer_notes" rows="3" maxlength="5000" class="casa-input mt-2" placeholder="{{ __('Special requests for this visit') }}">{{ old('customer_notes', $appointment->customer_notes) }}</textarea><x-input-error class="mt-2" :messages="$errors->get('customer_notes')" /></div>
                    <div><x-input-label for="internal_notes" :value="__('Internal notes (optional)')" /><textarea id="internal_notes" name="internal_notes" rows="3" maxlength="5000" class="casa-input mt-2" placeholder="{{ __('Visible only to the spa team') }}">{{ old('internal_notes', $appointment->internal_notes) }}</textarea><x-input-error class="mt-2" :messages="$errors->get('internal_notes')" /></div>
                </div>
            </div>
        </section>
    </div>
    <x-booking-footer :submit-label="$submitLabel" :cancel-url="$cancelUrl" />
</form>
