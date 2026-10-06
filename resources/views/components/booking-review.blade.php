@props(['operations' => false])
<section class="booking-review-card">
    <h3>{{ __('Booking summary') }}</h3>
    <p class="booking-review-date" x-text="summaryDate"></p>
    <p class="text-sm text-casa-muted"><span x-text="summaryTime"></span>–<span x-text="summaryEndTime"></span> · Asia/Manila</p>
    <dl class="casa-booking-summary-details">
        @if ($operations)<dt>{{ __('Customer') }}</dt><dd x-text="customerLabel"></dd>@endif
        <dt>{{ __('Treatment') }}</dt><dd x-text="selectedService?.name || 'Not selected'"></dd>
        <dt>{{ __('Duration') }}</dt><dd x-text="`${durationMinutes} minutes`"></dd>
        <dt>{{ $operations ? __('Assigned therapist') : __('Therapist preference') }}</dt><dd x-text="{{ $operations ? 'assignedStaffName' : "selectedStaff?.name || 'No preference'" }}"></dd>
        <dt>{{ __('Paid add-ons') }}</dt><dd x-text="selectedPaidAddons.length ? selectedPaidAddons.map(addon => addon.name).join(', ') : 'None'"></dd>
        <dt>{{ __('Complimentary add-on') }}</dt><dd x-text="{{ $operations ? "persistedVoucherName || 'None'" : "selectedVoucher?.addon_name || 'None'" }}"></dd>
        <dt>{{ __('Add-on amount') }}</dt><dd x-text="`PHP ${paidAddonTotal.toFixed(2)}`"></dd>
    </dl>
    <div class="booking-review-total"><span>{{ __('Total') }}</span><strong x-text="`PHP ${bookingTotal.toFixed(2)}`"></strong></div>
    <p class="booking-confirmed-note">{{ __('Saving rechecks availability and reserves the confirmed appointment.') }}</p>
    @if (! $operations)<p class="booking-confirmed-note">{{ __('An available therapist is assigned automatically when you confirm.') }}</p>@endif
</section>
