<x-app-layout>
    @php
        $isPreview = $isPreview ?? false;
        $completionModal = 'admin-appointment-completion-'.$appointment->id;
        $customer = $appointment->customerProfile;
        $customerName = $customer?->display_name ?: __('Customer unavailable');
        $initials = collect(preg_split('/\s+/u', trim($customerName)))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->join('');
        $startsAt = ($appointment->scheduled_start_at ?? $appointment->requested_start_at)?->copy()->timezone('Asia/Manila');
        $endsAt = $appointment->scheduled_end_at?->copy()->timezone('Asia/Manila');
        $scheduledDuration = $appointment->scheduled_start_at && $endsAt ? (int) $appointment->scheduled_start_at->diffInMinutes($endsAt) : null;
        $confirmed = $appointment->status === \App\Models\Appointment::STATUS_CONFIRMED;
        $canFinish = $confirmed && $appointment->scheduled_start_at?->lte(now());
        $statusLabel = ucfirst(str_replace('_', '-', $appointment->status));
        $statusTone = match ($appointment->status) { 'confirmed', 'completed' => 'success', 'cancelled' => 'danger', default => 'warning' };
        $paymentTone = match ($latestTransaction?->payment_status) { 'paid' => 'success', 'unpaid', 'partial' => 'warning', default => 'neutral' };
    @endphp

    <x-slot name="header">
        <div class="appointment-detail-heading" data-appointment-detail-heading>
            @unless ($isPreview)
            <nav aria-label="{{ __('Breadcrumb') }}" class="appointment-detail-breadcrumb">
                <a href="{{ route('admin.appointments.index') }}">{{ __('Appointments') }}</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $appointment->appointment_number }}</span>
            </nav>
            @endunless
            <div class="appointment-detail-title">
                <h1>{{ __('Appointment details') }}</h1>
                <x-status-badge :tone="$statusTone">{{ $statusLabel }}</x-status-badge>
            </div>
            <p class="appointment-detail-subtitle">{{ $appointment->appointment_number }} · {{ __('Created :date', ['date' => $appointment->created_at?->copy()->timezone('Asia/Manila')->format('M j, Y') ?: __('Unknown')]) }}</p>
        </div>
        @unless ($isPreview)
        <div class="appointment-detail-header-actions">
            <a href="{{ route('admin.appointments.index') }}" class="appointment-detail-link"><span aria-hidden="true">←</span> {{ __('Back to appointments') }}</a>
                <a href="{{ route('admin.appointments.edit', $appointment) }}" class="casa-button-secondary" data-panel-link data-turbo="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15v5Z"/></svg>
                {{ __('Edit appointment') }}
            </a>
        </div>
        @endunless
    </x-slot>

    @if ($isPreview)<div data-appointment-preview>@endif
    <div class="appointment-detail" data-appointment-detail>
        <section class="appointment-detail-column" aria-label="{{ __('Booking information') }}">
            <x-app-card class="appointment-detail-customer">
                <span class="appointment-detail-avatar" aria-hidden="true">{{ mb_strtoupper($initials) }}</span>
                <div class="appointment-detail-customer-identity">
                    <h2>{{ $customerName }}</h2>
                    <p>{{ $customer?->display_email ?: ($customer?->is_guest ? __('Guest profile') : __('No email recorded')) }}</p>
                </div>
                @if ($appointment->customerProfile)
                    <a href="{{ route('admin.customers.show', $appointment->customerProfile) }}" class="appointment-detail-link">{{ __('View customer profile') }} <span aria-hidden="true">→</span></a>
                @endif
            </x-app-card>

            <x-app-card>
                <h2 class="appointment-detail-section-title">{{ __('Booking details') }}</h2>
                <div class="appointment-detail-booking-strip">
                    <div class="appointment-detail-service">
                        <span class="appointment-detail-service-icon"><x-nav-icon name="services" /></span>
                        <strong>{{ $appointment->service?->name ?: __('Service unavailable') }}</strong>
                    </div>
                    <div><x-nav-icon name="calendar" /><span>{{ $startsAt?->format('l, F j, Y') ?: __('Not scheduled') }}</span></div>
                    <div>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 6v6l4 2"/></svg>
                        <span>{{ $startsAt?->format('g:i A') ?: __('Not scheduled') }}@if ($endsAt) – {{ $endsAt->format('g:i A') }}@endif<small>Asia/Manila</small></span>
                    </div>
                </div>
                <dl class="appointment-detail-booking-facts">
                    <div><dt>{{ __('Assigned therapist') }}</dt><dd>{{ $appointment->staffProfile?->user?->name ?: __('Unassigned') }}</dd></div>
                    <div><dt>{{ __('Customer preference') }}</dt><dd>{{ $appointment->preferredStaffProfile?->user?->name ?: __('No preference') }}</dd></div>
                    <div><dt>{{ __('Base treatment') }}</dt><dd>{{ $appointment->service ? __(':minutes minutes', ['minutes' => $appointment->service->duration_minutes]) : __('Unavailable') }}</dd></div>
                    <div><dt>{{ __('Total duration') }}</dt><dd>{{ $scheduledDuration !== null ? __(':minutes minutes', ['minutes' => $scheduledDuration]) : __('Not scheduled') }}</dd></div>
                </dl>
                @if ($appointment->preferred_staff_profile_id && $appointment->staff_profile_id && $appointment->preferred_staff_profile_id !== $appointment->staff_profile_id)
                    <p class="appointment-detail-notice">{{ __('The confirmed therapist differs from the customer preference.') }}</p>
                @endif

                <div class="appointment-detail-treatment">
                    <h3 class="appointment-detail-section-title">{{ __('Treatment & add-ons') }}</h3>
                    <x-table-shell label="{{ __('Treatment and paid add-ons') }}" class="appointment-detail-treatment-table">
                        <thead><tr><th scope="col">{{ __('Item') }}</th><th scope="col">{{ __('Duration') }}</th><th scope="col">{{ __('Amount') }}</th></tr></thead>
                        <tbody>
                            <tr>
                                <th scope="row">{{ $appointment->service?->name ?: __('Service unavailable') }}</th>
                                <td>{{ $appointment->service ? __(':minutes min', ['minutes' => $appointment->service->duration_minutes]) : __('Unavailable') }}</td>
                                <td>{{ $appointment->service ? '₱'.number_format($appointment->service->price, 2) : __('Unavailable') }}</td>
                            </tr>
                            @foreach ($appointment->addons as $addon)
                                <tr><th scope="row">{{ $addon->addon_name }}</th><td>{{ $addon->duration_minutes ? __('+:minutes min', ['minutes' => $addon->duration_minutes]) : __('No additional time') }}</td><td>₱{{ number_format($addon->price, 2) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><th scope="row" colspan="2">{{ __('Total') }}</th><td>₱{{ number_format($appointment->expectedAmount(), 2) }}</td></tr></tfoot>
                    </x-table-shell>
                </div>
                @if ($appointment->promotionSuggestion)
                    <div class="appointment-detail-voucher">
                        <x-nav-icon name="services" />
                        <div><strong>{{ __('RFM add-on voucher') }} · {{ __('Complimentary') }}</strong><p>{{ $voucherName ?: __('Voucher add-on unavailable') }}</p><p>{{ __('Prepare this complimentary add-on; keep the package price unchanged.') }}</p></div>
                    </div>
                @endif
            </x-app-card>

            <x-app-card>
                <h2 class="appointment-detail-section-title">{{ __('Visit notes') }}</h2>
                <div class="appointment-detail-notes">
                    <section><h3><x-nav-icon name="feedback" />{{ __('Customer notes') }}</h3><p>{{ $appointment->customer_notes ?: __('No customer notes.') }}</p></section>
                    <section><h3><x-nav-icon name="feedback" />{{ __('Internal notes') }}<span class="appointment-detail-staff-label">{{ __('Staff only') }}</span></h3><p>{{ $appointment->internal_notes ?: __('No internal notes.') }}</p></section>
                </div>
            </x-app-card>

            <x-app-card>
                <h2 class="appointment-detail-section-title">{{ __('Booking activity') }}</h2>
                <ol class="appointment-detail-activity">
                    @foreach ($activity as $event)
                        <li>
                            <span @class(['appointment-detail-activity-marker', 'is-success' => in_array($event['status'], ['confirmed', 'completed'], true), 'is-danger' => $event['status'] === 'cancelled']) aria-hidden="true">
                                @if (in_array($event['status'], ['confirmed', 'completed'], true))<span>✓</span>@else<x-nav-icon name="calendar" />@endif
                            </span>
                            <div><p>{{ $event['label'] }}</p><small>{{ $event['actor'] }} · {{ $event['at']?->copy()->timezone('Asia/Manila')->format('M j, Y, g:i A') ?: __('Unknown date') }}</small></div>
                        </li>
                    @endforeach
                </ol>
            </x-app-card>
        </section>

        <aside class="appointment-detail-column" aria-label="{{ __('Visit and payment') }}">
            <x-app-card>
                <h2 class="appointment-detail-section-title">{{ __('Visit status') }}</h2>
                <div class="appointment-detail-visit-state appointment-detail-visit-state--{{ $statusTone }}">
                    <span class="appointment-detail-state-icon" aria-hidden="true">{{ $statusTone === 'success' ? '✓' : '−' }}</span>
                    <div><strong>{{ $statusLabel }}</strong><p>
                        @if ($confirmed)
                            {{ $appointment->staffProfile?->user ? __(':name is reserved for this visit.', ['name' => $appointment->staffProfile->user->name]) : __('No therapist is assigned to this visit.') }}
                        @else
                            {{ match ($appointment->status) { 'completed' => __('This service has been completed.'), 'cancelled' => __('This appointment has been cancelled.'), default => __('The customer did not attend this visit.') } }}
                        @endif
                    </p></div>
                </div>
                <dl class="appointment-detail-visit-facts">
                    <div><dt><x-nav-icon name="calendar" />{{ __('Date') }}</dt><dd>{{ $startsAt?->format('l, F j, Y') ?: __('Not scheduled') }}</dd></div>
                    <div><dt><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 6v6l4 2"/></svg>{{ __('Time') }}</dt><dd>{{ $startsAt?->format('g:i A') ?: __('Not scheduled') }}@if ($endsAt) – {{ $endsAt->format('g:i A') }}@endif<small>Asia/Manila</small></dd></div>
                    <div><dt><x-nav-icon name="profile" />{{ __('Assigned therapist') }}</dt><dd>{{ $appointment->staffProfile?->user?->name ?: __('Unassigned') }}</dd></div>
                </dl>
                @if ($confirmed && ! $isPreview)
                    <div class="appointment-detail-visit-actions">
                        @if ($canFinish)
                            <button type="button" class="casa-button-primary w-full" x-data="" x-on:click="$dispatch('open-modal', '{{ $completionModal }}')"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4v16l14-8Z"/></svg>{{ __('Finish service') }}</button>
                            <x-confirm-action :action="route('admin.appointments.outcome', $appointment)" method="PATCH" label="{{ __('Mark as no-show') }}" confirm-title="{{ __('Mark this visit as no-show?') }}" confirm-message="{{ __('The visit will leave the active queue and no transaction will be created.') }}" confirm-button="{{ __('Mark no-show') }}" button-class="casa-button-secondary w-full" tone="danger"><input type="hidden" name="status" value="no_show"></x-confirm-action>
                        @else
                            <button type="button" class="casa-button-primary w-full" disabled aria-describedby="detail-visit-start"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4v16l14-8Z"/></svg>{{ __('Finish service') }}</button>
                            <button type="button" class="casa-button-secondary w-full" disabled aria-describedby="detail-visit-start">{{ __('Mark as no-show') }}</button>
                            <p id="detail-visit-start" class="appointment-detail-action-help">{{ __('Finish and no-show are available once the visit starts.') }}</p>
                        @endif
                        <div class="appointment-detail-cancel">
                            <x-confirm-action :action="route('admin.appointments.outcome', $appointment)" method="PATCH" label="{{ __('Cancel appointment') }}" confirm-title="{{ __('Cancel this appointment?') }}" confirm-message="{{ __('The therapist reservation will be released. This appointment will remain in your records.') }}" confirm-button="{{ __('Cancel appointment') }}" button-class="appointment-detail-cancel-button w-full" tone="danger" dismiss-label="{{ __('Keep appointment') }}"><input type="hidden" name="status" value="cancelled"></x-confirm-action>
                        </div>
                    </div>
                @endif
            </x-app-card>

            <x-app-card>
                <h2 class="appointment-detail-section-title">{{ __('Payment summary') }}</h2>
                <dl class="appointment-detail-payment">
                    <div><dt>{{ __('Payment status') }}</dt><dd><x-status-badge :tone="$paymentTone">{{ $latestTransaction ? ucfirst($latestTransaction->payment_status) : __('No payment recorded') }}</x-status-badge></dd></div>
                    <div class="appointment-detail-payment-total"><dt>{{ __('Expected booking total') }}</dt><dd>₱{{ number_format($appointment->expectedAmount(), 2) }}</dd></div>
                    @if ($latestTransaction)
                        <div><dt>{{ __('Latest transaction amount') }}</dt><dd>₱{{ number_format($latestTransaction->amount, 2) }}</dd></div>
                        <div><dt>{{ __('Latest transaction') }}</dt><dd><a href="{{ route('admin.transactions.show', $latestTransaction) }}" class="appointment-detail-link">{{ $latestTransaction->transaction_number }}</a></dd></div>
                    @endif
                </dl>
                @unless ($isPreview)
                @if (! $confirmed)
                    <a data-panel-link data-turbo="false" aria-haspopup="dialog" href="{{ route('admin.transactions.create', ['appointment_id' => $appointment]) }}" class="casa-button-secondary w-full"><x-nav-icon name="payments" />{{ __('Record payment') }}</a>
                @else
                    <p class="appointment-detail-action-help">{{ __('Record the visit and payment together using Finish service.') }}</p>
                @endif
                @endunless
                <div class="appointment-detail-related">
                    <h3>{{ __('Related records') }}</h3>
                    <p>{{ trans_choice(':count transaction|:count transactions', $appointment->transactions->count()) }}</p>
                    @if ($appointment->transactions->isNotEmpty())
                        <ul>@foreach ($appointment->transactions->sortByDesc('id') as $record)<li><a href="{{ route('admin.transactions.show', $record) }}" class="appointment-detail-link">{{ $record->transaction_number }} <span aria-hidden="true">→</span></a></li>@endforeach</ul>
                    @endif
                    @if ($appointment->feedback)
                        <a href="{{ route('admin.feedback.show', $appointment->feedback) }}" class="appointment-detail-link">{{ __('Feedback submitted') }} <span aria-hidden="true">→</span></a>
                    @else
                        <p>{{ __('No feedback yet') }}</p>
                    @endif
                </div>
            </x-app-card>
        </aside>
    </div>

    @if ($isPreview)
        <div class="appointment-preview-actions">
            <button type="button" class="casa-button-secondary" data-panel-close>{{ __('Close') }}</button>
            <a href="{{ route('admin.appointments.show', $appointment) }}" class="casa-button-primary" data-no-panel data-turbo="false">{{ __('Open full details') }}</a>
        </div>
    </div>
    @else
        <x-modal :name="$completionModal" :show="old('_modal') === $completionModal" maxWidth="5xl" focusable><div class="p-5">@include('admin.appointments.partials.completion-form', ['appointment' => $appointment, 'modalName' => $completionModal])</div></x-modal>
    @endif
</x-app-layout>
