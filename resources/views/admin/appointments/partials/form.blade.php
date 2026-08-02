@php
    $modalName = $modalName ?? null;
    $fixedStatus = $fixedStatus ?? null;
    $initialScheduledStart = old('scheduled_start_at', optional($appointment->scheduled_start_at ?? $appointment->requested_start_at)->format('Y-m-d\TH:i'));
    $staffNames = $staffProfiles->mapWithKeys(fn ($staff) => [(string) $staff->id => $staff->user?->name])->all();
    $initialAddonCodes = old('addon_codes', $appointment->addons->pluck('addon_code')->all());
    $availableTherapistsUrl = $availableTherapistsUrl ?? route('admin.appointments.available-therapists');
    $cancelUrl = $cancelUrl ?? route('admin.appointments.index');
@endphp

<form
    method="POST"
    action="{{ $action }}"
    @class(['grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]', 'casa-modal-form' => $modalName])
    x-data="adminAppointmentForm({
        availableUrl: @js($availableTherapistsUrl),
        appointmentId: @js($appointment->id),
        initialServiceId: @js((string) old('service_id', $appointment->service_id)),
        initialScheduledStart: @js($initialScheduledStart),
        initialStaffId: @js((string) old('staff_profile_id', $appointment->staff_profile_id)),
        persistedServiceId: @js($appointment->exists ? (string) $appointment->service_id : ''),
        persistedScheduledStart: @js($appointment->exists ? optional($appointment->scheduled_start_at)->format('Y-m-d\TH:i') : ''),
        persistedStaffId: @js($appointment->exists ? (string) $appointment->staff_profile_id : ''),
        staffNames: @js($staffNames),
        addonOptions: @js($addons),
        initialAddonCodes: @js($initialAddonCodes)
    })"
    x-init="init()"
    x-on:calendar-booking-selected.window="applyCalendarSelection($event.detail)"
