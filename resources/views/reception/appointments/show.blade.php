<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Appointment detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $appointment->appointment_number }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $appointment->customerProfile?->user?->name }} · {{ $appointment->service?->name }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('reception.appointments.edit', $appointment) }}" class="casa-button-primary">{{ __('Edit') }}</a>
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
                <x-app-card>
                    <p class="casa-section-label">{{ __('Finish service') }}</p>
                    <form method="POST" action="{{ route('reception.appointments.complete', $appointment) }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $appointment->expectedAmount()) }}" class="casa-input" required>
                        <select name="payment_status" class="casa-input" required>
                            @foreach ([\App\Models\Transaction::PAYMENT_UNPAID, \App\Models\Transaction::PAYMENT_PARTIAL, \App\Models\Transaction::PAYMENT_PAID] as $status)
                                <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        <select name="payment_method" class="casa-input">
                            <option value="">{{ __('Select method') }}</option>
                            @foreach (\App\Models\Transaction::PAYMENT_METHODS as $method)
                                <option value="{{ $method }}" @selected(old('payment_method', $transaction->payment_method) === $method)>{{ $method }}</option>
                            @endforeach
                        </select>
                        <input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="casa-input">
                        <textarea name="notes" class="casa-input" rows="3" placeholder="{{ __('Payment notes') }}"></textarea>
                        <button class="casa-button-primary w-full">{{ __('Complete and record payment') }}</button>
                    </form>
                </x-app-card>
            @endif

            @if ($appointment->status === \App\Models\Appointment::STATUS_CONFIRMED)
                <x-app-card>
                    <p class="casa-section-label">{{ __('Outcome') }}</p>
                    <form method="POST" action="{{ route('reception.appointments.outcome', $appointment) }}" class="mt-4 space-y-3">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="casa-input">
                            <option value="cancelled">{{ __('Cancelled') }}</option>
                            <option value="no_show">{{ __('No-show') }}</option>
                        </select>
                        <input name="reason" class="casa-input" placeholder="{{ __('Reason (optional)') }}">
                        <button class="casa-button-secondary w-full">{{ __('Record outcome') }}</button>
                    </form>
                </x-app-card>
            @endif
        </aside>
    </div>
</x-app-layout>
