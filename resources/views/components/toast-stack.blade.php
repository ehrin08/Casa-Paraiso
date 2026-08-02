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
        'customer-reward-dismissed' => __('Customer reward dismissed.'),
        'customer-updated' => __('Customer notes updated.'),
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
                x-transition
                role="status"
                class="pointer-events-auto rounded-2xl border border-casa-green/30 bg-white px-5 py-4 text-sm font-semibold text-casa-green shadow-casa-lift"
            >
                {{ $message }}
            </div>
        @endif

        @if ($errorMessage)
            <div
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 5600)"
                x-show="show"
                x-transition
                role="alert"
                class="pointer-events-auto rounded-2xl border border-red-200 bg-white px-5 py-4 text-sm font-semibold text-red-700 shadow-casa-lift"
            >
                {{ $errorMessage }}
            </div>
        @endif
    </div>
@endif
