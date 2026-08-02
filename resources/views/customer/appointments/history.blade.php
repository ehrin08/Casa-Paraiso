<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-eyebrow">{{ __('Customer lounge') }}</p>
            <h1 class="mt-3 font-editorial text-4xl font-semibold text-casa-text">{{ __('My appointments') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Review your upcoming visits and wellness history in one place.') }}</p>
        </div>
        <a href="{{ route('customer.appointments.index') }}" class="casa-button-secondary">{{ __('View calendar') }}</a>
    </x-slot>

    <x-app-card>
        <x-list-toolbar eyebrow="{{ __('My appointments') }}" title="{{ __('Appointment history') }}" :count="$appointments->total()" :reset-url="route('customer.appointments.history')" :active-filters="collect([$appointmentStatus, $appointmentDateFrom, $appointmentDateTo])->filter(fn ($value) => filled($value))->count()" :collapsible="true">
            <form method="GET" action="{{ route('customer.appointments.history') }}" class="casa-filter-grid sm:grid-cols-2 xl:grid-cols-[minmax(10rem,1fr)_minmax(10rem,1fr)_minmax(10rem,1fr)_auto]">
                <label><span class="sr-only">{{ __('Filter appointment status') }}</span><select name="status" class="casa-input" aria-label="{{ __('Filter appointment status') }}"><option value="">{{ __('All appointment statuses') }}</option>@foreach (\App\Models\Appointment::STATUSES as $status)<option value="{{ $status }}" @selected($appointmentStatus === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></label>
                <label><span class="sr-only">{{ __('From date') }}</span><input type="date" name="date_from" value="{{ $appointmentDateFrom }}" class="casa-input" aria-label="{{ __('From date') }}"><x-input-error :messages="$errors->get('date_from')" class="mt-1" /></label>
                <label><span class="sr-only">{{ __('To date') }}</span><input type="date" name="date_to" value="{{ $appointmentDateTo }}" class="casa-input" aria-label="{{ __('To date') }}"><x-input-error :messages="$errors->get('date_to')" class="mt-1" /></label>
                <button type="submit" class="casa-button-secondary">{{ __('Filter') }}</button>
            </form>
        </x-list-toolbar>

        <div class="mt-5 space-y-3">
            @forelse ($appointments as $appointment)
                @php
                    $appointmentModal = 'customer-appointment-details-'.$appointment->id;
                    $appointmentStart = $appointment->scheduled_start_at ?? $appointment->requested_start_at;
                    $statusTone = match ($appointment->status) {
                        \App\Models\Appointment::STATUS_CONFIRMED => 'success',
                        \App\Models\Appointment::STATUS_CANCELLED, \App\Models\Appointment::STATUS_NO_SHOW => 'danger',
                        default => 'neutral',
                    };
                @endphp
                <article class="rounded-2xl border border-casa-border bg-casa-paper p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2"><x-status-badge :tone="$statusTone">{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge><span class="text-xs font-bold text-casa-muted">{{ $appointment->appointment_number }}</span></div>
                            <h2 class="mt-3 text-lg font-extrabold text-casa-text">{{ $appointment->service?->name ?? __('Spa service') }}</h2>
                            <dl class="mt-3 grid gap-2 text-sm leading-6 text-casa-muted sm:grid-cols-2"><div><dt class="inline font-extrabold text-casa-text">{{ __('Appointment time') }}:</dt> <dd class="inline">{{ $appointmentStart?->format('M d, Y g:i A') }}</dd></div><div><dt class="inline font-extrabold text-casa-text">{{ __('Therapist') }}:</dt> <dd class="inline">{{ $appointment->staffProfile?->user?->name ?? __('Unassigned') }}</dd></div></dl>
                        </div>
                        <button type="button" class="casa-button-secondary shrink-0" x-data="" x-on:click="$dispatch('open-modal', '{{ $appointmentModal }}')">{{ __('View details') }}</button>
                    </div>
                </article>

                <x-modal :name="$appointmentModal" maxWidth="3xl" focusable>
                    <div class="p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-4 border-b border-casa-border pb-5">
                            <div><p class="casa-section-label">{{ __('Appointment') }}</p><h2 class="mt-2 font-display text-2xl font-black text-casa-text">{{ $appointment->appointment_number }}</h2><p class="mt-2 text-sm text-casa-muted">{{ $appointment->service?->name }}</p></div>
                            <button type="button" class="casa-icon-button" aria-label="{{ __('Close appointment details') }}" x-on:click="$dispatch('close-modal', '{{ $appointmentModal }}')">×</button>
                        </div>
                        <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl bg-casa-bg p-4"><dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Requested') }}</dt><dd class="mt-2 font-semibold text-casa-text">{{ $appointment->requested_start_at?->format('M d, Y g:i A') }}</dd></div>
                            <div class="rounded-2xl bg-casa-bg p-4"><dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Scheduled') }}</dt><dd class="mt-2 font-semibold text-casa-text">{{ $appointment->scheduled_start_at?->format('M d, Y g:i A') ?: __('Not scheduled') }}</dd></div>
                            <div class="rounded-2xl bg-casa-bg p-4"><dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Assigned therapist') }}</dt><dd class="mt-2 font-semibold text-casa-text">{{ $appointment->staffProfile?->user?->name ?: __('Unassigned') }}</dd></div>
                            <div class="rounded-2xl bg-casa-bg p-4"><dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Status') }}</dt><dd class="mt-2"><x-status-badge :tone="$statusTone">{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge></dd></div>
                            <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2"><dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Therapist preference') }}</dt><dd class="mt-2 font-semibold text-casa-text">{{ $appointment->preferredStaffProfile?->user?->name ?: __('No preference') }}</dd></div>
                            <div class="rounded-2xl bg-casa-brass/10 p-4 sm:col-span-2"><dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-cacao">{{ __('Complimentary add-on') }}</dt><dd class="mt-2 font-semibold text-casa-text">{{ $appointment->promotionSuggestion?->addonName() ?: __('No voucher selected') }}</dd></div>
                            <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2"><dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Paid add-ons') }}</dt><dd class="mt-2 font-semibold text-casa-text">{{ $appointment->addons->isNotEmpty() ? $appointment->addons->pluck('addon_name')->join(', ') : __('None') }}</dd></div>
                            <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2"><dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Request notes') }}</dt><dd class="mt-2 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $appointment->customer_notes ?: __('No notes added.') }}</dd></div>
                        </dl>
                        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:justify-end">
                            @if ($appointment->status === \App\Models\Appointment::STATUS_COMPLETED && ! $appointment->feedback)
                                <a href="{{ route('customer.feedback.create', ['appointment_id' => $appointment->id]) }}" class="casa-button-primary">{{ __('Submit feedback') }}</a>
                            @endif
                            @if ($appointment->status === \App\Models\Appointment::STATUS_CONFIRMED && $appointment->scheduled_start_at?->isFuture())
                                <x-confirm-action :action="route('customer.appointments.cancel', $appointment)" method="PATCH" label="{{ __('Cancel booking') }}" confirm-title="{{ __('Cancel this confirmed booking?') }}" confirm-message="{{ __('The reserved therapist and time will become available to other customers immediately.') }}" confirm-button="{{ __('Cancel booking') }}" button-class="casa-danger-button" />
                            @endif
                        </div>
                    </div>
                </x-modal>
            @empty
                <x-empty-state title="{{ __('No appointments found') }}" description="{{ __('Try adjusting the filters or book a new visit when you are ready.') }}" />
            @endforelse
        </div>

        @if ($appointments->isNotEmpty())
            <div class="mt-5">{{ $appointments->links() }}</div>
        @endif
    </x-app-card>
</x-app-layout>
