@props(['notification', 'available' => true, 'primary' => false])

@if ($available)
    <form method="POST" action="{{ route('notifications.open', $notification) }}">
        @csrf
        @method('PATCH')
        <button type="submit" class="{{ $primary ? 'casa-button-primary' : 'notifications-view' }}" aria-label="{{ __('View booking :number', ['number' => $notification->data['appointment_number'] ?? __('Appointment')]) }}">{{ __('View booking') }}<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg></button>
    </form>
@else
    <button type="button" class="{{ $primary ? 'casa-button-primary' : 'notifications-view' }}" disabled>{{ $primary ? __('View booking') : __('Unavailable') }}</button>
@endif
