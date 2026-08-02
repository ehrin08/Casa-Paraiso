<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Appointment') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $appointment->appointment_number }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $appointment->service?->name }} · {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}
            </p>
        </div>

        <a href="{{ route('customer.appointments.index') }}" class="casa-button-secondary">{{ __('My appointments') }}</a>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <x-input-error :messages="$errors->all()" />

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Booking status') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Visit details') }}</h2>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Appointment time') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ ($appointment->scheduled_start_at ?? $appointment->requested_start_at)?->format('M d, Y g:i A') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Assigned therapist') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->staffProfile?->user?->name ?: __('Unassigned') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Status') }}</dt>
                        <dd class="mt-2"><x-status-badge>{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge></dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Therapist preference') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->preferredStaffProfile?->user?->name ?: __('No preference') }}</dd>
                        <p class="mt-1 text-xs leading-5 text-casa-muted">{{ __('Your preferred therapist is assigned when available; otherwise the system selects another eligible therapist.') }}</p>
                    </div>
                    <div class="rounded-2xl bg-casa-brass/10 p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-cacao">{{ __('Complimentary add-on') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->promotionSuggestion?->addonName() ?: __('No voucher selected') }}</dd>
                        @if ($appointment->promotionSuggestion)
                            <p class="mt-1 text-xs leading-5 text-casa-muted">{{ __('This RFM voucher does not change your package price.') }}</p>
                        @endif
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Paid add-ons') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->addons->isNotEmpty() ? $appointment->addons->pluck('addon_name')->join(', ') : __('None') }}</dd>
                        @if ($appointment->addons->isNotEmpty())<p class="mt-1 text-xs leading-5 text-casa-muted">PHP {{ number_format($appointment->paidAddonTotal(), 2) }}@if ($appointment->addons->sum('duration_minutes')) · +{{ $appointment->addons->sum('duration_minutes') }} {{ __('minutes') }}@endif</p>@endif
                    </div>
                </dl>
            </x-app-card>

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Notes') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Request notes') }}</h2>
                </div>
                <p class="mt-5 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $appointment->customer_notes ?: __('No notes added.') }}</p>
            </x-app-card>
        </section>

        <aside class="space-y-4">
            @if ($appointment->status === \App\Models\Appointment::STATUS_CONFIRMED && $appointment->scheduled_start_at?->isFuture())
                <x-app-card>
                    <p class="casa-section-label">{{ __('Confirmed booking') }}</p>
                    <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('Your time and therapist are reserved. Cancel before the start time if your plans change.') }}</p>
                    <div class="mt-5">
                        <x-confirm-action
                            :action="route('customer.appointments.cancel', $appointment)"
                            method="PATCH"
                            label="{{ __('Cancel booking') }}"
                            confirm-title="{{ __('Cancel this confirmed booking?') }}"
                            confirm-message="{{ __('The reserved therapist and time will become available to other customers immediately.') }}"
                            confirm-button="{{ __('Cancel booking') }}"
                            button-class="casa-danger-button w-full"
                        />
                    </div>
                </x-app-card>
            @endif

            @if ($appointment->status === \App\Models\Appointment::STATUS_COMPLETED && ! $appointment->feedback)
                <a href="{{ route('customer.feedback.create', ['appointment_id' => $appointment->id]) }}" class="casa-button-primary w-full">{{ __('Submit feedback') }}</a>
            @elseif ($appointment->feedback)
                <x-app-card>
                    <p class="casa-section-label">{{ __('Feedback') }}</p>
                    <p class="mt-3 text-sm text-casa-muted">{{ __('You already submitted feedback for this appointment.') }}</p>
                </x-app-card>
            @endif
        </aside>
    </div>
</x-app-layout>
