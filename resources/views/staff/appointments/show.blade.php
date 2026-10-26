<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Therapist appointment') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $appointment->appointment_number }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $appointment->customerProfile?->display_name }} · {{ $appointment->service?->name }}
            </p>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <x-input-error :messages="$errors->all()" />

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Details') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Service schedule') }}</h2>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Appointment time') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ ($appointment->scheduled_start_at ?? $appointment->requested_start_at)?->format('M d, Y g:i A') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Status') }}</dt>
                        <dd class="mt-2"><x-status-badge>{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge></dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Customer phone') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->customerProfile?->display_phone ?: __('Not set') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Therapist preference') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->preferredStaffProfile?->user?->name ?: __('No preference') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-brass/10 p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-cacao">{{ __('RFM add-on voucher') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->promotionSuggestion?->addonName() ?: __('None') }}</dd>
                        @if ($appointment->promotionSuggestion)
                            <p class="mt-1 text-xs leading-5 text-casa-muted">{{ __('Prepare this complimentary add-on as part of the scheduled visit.') }}</p>
                        @endif
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Paid add-ons') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $appointment->addons->isNotEmpty() ? $appointment->addons->pluck('addon_name')->join(', ') : __('None') }}</dd>
                        @if ($appointment->addons->isNotEmpty())<p class="mt-1 text-xs leading-5 text-casa-muted">{{ __('Prepare these add-ons as part of the scheduled visit.') }}</p>@endif
                    </div>
                </dl>
            </x-app-card>

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Notes') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Customer notes') }}</h2>
                </div>
                <p class="mt-5 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $appointment->customer_notes ?: __('No customer notes.') }}</p>
            </x-app-card>
        </section>

        <aside class="space-y-4">
            <x-app-card>
                <p class="casa-section-label">{{ __('Customer') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ $appointment->customerProfile?->display_name }}</h2>
                @if ($appointment->customerProfile)
                    <a href="{{ route('staff.customers.show', $appointment->customerProfile) }}" class="mt-5 casa-button-secondary w-full">{{ __('Open customer') }}</a>
                @endif
            </x-app-card>
        </aside>
    </div>

</x-app-layout>