>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    @if ($modalName)<input type="hidden" name="_modal" value="{{ $modalName }}">@endif

    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Appointment details') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Booking information') }}</h2>
        </div>

        <div class="mt-5 grid gap-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="customer_profile_id" :value="__('Customer')" />
                    <select id="customer_profile_id" name="customer_profile_id" class="casa-input mt-2" required>
                        <option value="">{{ __('Select customer') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('customer_profile_id', $appointment->customer_profile_id) === $customer->id)>
                                {{ $customer->user->name }} ({{ $customer->customer_code }})
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('customer_profile_id')" />
                </div>

                <div>
                    <x-input-label for="service_id" :value="__('Service')" />
                    <select id="service_id" name="service_id" class="casa-input mt-2" required x-model="serviceId" x-on:change="refreshTherapists()">
                        <option value="">{{ __('Select service') }}</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}">{{ $service->name }} · {{ $service->duration_minutes }} min</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('service_id')" />
                </div>
            </div>

            <fieldset class="rounded-2xl border border-casa-border bg-casa-sand/45 p-4">
                <legend class="casa-label px-1">{{ __('Paid add-ons') }}</legend>
                <p class="mt-1 text-sm leading-6 text-casa-muted">{{ __('Select any add-ons needed for this visit. Back Massage extends the appointment by 30 minutes.') }}</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($addons as $addon)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-casa-border bg-casa-paper px-4 py-3">
                            <input type="checkbox" name="addon_codes[]" value="{{ $addon['code'] }}" x-model="addonCodes" x-on:change="addonChanged()" class="mt-1 rounded border-casa-border text-casa-primary focus:ring-casa-gold">
                            <span><strong class="block text-sm text-casa-text">{{ $addon['name'] }}</strong><span class="mt-1 block text-xs text-casa-muted">PHP {{ number_format((float) $addon['price'], 2) }}@if (($addon['duration_minutes'] ?? 0) > 0) · +{{ $addon['duration_minutes'] }} {{ __('minutes') }}@endif</span></span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-3 text-sm font-bold text-casa-text">{{ __('Selected add-ons:') }} <span x-text="`PHP ${paidAddonTotal.toFixed(2)}`"></span><span x-show="addonDurationMinutes" x-text="` · +${addonDurationMinutes} min`"></span></p>
                <x-input-error class="mt-3" :messages="$errors->get('addon_codes')" />
            </fieldset>

            <div>
                <x-input-label for="scheduled_start_at" :value="__('Appointment time')" />
                <x-text-input id="scheduled_start_at" name="scheduled_start_at" type="datetime-local" class="mt-2" :value="$initialScheduledStart" x-model="scheduledStart" x-on:change="refreshTherapists()" required />
                <x-input-error class="mt-2" :messages="$errors->get('scheduled_start_at')" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="preferred_staff_profile_id" :value="__('Customer therapist preference')" />
                    <select id="preferred_staff_profile_id" name="preferred_staff_profile_id" class="casa-input mt-2">
                        <option value="">{{ __('No preference') }}</option>
                        @foreach ($staffProfiles as $staffProfile)
                            <option value="{{ $staffProfile->id }}" @selected((int) old('preferred_staff_profile_id', $appointment->preferred_staff_profile_id) === $staffProfile->id)>{{ $staffProfile->user->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs leading-5 text-casa-muted">{{ __('A preference is visible during confirmation but does not override eligibility or conflicts.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('preferred_staff_profile_id')" />
                </div>

                <div>
                    <x-input-label for="staff_profile_id" :value="__('Assigned therapist')" />
                    <select id="staff_profile_id" name="staff_profile_id" class="casa-input mt-2" x-model="staffId" @required($fixedStatus)>
                        <option value="">{{ __('Select assigned therapist') }}</option>
                        @foreach ($staffProfiles as $staffProfile)
                            <option value="{{ $staffProfile->id }}" x-bind:disabled="!staffIsAvailable('{{ $staffProfile->id }}')">{{ $staffProfile->user->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs leading-5 text-casa-muted" x-show="loadingTherapists">{{ __('Checking therapist availability…') }}</p>
                    <p class="mt-2 text-xs leading-5 text-red-700" x-show="therapistError" x-text="therapistError"></p>
                    <x-input-error class="mt-2" :messages="$errors->get('staff_profile_id')" />
                </div>
            </div>

            @if ($fixedStatus)
                <input type="hidden" name="status" value="{{ $fixedStatus }}">
                <div class="rounded-2xl border border-casa-palm/25 bg-casa-palm/5 p-4">
                    <p class="casa-section-label">{{ __('Reservation status') }}</p>
                    <p class="mt-2 font-bold text-casa-palm">{{ __('Confirmed reservation') }}</p>
                    <p class="mt-1 text-xs leading-5 text-casa-muted">{{ __('Saving reserves the assigned therapist and time immediately.') }}</p>
                </div>
            @else
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <select id="status" name="status" class="casa-input mt-2">
                        @foreach ($appointment->allowedTargetStatuses() as $option)
                            <option value="{{ $option }}" @selected(old('status', $appointment->status) === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('status')" />
                </div>
            @endif

            <div>
                <x-input-label for="customer_notes" :value="__('Customer notes')" />
                <textarea id="customer_notes" name="customer_notes" rows="4" class="casa-input mt-2">{{ old('customer_notes', $appointment->customer_notes) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('customer_notes')" />
            </div>

            <div>
                <x-input-label for="internal_notes" :value="__('Internal notes')" />
                <textarea id="internal_notes" name="internal_notes" rows="4" class="casa-input mt-2">{{ old('internal_notes', $appointment->internal_notes) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('internal_notes')" />
            </div>
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card data-modal-actions>
            <p class="casa-section-label">{{ $fixedStatus ? __('Selected calendar time') : __('Confirmation rule') }}</p>
            @if ($fixedStatus)
                <p class="mt-3 font-display text-lg font-black text-casa-text" x-text="scheduleSummary"></p>
            @endif
            <p class="mt-3 text-sm leading-6 text-casa-muted">
                {{ __('Confirmed appointments require an eligible therapist inside business and working hours. The server rechecks overlaps while locking that therapist’s schedule.') }}
            </p>
        </x-app-card>

        <x-app-card>
            <div class="flex flex-col gap-3">
                <button type="submit" class="casa-button-primary w-full">{{ $submitLabel }}</button>
                @if ($modalName)
                    <button type="button" class="casa-button-secondary w-full" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">{{ __('Cancel') }}</button>
                @else
                    <a href="{{ $cancelUrl }}" class="casa-button-secondary w-full">{{ __('Cancel') }}</a>
                @endif
            </div>
        </x-app-card>
    </aside>
</form>
