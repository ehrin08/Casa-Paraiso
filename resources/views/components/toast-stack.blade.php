@props(['suppressBooking' => false])

@php
    $status = session('status');
    $message = match ($status) {
        'appointment-created' => __('Appointment created.'),
        'appointment-updated' => __('Appointment updated.'),
        'appointment-booked' => __('Appointment confirmed and added to the schedule.'),
        'appointment-completed' => __('Service finished and transaction recorded.'),
        'appointment-cancelled' => __('Appointment cancelled.'),
        'operating-hours-updated' => __('Default hours saved.'),
        'operating-hour-rule-created' => __('Hours change saved. Booking follows it right away.'),
        'operating-hour-rule-ended' => __('Hours change ended. Default hours apply again from today.'),
        'operating-hour-rule-deleted' => __('Hours change removed.'),
        'service-created' => __('Service created.'),
        'service-updated' => __('Service updated.'),
        'service-activated' => __('Service activated.'),
        'service-deactivated' => __('Service deactivated.'),
        'staff-created' => __('Therapist record created.'),
        'staff-updated' => __('Therapist record updated.'),
        'transaction-created' => __('Transaction created.'),
        'transaction-updated' => __('Transaction updated.'),
        'feedback-submitted' => __('Feedback submitted.'),
        'customer-rewards-updated' => __('Customer reward settings updated.'),
        'gift-voucher-issued' => __('Gift voucher recorded.'),
        'gift-voucher-voided' => __('Gift voucher voided.'),
        'gift-voucher-settings-updated' => __('Gift voucher settings updated.'),
        'membership-issued' => __('Membership card issued.'),
        'membership-renewed' => __('Membership card renewed.'),
        'membership-cancelled' => __('Membership card cancelled.'),
        'membership-tier-created' => __('Membership tier created.'),
        'membership-tier-updated' => __('Membership tier updated.'),
        'rfm-segment-created' => __('Customer group created.'),
        'rfm-segment-updated' => __('Customer group updated.'),
        'rfm-segment-moved' => __('Customer group order updated.'),
        'rfm-segment-deleted' => __('Customer group deleted.'),
        'customer-reward-dismissed' => __('Customer reward dismissed.'),
        'customer-updated' => __('Customer notes updated.'),
        'customer-online-booking-blocked' => __('Online booking blocked for this customer.'),
        'customer-online-booking-unblocked' => __('Online booking allowed again for this customer.'),
        'profile-updated' => __('Profile updated.'),
        'password-updated' => __('Password updated.'),
        'password-set' => __('Password created. You can now sign in with your email and password.'),
        'password-identity-confirmed' => __('Google account confirmed. Set your password within 10 minutes.'),
        'verification-link-sent' => __('Verification link sent.'),
        'weekly-schedule-created' => __('Weekly schedule created.'),
        'weekly-schedule-updated' => __('Weekly schedule updated.'),
        'weekly-schedule-deleted' => __('Weekly schedule deleted.'),
        'schedule-exception-created' => __('Schedule exception created.'),
        'schedule-exception-updated' => __('Schedule exception updated.'),
        'schedule-exception-deleted' => __('Schedule exception deleted.'),
        'settings-updated' => __('Settings updated.'),
        default => is_string($status) && $status !== '' ? __(str_replace('-', ' ', ucfirst($status))) : null,
    };

    // The booking success pop-up already says this.
    if ($suppressBooking && in_array($status, ['appointment-booked', 'appointment-created'], true)) {
        $message = null;
    }

    $errorMessage = session('error');
    $toasts = array_filter([
        $message ? ['type' => 'success', 'text' => $message, 'role' => 'status', 'timeout' => 6000] : null,
        $errorMessage ? ['type' => 'error', 'text' => $errorMessage, 'role' => 'alert', 'timeout' => 8000] : null,
    ]);
@endphp

@if ($toasts)
    <div class="casa-toast-stack" data-toast-stack>
        @foreach ($toasts as $toast)
            <div
                x-data="{ show: true, timer: null, start() { this.timer = setTimeout(() => this.show = false, {{ $toast['timeout'] }}) }, hold() { clearTimeout(this.timer) } }"
                x-init="start()"
                x-show="show"
                x-on:mouseenter="hold()"
                x-on:mouseleave="start()"
                x-on:focusin="hold()"
                x-on:focusout="start()"
                x-transition:enter="transition ease-casa duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-160"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                role="{{ $toast['role'] }}"
                class="casa-toast casa-toast--{{ $toast['type'] }}"
            >
                <span class="casa-toast__icon" aria-hidden="true">
                    @if ($toast['type'] === 'success')
                        <svg viewBox="0 0 24 24" fill="none"><path d="m5.5 12.5 4.2 4.2L18.5 8" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none"><path d="M12 7v6.5M12 17.2v.3" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>
                    @endif
                </span>
                <p class="casa-toast__message"><span class="sr-only">{{ $toast['type'] === 'success' ? __('Success:') : __('Error:') }}</span> {{ $toast['text'] }}</p>
                <button type="button" class="casa-toast__close" aria-label="{{ __('Dismiss message') }}" x-on:click="show = false">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </div>
        @endforeach
    </div>
@endif
