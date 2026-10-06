@php
    $initialRequestedAt = old('requested_start_at', $initialRequestedAt ?? null);
    $initialMonth = $initialRequestedAt ? substr($initialRequestedAt, 0, 7) : now('Asia/Manila')->addDay()->format('Y-m');
    $bookingServices = $services->map(fn ($service) => ['id' => $service->id, 'name' => $service->name, 'duration' => $service->duration_minutes, 'price' => (float) $service->price])->values();
    $bookingStaff = $staffProfiles->map(fn ($staff) => ['id' => $staff->id, 'name' => $staff->user->name, 'service_ids' => $staff->services->pluck('id')->values()->all()])->values();
    $bookingVouchers = $vouchers->map(fn ($voucher) => ['id' => $voucher->id, 'addon_name' => $voucher->addonName(), 'addon_code' => $voucher->addon_code, 'duration_minutes' => (int) (collect($addons)->firstWhere('code', $voucher->addon_code)['duration_minutes'] ?? 0)])->values();
    $initialStep = $errors->hasAny(['service_id', 'preferred_staff_profile_id', 'promotion_suggestion_id', 'addon_codes', 'addon_codes.*']) ? 1 : ($errors->has('requested_start_at') ? 2 : ($errors->any() ? 3 : 1));
@endphp
<form method="POST" action="{{ route('customer.appointments.store') }}" class="booking-wizard" data-booking-form data-booking-title="{{ __('Book appointment') }}" data-turbo="false"
    x-data="customerCalendarBooking({
        availabilityUrl: @js(route('customer.appointments.availability')), services: @js($bookingServices), staffOptions: @js($bookingStaff),
        vouchers: @js($bookingVouchers), addonOptions: @js($addons), initialMonth: @js($initialMonth), initialStep: @js($initialStep),
        initialServiceId: @js((string) old('service_id', $preselectedServiceId ?? '')), initialStaffId: @js((string) old('preferred_staff_profile_id', '')),
        initialVoucherId: @js((string) old('promotion_suggestion_id', '')), initialAddonCodes: @js(old('addon_codes', [])), initialSlot: @js($initialRequestedAt), slotPreviewLimit: 2
    })" x-bind:novalidate="true" x-on:submit="submitBooking($event)" x-on:booking-date-selected.window="preselectDate($event.detail.date)">
    @csrf
    @if ($bookingContext ?? null)<input type="hidden" name="_booking_context" value="{{ $bookingContext }}">@endif
    <input type="hidden" name="service_id" value="{{ old('service_id', $preselectedServiceId ?? '') }}" x-bind:value="serviceId">
    <input type="hidden" name="requested_start_at" value="{{ $initialRequestedAt }}" x-bind:value="selectedSlot">
    <x-booking-stepper />
    <div class="booking-wizard-body">
        <p class="booking-step-error" x-show="stepError" x-cloak role="alert" x-text="stepError"></p>
        <section data-booking-step="1" x-show="step === 1">
            <div class="booking-step-heading"><h2 data-step-heading tabindex="-1">{{ __('Treatment details') }}</h2><p>{{ __('Choose your treatment and any extra care for your visit.') }}</p></div>
            <fieldset><legend class="casa-label">{{ __('Choose a treatment') }}</legend><div class="booking-service-grid">
                @foreach ($services as $service)<button type="button" class="booking-service" x-on:click="selectService({{ $service->id }})" x-bind:class="{ 'is-selected': serviceId === '{{ $service->id }}' }" x-bind:aria-pressed="serviceId === '{{ $service->id }}'"><strong>{{ $service->name }}</strong><span>{{ $service->duration_minutes }} {{ __('minutes') }} · PHP {{ number_format((float) $service->price, 2) }}</span><span>{{ $service->description }}</span></button>@endforeach
            </div><x-input-error class="mt-2" :messages="$errors->get('service_id')" /></fieldset>
            <noscript><select name="service_id" class="casa-input mt-4" aria-label="{{ __('Treatment') }}" required><option value="">{{ __('Select treatment') }}</option>@foreach ($services as $service)<option value="{{ $service->id }}" @selected((int) old('service_id', $preselectedServiceId ?? null) === $service->id)>{{ $service->name }}</option>@endforeach</select></noscript>
            <div class="booking-preference mt-5"><x-input-label for="preferred_staff_profile_id" :value="__('Preferred therapist (optional)')" />
                <select id="preferred_staff_profile_id" name="preferred_staff_profile_id" class="casa-input mt-2" x-model="staffId" x-on:change="staffChanged()"><option value="">{{ __('No preference') }}</option>@foreach ($staffProfiles as $staff)<option value="{{ $staff->id }}" x-bind:disabled="!eligibleStaff.some(staff => String(staff.id) === '{{ $staff->id }}')" @selected((int) old('preferred_staff_profile_id') === $staff->id)>{{ $staff->user->name }}</option>@endforeach</select>
                <p class="mt-2 text-sm text-casa-muted">{{ __('A preference shows that therapist’s open times. Assignment is rechecked when you book.') }}</p><x-input-error class="mt-2" :messages="$errors->get('preferred_staff_profile_id')" />
            </div>
            <div class="mt-5"><x-booking-addons :addons="$addons" :customer="true" /></div>
            @if ($vouchers->isNotEmpty())<p class="mt-4 text-sm text-casa-muted">{{ __('A complimentary add-on voucher does not change the package price.') }}</p>@endif
            @if ($vouchers->isNotEmpty())
                <details class="booking-optional mt-4" @if (old('promotion_suggestion_id')) open @endif>
                    <summary>{{ __('Complimentary add-on voucher (optional)') }}</summary>
                    <div class="booking-voucher-options mt-4">
                        <label><input type="radio" name="promotion_suggestion_id" value="" x-model="voucherId" x-on:change="voucherChanged()" @checked(! old('promotion_suggestion_id'))>{{ __('No voucher') }}</label>
                        @foreach ($vouchers as $voucher)
                            <label>
                                <input type="radio" name="promotion_suggestion_id" value="{{ $voucher->id }}" x-model="voucherId" x-on:change="voucherChanged()" @checked((int) old('promotion_suggestion_id') === $voucher->id)>
                                <span><strong>{{ $voucher->addonName() }}</strong><span>{{ __('Complimentary customer reward') }}@if ($voucher->expires_at) · {{ __('Expires') }} {{ $voucher->expires_at->format('M j, Y') }}@endif</span></span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error class="mt-3" :messages="$errors->get('promotion_suggestion_id')" />
                </details>
            @endif
        </section>
        <section data-booking-step="2" x-show="step === 2">
            <div class="booking-step-heading"><h2 data-step-heading tabindex="-1">{{ __('Date and time') }}</h2><p>{{ __('Choose a highlighted date and an available start time.') }}</p></div>
            <x-booking-calendar />
            <x-input-error class="mt-3" :messages="$errors->get('requested_start_at')" />
            <noscript><div class="mt-5"><x-input-label for="requested_start_at_fallback" :value="__('Preferred date and time')" /><input id="requested_start_at_fallback" name="requested_start_at" type="datetime-local" class="casa-input mt-2" value="{{ $initialRequestedAt ?: now('Asia/Manila')->addDay()->setTime(14, 0)->format('Y-m-d\TH:i') }}" required><p class="mt-2 text-sm text-casa-muted">{{ __('The server will verify this time when you book.') }}</p></div></noscript>
        </section>
        <section data-booking-step="3" x-show="step === 3">
            <div class="booking-step-heading"><h2 data-step-heading tabindex="-1">{{ __('Review and save') }}</h2><p>{{ __('Check your visit details before confirming the booking.') }}</p></div>
            <div class="booking-review-layout"><x-booking-review /><div><x-input-label for="customer_notes" :value="__('Notes for the spa team (optional)')" /><textarea id="customer_notes" name="customer_notes" rows="5" maxlength="5000" class="casa-input mt-2" placeholder="{{ __('Share preferences or details to help us prepare.') }}">{{ old('customer_notes') }}</textarea><x-input-error class="mt-2" :messages="$errors->get('customer_notes')" /></div></div>
        </section>
    </div>
    <x-booking-footer :submit-label="__('Confirm booking')" :cancel-url="route('customer.appointments.index')" />
</form>
