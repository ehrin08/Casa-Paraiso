@props(['submitLabel', 'cancelUrl'])
<footer class="booking-wizard-footer">
    <div class="booking-footer-total" aria-live="polite">
        <span>{{ __('Booking total') }}</span>
        <strong x-text="selectedService ? `PHP ${bookingTotal.toFixed(2)}` : 'Choose a treatment'"></strong>
    </div>
    <div class="booking-footer-actions">
        <a href="{{ $cancelUrl }}" class="booking-cancel" x-on:click="cancelBooking($event)">{{ __('Cancel') }}</a>
        <button type="button" class="casa-button-secondary" x-show="step > 1" x-cloak x-on:click="previousStep()">{{ __('Back') }}</button>
        <button type="button" class="casa-button-primary" x-show="step < 3" x-cloak x-on:click="nextStep()">{{ __('Next') }} <span aria-hidden="true">→</span></button>
        <button type="submit" class="casa-button-primary disabled:opacity-50" x-show="step === 3" x-cloak x-bind:disabled="!canSubmit" x-text="submitting ? 'Saving…' : @js($submitLabel)">{{ $submitLabel }}</button>
        <noscript><button type="submit" class="casa-button-primary">{{ $submitLabel }}</button></noscript>
    </div>
</footer>
