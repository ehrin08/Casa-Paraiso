@php
    $initialRequestedAt = old('requested_start_at', $initialRequestedAt ?? null);
    $initialMonth = $initialRequestedAt
        ? \Illuminate\Support\Carbon::parse($initialRequestedAt)->format('Y-m')
        : now()->addDay()->format('Y-m');
    $bookingServices = $services->map(fn ($service) => [
        'id' => $service->id,
        'name' => $service->name,
        'duration' => $service->duration_minutes,
        'price' => number_format((float) $service->price, 2),
    ])->values();
    $bookingStaff = $staffProfiles->map(fn ($staff) => [
        'id' => $staff->id,
        'name' => $staff->user->name,
        'specialization' => $staff->specialization ?: __('Spa therapist'),
        'service_ids' => $staff->services->pluck('id')->values()->all(),
    ])->values();
    $bookingVouchers = $vouchers->map(fn ($voucher) => [
        'id' => $voucher->id,
        'addon_name' => $voucher->addonName(),
        'addon_code' => $voucher->addon_code,
        'offer' => $voucher->suggested_offer,
        'expires_at' => $voucher->expires_at?->toIso8601String(),
    ])->values();
    $bookingAddons = $addons->map(fn ($addon) => [
        'code' => is_array($addon) ? $addon['code'] : $addon->code,
        'name' => is_array($addon) ? $addon['name'] : $addon->name,
        'price' => (float) (is_array($addon) ? $addon['price'] : $addon->price),
        'duration_minutes' => (int) (is_array($addon) ? ($addon['duration_minutes'] ?? 0) : $addon->duration_minutes),
    ])->values();
    $bookingContext = $bookingContext ?? null;
@endphp

<form
    method="POST"
    action="{{ route('customer.appointments.store') }}"
    class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_21rem]"
    data-booking-form
    x-data="customerCalendarBooking({
        availabilityUrl: @js(route('customer.appointments.availability')),
        services: @js($bookingServices),
        staffOptions: @js($bookingStaff),
        vouchers: @js($bookingVouchers),
        addonOptions: @js($bookingAddons),
        initialMonth: @js($initialMonth),
        initialServiceId: @js((string) old('service_id', $preselectedServiceId ?? '')),
        initialStaffId: @js((string) old('preferred_staff_profile_id', '')),
        initialVoucherId: @js((string) old('promotion_suggestion_id', '')),
        initialAddonCodes: @js(old('addon_codes', [])),
        initialSlot: @js($initialRequestedAt),
        slotPreviewLimit: 2
    })"
    x-init="init()"
    x-on:booking-date-selected.window="preselectDate($event.detail.date)"
