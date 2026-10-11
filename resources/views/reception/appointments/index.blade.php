<x-app-layout>
    <x-slot name="header"><div><p class="casa-section-label">{{ __('Daily work') }}</p><h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Appointments') }}</h1><p class="mt-2 text-sm text-casa-muted">{{ __('Manage visits and check when therapists are available.') }}</p></div><a href="{{ route('reception.appointments.create') }}" class="casa-button-primary" data-panel-link data-turbo="false">{{ __('Add appointment') }}</a></x-slot>
    <div class="space-y-5"><x-stat-strip :items="[['label' => __('Confirmed'), 'value' => $summary['confirmed'], 'meta' => __('Reserved'), 'tone' => 'green'], ['label' => __('Completed'), 'value' => $summary['completed'], 'meta' => __('Finished'), 'tone' => 'brown'], ['label' => __('Cancelled'), 'value' => $summary['cancelled'], 'meta' => __('Removed'), 'tone' => 'gold']]"/><x-operational-calendar :feed-url="route('reception.appointments.calendar')" :initial-week="$initialWeek" :initial-date="$initialDate" :initial-mode="$mode" role="reception" :services="$services" :staff-profiles="$staffProfiles" :create-url="route('reception.appointments.create')"/></div>
    @if ($completedTransaction)
        <x-appointment-completed :transaction="$completedTransaction" :voucher-name="$completedVoucherName" role="reception" />
    @endif
</x-app-layout>
