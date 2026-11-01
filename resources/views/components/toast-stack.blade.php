@php
    $status = session('status');
    $message = match ($status) {
        'appointment-created' => __('Appointment created.'),
        'appointment-updated' => __('Appointment updated.'),
        'appointment-booked' => __('Appointment confirmed and added to the schedule.'),
        'appointment-completed' => __('Service finished and transaction recorded.'),
        'appointment-cancelled' => __('Appointment cancelled.'),
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

    $errorMessage = session('error');
@endphp

@if ($message || $errorMessage)
    <div class="pointer-events-none fixed end-4 top-4 z-[60] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3">
        @if ($message)
            <div
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 4200)"
                x-show="show"
                x-transition:enter="transition ease-casa duration-200"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-160"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                role="status"
                class="pointer-events-auto rounded-[20px] border border-casa-success/15 bg-casa-success-bg px-5 py-4 text-sm font-semibold text-casa-success shadow-casa-card"
            >
                {{ $message }}
            </div>
        @endif

        @if ($errorMessage)
            <div
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 5600)"
                x-show="show"
                x-transition:enter="transition ease-casa duration-200"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-160"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                role="alert"
                class="pointer-events-auto rounded-[20px] border border-casa-danger/15 bg-casa-danger-bg px-5 py-4 text-sm font-semibold text-casa-danger-text shadow-casa-card"
            >
                {{ $errorMessage }}
            </div>
        @endif
    </div>
@endif
