<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Appointment detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $appointment->appointment_number }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $appointment->customerProfile?->display_name }} · {{ $appointment->service?->name }}</p>
        </div>
        <div class="flex gap-2">
        <a href="{{ route('reception.appointments.edit', $appointment) }}" class="casa-button-primary" data-panel-link data-turbo="false">{{ __('Edit') }}</a>
            <a href="{{ route('reception.appointments.index') }}" class="casa-button-secondary">{{ __('Calendar') }}</a>
        </div>
    </x-slot>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <x-app-card>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="casa-section-label">{{ __('Status') }}</dt><dd class="mt-2"><x-status-badge>{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge></dd></div>
                <div><dt class="casa-section-label">{{ __('Therapist') }}</dt><dd class="mt-2 font-semibold">{{ $appointment->staffProfile?->user?->name }}</dd></div>
                <div><dt class="casa-section-label">{{ __('Start') }}</dt><dd class="mt-2">{{ $appointment->scheduled_start_at?->format('M d, Y g:i A') }}</dd></div>
                <div><dt class="casa-section-label">{{ __('End') }}</dt><dd class="mt-2">{{ $appointment->scheduled_end_at?->format('M d, Y g:i A') }}</dd></div>
                <div class="sm:col-span-2"><dt class="casa-section-label">{{ __('RFM add-on voucher') }}</dt><dd class="mt-2 font-semibold">{{ $appointment->promotionSuggestion?->addonName() ?: __('None') }}</dd>@if ($appointment->promotionSuggestion)<p class="mt-1 text-xs text-casa-muted">{{ __('Complimentary add-on; package price remains unchanged.') }}</p>@endif</div>
                <div class="sm:col-span-2"><dt class="casa-section-label">{{ __('Paid add-ons') }}</dt><dd class="mt-2 font-semibold">{{ $appointment->addons->isNotEmpty() ? $appointment->addons->pluck('addon_name')->join(', ') : __('None') }}</dd>@if ($appointment->addons->isNotEmpty())<p class="mt-1 text-xs text-casa-muted">PHP {{ number_format($appointment->paidAddonTotal(), 2) }} · {{ __('Expected total: PHP :amount', ['amount' => number_format($appointment->expectedAmount(), 2)]) }}</p>@endif</div>
            </dl>
            @if ($appointment->customer_notes)
                <p class="mt-5 text-sm text-casa-muted">{{ $appointment->customer_notes }}</p>
            @endif
        </x-app-card>

        <aside class="space-y-4">
            @if ($appointment->status === \App\Models\Appointment::STATUS_CONFIRMED && $appointment->scheduled_start_at?->lte(now()))
                @php($completionModal = 'reception-appointment-completion-'.$appointment->id)
                <x-app-card>
                    <p class="casa-section-label">{{ __('Finish service') }}</p>
                    <p class="mt-2 text-sm leading-6 text-casa-muted">{{ __('Record the completed visit and its payment together.') }}</p>
                    <button type="button" class="casa-button-primary mt-4 w-full" aria-haspopup="dialog" x-data="" x-on:click="$dispatch('open-modal', '{{ $completionModal }}')">{{ __('Finish service') }}</button>
                </x-app-card>
                <x-modal :name="$completionModal" :show="old('_modal') === $completionModal" maxWidth="5xl" focusable><div class="p-5">@include('admin.appointments.partials.completion-form', ['appointment' => $appointment, 'modalName' => $completionModal, 'completeAction' => route('reception.appointments.complete', $appointment)])</div></x-modal>
            @endif

            @if ($appointment->status === \App\Models\Appointment::STATUS_CONFIRMED)
                <x-app-card>
                    <p class="casa-section-label">{{ __('Outcome') }}</p>
                    <x-input-error class="mt-2" :messages="old('_modal') ? [] : $errors->get('status')" />
                    <div class="mt-4 space-y-3">
                        @foreach ([
                            ['status' => 'no_show', 'label' => __('Mark as no-show'), 'title' => __('Mark this visit as no-show?'), 'message' => __('The visit will leave the active queue and no transaction will be created.'), 'button' => __('Mark no-show'), 'dismiss' => null, 'class' => 'casa-button-secondary w-full'],
                            ['status' => 'cancelled', 'label' => __('Cancel appointment'), 'title' => __('Cancel this appointment?'), 'message' => __('The therapist reservation will be released. This appointment will remain in your records.'), 'button' => __('Cancel appointment'), 'dismiss' => __('Keep appointment'), 'class' => 'casa-button-secondary w-full'],
                        ] as $outcome)
                            <x-confirm-action :action="route('reception.appointments.outcome', $appointment)" method="PATCH" :label="$outcome['label']" :confirm-title="$outcome['title']" :confirm-message="$outcome['message']" :confirm-button="$outcome['button']" :dismiss-label="$outcome['dismiss']" :button-class="$outcome['class']" tone="danger">
                                <input type="hidden" name="status" value="{{ $outcome['status'] }}">
                                <div>
                                    <x-input-label :for="'outcome_reason_'.$outcome['status']" :value="__('Reason (optional)')" />
                                    <x-text-input :id="'outcome_reason_'.$outcome['status']" name="reason" class="mt-2 block w-full" maxlength="500" />
                                </div>
                            </x-confirm-action>
                        @endforeach
                    </div>
                </x-app-card>
            @endif
        </aside>
    </div>
</x-app-layout>
