@props(['transaction', 'voucherName' => null])

@php
    $appointment = $transaction->appointment;
    $formatDate = fn ($date) => $date?->copy()->timezone('Asia/Manila')->format('M j, Y g:i A') ?: __('Not recorded');
@endphp

<div class="casa-completion-details">
    <section>
        <h3 class="text-lg font-extrabold">{{ __('Appointment') }}</h3>
        <dl class="casa-completion-fields">
            <div><dt>{{ __('Booking number') }}</dt><dd>{{ $appointment->appointment_number }}</dd></div>
            <div><dt>{{ __('Customer') }}</dt><dd>{{ $appointment->customerProfile?->display_name ?: __('Customer unavailable') }}</dd></div>
            <div><dt>{{ __('Service') }}</dt><dd>{{ $appointment->service?->name ?: __('Service unavailable') }}</dd></div>
            <div><dt>{{ __('Therapist') }}</dt><dd>{{ $appointment->staffProfile?->user?->name ?: __('Therapist unavailable') }}</dd></div>
            <div><dt>{{ __('Scheduled start') }}</dt><dd>{{ $formatDate($appointment->scheduled_start_at) }}</dd></div>
            <div><dt>{{ __('Scheduled end') }}</dt><dd>{{ $formatDate($appointment->scheduled_end_at) }}</dd></div>
            <div><dt>{{ __('Visit status') }}</dt><dd><x-status-badge>{{ ucfirst($appointment->status) }}</x-status-badge></dd></div>
            <div><dt>{{ __('Completed at') }}</dt><dd>{{ $formatDate($appointment->completed_at) }}</dd></div>
            <div><dt>{{ __('Paid add-ons') }}</dt><dd>
                @forelse ($appointment->addons as $addon)
                    <span class="block">{{ $addon->addon_name }} · PHP {{ number_format((float) $addon->price, 2) }}</span>
                @empty
                    {{ __('None') }}
                @endforelse
            </dd></div>
            <div><dt>{{ __('Complimentary voucher') }}</dt><dd>{{ $voucherName ?: ($appointment->promotionSuggestion?->addon_code ?: __('None')) }}</dd></div>
        </dl>
    </section>
    <section>
        <h3 class="text-lg font-extrabold">{{ __('Transaction') }}</h3>
        <dl class="casa-completion-fields">
            <div><dt>{{ __('Transaction number') }}</dt><dd>{{ $transaction->transaction_number }}</dd></div>
            <div class="casa-completion-amount"><dt>{{ __('Recorded amount') }}</dt><dd>PHP {{ number_format((float) $transaction->amount, 2) }}</dd></div>
            <div><dt>{{ __('Payment status') }}</dt><dd><x-status-badge>{{ ucfirst($transaction->payment_status) }}</x-status-badge></dd></div>
            <div><dt>{{ __('Payment method') }}</dt><dd>{{ $transaction->payment_method ?: __('Not received') }}</dd></div>
            <div><dt>{{ __('Payment date') }}</dt><dd>{{ $transaction->paid_at ? $formatDate($transaction->paid_at) : __('Not received') }}</dd></div>
            <div><dt>{{ __('Recorded by') }}</dt><dd>{{ $transaction->recorder?->name ?: __('Recorder unavailable') }}</dd></div>
            <div><dt>{{ __('Transaction notes') }}</dt><dd class="whitespace-pre-line">{{ $transaction->notes ?: __('None') }}</dd></div>
        </dl>
    </section>
</div>