>
    @csrf
    @if ($bookingContext)<input type="hidden" name="_booking_context" value="{{ $bookingContext }}">@endif
    <input type="hidden" name="service_id" x-bind:value="serviceId">
    <input type="hidden" name="requested_start_at" x-bind:value="selectedSlot">

    <div class="space-y-5">
        <section class="casa-editorial-card p-5 sm:p-7">
            <div class="flex items-start gap-4 border-b border-casa-border pb-5">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-casa-cacao text-sm font-extrabold text-white">1</span>
                <div>
                    <p class="text-[0.68rem] font-extrabold uppercase tracking-[0.14em] text-casa-cacao">{{ __('Choose your care') }}</p>
                    <h2 class="mt-1 font-editorial text-3xl font-semibold text-casa-text">{{ __('Treatment and therapist') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-casa-muted">{{ __('Choose a treatment first. A therapist preference is optional and filters the calendar to that person’s open times.') }}</p>
                </div>
            </div>

            <fieldset class="mt-6">
                <legend class="casa-label">{{ __('Choose a treatment') }}</legend>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($services as $service)
                        <button
                            type="button"
                            class="rounded-2xl border p-4 text-left transition sm:p-5"
                            x-on:click="selectService({{ $service->id }})"
                            x-bind:aria-pressed="String(serviceId) === '{{ $service->id }}'"
                            x-bind:class="String(serviceId) === '{{ $service->id }}'
                                ? 'border-casa-palm bg-casa-palm text-white shadow-md'
                                : 'border-casa-border bg-casa-sand/40 text-casa-text hover:border-casa-brass hover:bg-casa-paper'"
                        >
                            <span class="block text-[0.65rem] font-extrabold uppercase tracking-[0.1em]" x-bind:class="String(serviceId) === '{{ $service->id }}' ? 'text-white/70' : 'text-casa-cacao'">
                                {{ $service->duration_minutes }} {{ __('minutes') }} · PHP {{ number_format((float) $service->price, 2) }}
                            </span>
                            <strong class="mt-2 block text-base font-extrabold">{{ $service->name }}</strong>
                            <span class="mt-2 block text-xs leading-5" x-bind:class="String(serviceId) === '{{ $service->id }}' ? 'text-white/75' : 'text-casa-muted'">{{ $service->description }}</span>
                        </button>
                    @endforeach
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('service_id')" />
            </fieldset>

            <div class="mt-5 rounded-2xl border border-casa-border bg-casa-paper p-4 sm:p-5">
                <div class="max-w-xl">
                    <x-input-label for="preferred_staff_profile_id" :value="__('Preferred therapist (optional)')" />
                    <select id="preferred_staff_profile_id" name="preferred_staff_profile_id" class="casa-input mt-2" x-model="staffId" x-on:change="staffChanged()" x-bind:disabled="!serviceId">
                        <option value="">{{ __('No preference') }}</option>
                        <template x-for="staff in eligibleStaff" x-bind:key="staff.id">
                            <option x-bind:value="staff.id" x-text="`${staff.name} · ${staff.specialization}`"></option>
                        </template>
                    </select>
                    <p class="mt-2 text-xs leading-5 text-casa-muted" x-show="!serviceId">{{ __('Choose a treatment to see eligible therapists.') }}</p>
                    <p class="mt-2 text-xs leading-5 text-casa-muted" x-show="serviceId">{{ __('No preference gives the spa team more flexibility. A selected preference is considered but not guaranteed.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('preferred_staff_profile_id')" />
                </div>
            </div>

            <fieldset class="mt-5 rounded-2xl border border-casa-border bg-casa-sand/45 p-4 sm:p-5">
                <legend class="casa-label px-1">{{ __('Add-ons (optional)') }}</legend>
                <p class="mt-1 text-sm leading-6 text-casa-muted">{{ __('Add extra care to your visit. Back Massage extends your reserved time by 30 minutes.') }}</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($addons as $addon)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-casa-border bg-casa-paper px-4 py-3 transition hover:border-casa-brass" x-bind:class="selectedVoucher?.addon_code === '{{ $addon['code'] }}' ? 'opacity-50' : ''">
                            <input type="checkbox" name="addon_codes[]" value="{{ $addon['code'] }}" x-model="addonCodes" x-on:change="addonChanged()" x-bind:disabled="selectedVoucher?.addon_code === '{{ $addon['code'] }}'" class="mt-1 rounded border-casa-border text-casa-primary focus:ring-casa-gold">
                            <span>
                                <strong class="block text-sm text-casa-text">{{ $addon['name'] }}</strong>
                                <span class="mt-1 block text-xs leading-5 text-casa-muted">PHP {{ number_format((float) $addon['price'], 2) }}@if (($addon['duration_minutes'] ?? 0) > 0) · +{{ $addon['duration_minutes'] }} {{ __('minutes') }}@endif</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-3 text-sm font-bold text-casa-text">{{ __('Selected add-ons:') }} <span x-text="`PHP ${paidAddonTotal.toFixed(2)}`"></span><span x-show="addonDurationMinutes" x-text="` · +${addonDurationMinutes} min`"></span></p>
                <x-input-error class="mt-3" :messages="$errors->get('addon_codes')" />
            </fieldset>

            @if ($vouchers->isNotEmpty())
                <fieldset class="mt-5 rounded-2xl border border-casa-brass/60 bg-casa-brass/10 p-4 sm:p-5">
                    <legend class="casa-label px-1">{{ __('Complimentary add-on voucher (optional)') }}</legend>
                    <p class="mt-1 text-sm leading-6 text-casa-muted">{{ __('Your customer reward adds one spa add-on to this visit. It does not change the package price.') }}</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-casa-border bg-casa-paper px-4 py-3">
                                <input type="radio" name="promotion_suggestion_id" value="" x-model="voucherId" x-on:change="voucherChanged()" class="border-casa-border text-casa-primary focus:ring-casa-gold">
                            <span class="text-sm font-bold text-casa-text">{{ __('No voucher') }}</span>
                        </label>
                        @foreach ($vouchers as $voucher)
                            <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border border-casa-border bg-casa-paper px-4 py-3 transition hover:border-casa-brass">
                                <input type="radio" name="promotion_suggestion_id" value="{{ $voucher->id }}" x-model="voucherId" x-on:change="voucherChanged()" class="mt-1 border-casa-border text-casa-primary focus:ring-casa-gold">
                                <span>
                                    <strong class="block text-sm text-casa-text">{{ $voucher->addonName() }}</strong>
                                    <span class="mt-1 block text-xs leading-5 text-casa-muted">
                                        {{ __('Complimentary customer reward') }}
                                        @if ($voucher->expires_at)
                                            {{ __('· Expires :date', ['date' => $voucher->expires_at->format('M d, Y')]) }}
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error class="mt-3" :messages="$errors->get('promotion_suggestion_id')" />
                </fieldset>
            @endif

            <noscript>
                <div class="mt-5">
                    <x-input-label for="service_id_fallback" :value="__('Service')" />
                    <select id="service_id_fallback" name="service_id" class="casa-input mt-2" required>
                        <option value="">{{ __('Select service') }}</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((int) old('service_id', $preselectedServiceId ?? 0) === $service->id)>{{ $service->name }}</option>
                        @endforeach
                    </select>
                </div>
            </noscript>
        </section>

        <section class="casa-editorial-card p-5 sm:p-7">
            <div class="flex items-start gap-4 border-b border-casa-border pb-5">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-casa-palm text-sm font-extrabold text-white">2</span>
                <div>
                    <p class="text-[0.68rem] font-extrabold uppercase tracking-[0.14em] text-casa-palm">{{ __('Choose your time') }}</p>
                    <h2 class="mt-1 font-editorial text-3xl font-semibold text-casa-text">{{ __('Available calendar') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-casa-muted">{{ __('Highlighted dates have at least one open 30-minute start time within 1:00 PM to 12:00 midnight.') }}</p>
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-casa-border bg-casa-sand/45 p-3 sm:p-5">
                <div class="flex items-center justify-between gap-4">
                    <button type="button" class="casa-icon-button" x-on:click="previousMonth()" aria-label="{{ __('Previous month') }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m15 18-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <h3 class="font-editorial text-2xl font-semibold text-casa-text sm:text-3xl" x-text="monthLabel"></h3>
                    <button type="button" class="casa-icon-button" x-on:click="nextMonth()" aria-label="{{ __('Next month') }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m9 18 6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>

                <div class="mt-5 grid grid-cols-7 gap-1.5 text-center text-[0.62rem] font-extrabold uppercase tracking-[0.08em] text-casa-muted sm:gap-2 sm:text-xs">
                    <template x-for="dayName in weekDays" x-bind:key="dayName"><span x-text="dayName"></span></template>
                </div>

                <div class="mt-2 grid grid-cols-7 gap-1.5 sm:gap-2" role="grid" aria-label="{{ __('Available appointment dates') }}">
                    <template x-for="day in calendarDays" x-bind:key="day.key">
                        <button
                            type="button"
                            class="relative min-h-12 rounded-xl border px-1.5 py-2 text-center text-xs font-extrabold transition sm:min-h-24 sm:rounded-2xl sm:px-2 sm:py-3 sm:text-left sm:text-sm"
                            x-bind:class="day.date === selectedDate
                                ? 'border-casa-palm bg-casa-palm text-white shadow-md'
                                : day.available
                                    ? 'border-casa-brass bg-casa-paper text-casa-text hover:border-casa-palm'
                                    : 'border-casa-border/70 bg-casa-paper/45 text-casa-muted/40'"
                            x-bind:disabled="!day.available"
                            x-on:click="selectDate(day.date)"
                            x-on:keydown.right.prevent="moveAvailableDate(day.date, 1)"
                            x-on:keydown.left.prevent="moveAvailableDate(day.date, -1)"
                            x-on:keydown.down.prevent="moveAvailableDate(day.date, 7)"
                            x-on:keydown.up.prevent="moveAvailableDate(day.date, -7)"
                            x-bind:aria-label="day.date ? `${day.date}${day.available ? ', available' : ', unavailable'}` : 'Blank calendar day'"
                            x-bind:tabindex="!day.available ? -1 : (selectedDate ? (selectedDate === day.date ? 0 : -1) : 0)"
                            x-bind:data-booking-calendar-day="day.date || ''"
                            role="gridcell"
                        >
                            <span class="block" x-text="day.label"></span>
                            <span class="mx-auto mt-1 block size-1.5 rounded-full bg-casa-brass sm:hidden" x-show="day.available && day.date !== selectedDate"></span>
                            <span class="mt-2 hidden flex-col gap-1 sm:flex" x-show="day.available">
                                <template x-for="slot in day.previewSlots" x-bind:key="slot.starts_at">
                                    <span class="block w-full truncate rounded-full px-1.5 py-1 text-center text-[0.6rem] font-extrabold uppercase leading-none" x-bind:class="day.date === selectedDate ? 'bg-white/15 text-white' : 'bg-casa-brass/15 text-casa-cacao'" x-text="slot.label"></span>
                                </template>
                                <span class="block truncate text-center text-[0.6rem] font-extrabold uppercase leading-none" x-bind:class="day.date === selectedDate ? 'text-white/75' : 'text-casa-muted'" x-show="day.moreSlots" x-text="moreSlotsLabel(day)"></span>
                            </span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="mt-5 rounded-2xl border border-casa-border bg-casa-paper p-4 sm:p-5" aria-live="polite">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[0.68rem] font-extrabold uppercase tracking-[0.12em] text-casa-cacao">{{ __('Available start times') }}</p>
                        <p class="mt-1 text-sm font-semibold text-casa-text" x-text="selectedDateLabel"></p>
                    </div>
                    <x-status-badge x-show="loading">{{ __('Loading') }}</x-status-badge>
                </div>

                <p class="mt-4 text-sm leading-6 text-casa-muted" x-show="!serviceId">{{ __('Choose a treatment first to load available slots.') }}</p>
                <p class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm leading-6 text-red-700" x-show="error" x-text="error" role="alert"></p>

                <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4" x-show="selectedDate && selectedDateSlots.length">
                    <template x-for="slot in selectedDateSlots" x-bind:key="slot.starts_at">
                        <button
                            type="button"
                            class="min-h-11 rounded-xl border px-3 py-2 text-xs font-extrabold uppercase tracking-[0.055em] transition sm:text-sm"
                            x-bind:class="selectedSlot === slot.starts_at ? 'border-casa-palm bg-casa-palm text-white shadow-md' : 'border-casa-border bg-casa-sand/45 text-casa-cacao hover:border-casa-brass'"
                            x-on:click="chooseSlot(slot)"
                            x-bind:aria-pressed="selectedSlot === slot.starts_at"
                        >
                            <span x-text="slot.label"></span>
                        </button>
                    </template>
                </div>

                <p class="mt-4 text-sm leading-6 text-casa-muted" x-show="serviceId && selectedDate && !selectedDateSlots.length && !loading">{{ __('No available times remain for this date. Choose another highlighted day.') }}</p>
                <x-input-error class="mt-3" :messages="$errors->get('requested_start_at')" />
            </div>

            <noscript>
                <div class="mt-5">
                    <x-input-label for="requested_start_at_fallback" :value="__('Preferred date and time')" />
                    <x-text-input id="requested_start_at_fallback" name="requested_start_at" type="datetime-local" class="mt-2" :value="old('requested_start_at', $initialRequestedAt ?: now()->addDay()->setTime(14, 0)->format('Y-m-d\TH:i'))" required />
                    <p class="mt-2 text-xs leading-5 text-casa-muted">{{ __('JavaScript is off, so the server will verify this time when you book.') }}</p>
                </div>
            </noscript>
        </section>

        <section class="casa-editorial-card p-5 sm:p-7">
            <div class="flex items-start gap-4 border-b border-casa-border pb-5">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-casa-brass text-sm font-extrabold text-casa-charcoal">3</span>
                <div>
                    <p class="text-[0.68rem] font-extrabold uppercase tracking-[0.14em] text-casa-cacao">{{ __('Prepare your visit') }}</p>
                    <h2 class="mt-1 font-editorial text-3xl font-semibold text-casa-text">{{ __('Anything we should know?') }}</h2>
                </div>
            </div>
            <div class="mt-6">
                <x-input-label for="customer_notes" :value="__('Notes for the spa team (optional)')" />
                <textarea id="customer_notes" name="customer_notes" rows="5" class="casa-input mt-2" placeholder="{{ __('Share preferences or details that may help us prepare for your visit.') }}">{{ old('customer_notes') }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('customer_notes')" />
            </div>
        </section>
    </div>

    <aside class="space-y-4 xl:sticky xl:top-6 xl:self-start">
        <div class="casa-dark-panel rounded-[24px] p-6 shadow-casa-card">
            <p class="text-[0.68rem] font-extrabold uppercase tracking-[0.14em] text-casa-brass-light">{{ __('Your booking') }}</p>
            <h2 class="mt-3 font-editorial text-3xl font-semibold text-white">{{ __('Your reserved moment of care.') }}</h2>
            <p class="mt-4 text-sm leading-7 text-white/65">{{ __('The system rechecks therapist availability and confirms your reserved schedule when you book.') }}</p>
            <div class="casa-divider my-6"></div>
            <dl class="space-y-4">
                <div>
                    <dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.12em] text-white/60">{{ __('Treatment') }}</dt>
                    <dd class="mt-1.5 text-sm font-bold text-white" x-text="selectedService ? `${selectedService.name} · ${selectedService.duration} min` : '{{ __('No treatment selected') }}'"></dd>
                </div>
                <div>
                    <dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.12em] text-white/60">{{ __('Therapist preference') }}</dt>
                    <dd class="mt-1.5 text-sm font-bold text-white" x-text="selectedStaff?.name || '{{ __('No preference') }}'"></dd>
                </div>
                <div>
                    <dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.12em] text-white/60">{{ __('Complimentary add-on') }}</dt>
                    <dd class="mt-1.5 text-sm font-bold text-white" x-text="selectedVoucher?.addon_name || '{{ __('No voucher selected') }}'"></dd>
                </div>
                <div>
                    <dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.12em] text-white/60">{{ __('Paid add-ons') }}</dt>
                    <dd class="mt-1.5 text-sm font-bold text-white" x-text="selectedPaidAddons.length ? selectedPaidAddons.map((addon) => addon.name).join(', ') : '{{ __('No paid add-ons') }}'"></dd>
                    <p class="mt-1 text-xs text-white/65" x-show="selectedPaidAddons.length" x-text="`PHP ${paidAddonTotal.toFixed(2)}${addonDurationMinutes ? ` · +${addonDurationMinutes} min` : ''}`"></p>
                </div>
                <div>
                    <dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.12em] text-white/60">{{ __('Selected time') }}</dt>
                    <dd class="mt-1.5 text-sm font-bold text-white" x-text="selectedSlotLabel || '{{ __('No time selected') }}'"></dd>
                </div>
                <div>
                    <dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.12em] text-white/60">{{ __('Booking status') }}</dt>
                    <dd class="mt-1.5 text-sm font-bold text-white">{{ __('Confirmed when booked') }}</dd>
                </div>
            </dl>
        </div>

        <x-app-card>
            <button type="submit" class="casa-button-primary w-full" x-bind:disabled="!serviceId || !selectedSlot">{{ __('Confirm booking') }}</button>
            <a href="{{ route('customer.appointments.index') }}" class="casa-button-secondary mt-3 w-full">{{ __('Cancel') }}</a>
            <p class="mt-4 text-center text-xs leading-5 text-casa-muted">{{ __('A successful booking immediately reserves the assigned therapist and time.') }}</p>
        </x-app-card>

        <x-app-card>
            <p class="text-[0.68rem] font-extrabold uppercase tracking-[0.12em] text-casa-cacao">{{ __('Visit hours') }}</p>
            <p class="mt-2 text-sm font-bold text-casa-text">{{ __('Open every day') }}</p>
            <p class="mt-1 text-sm text-casa-muted">{{ __('1:00 PM to 12:00 midnight') }}</p>
        </x-app-card>
    </aside>
</form>
