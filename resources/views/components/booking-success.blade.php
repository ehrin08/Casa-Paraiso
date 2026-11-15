@props(['appointment', 'detailUrl'])

@php
    $isCustomer = auth()->user()?->isCustomer() ?? false;
    $modalName = 'booking-success-'.$appointment->id;
    $start = $appointment->scheduled_start_at?->copy()->timezone('Asia/Manila');
    $end = $appointment->scheduled_end_at?->copy()->timezone('Asia/Manila');
    $title = $isCustomer ? __('Booking confirmed') : __('Appointment saved');
    $lead = $isCustomer
        ? __('Your time and therapist are reserved. We look forward to seeing you.')
        : __('The appointment is confirmed and added to the schedule.');
    $rows = array_filter([
        $isCustomer ? null : ['label' => __('Customer'), 'value' => $appointment->customerProfile?->display_name],
        ['label' => __('Treatment'), 'value' => $appointment->service?->name],
        ['label' => __('Date'), 'value' => $start?->format('l, F j, Y')],
        ['label' => __('Time'), 'value' => $start ? $start->format('g:i A').($end ? ' – '.$end->format('g:i A') : '') : null],
        ['label' => __('Therapist'), 'value' => $appointment->staffProfile?->user?->name],
        ['label' => __('Booking number'), 'value' => $appointment->appointment_number],
    ], fn ($row) => $row && filled($row['value']));
@endphp

<x-modal :name="$modalName" :show="true" maxWidth="md" focusable centered transient
    panel-class="casa-booking-success" labelled-by="booking-success-title">
    <div class="casa-booking-success__body" data-booking-success>
        <span class="casa-booking-success__icon" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none">
                <circle class="casa-booking-success__ring" cx="24" cy="24" r="21" stroke="currentColor" stroke-width="3" />
                <path class="casa-booking-success__check" d="m15 24.5 6.2 6.2L33.5 18" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
        <h2 id="booking-success-title" class="casa-booking-success__title">{{ $title }}</h2>
        <p class="casa-booking-success__lead">{{ $lead }}</p>

        <dl class="casa-booking-success__summary">
            @foreach ($rows as $row)
                <div>
                    <dt>{{ $row['label'] }}</dt>
                    <dd>{{ $row['value'] }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="casa-booking-success__actions">
            <button type="button" class="casa-button-primary" x-on:click="close()">{{ __('Done') }}</button>
            <a href="{{ $detailUrl }}" class="casa-button-secondary">{{ $isCustomer ? __('View booking') : __('Open appointment') }}</a>
        </div>
    </div>
</x-modal>
