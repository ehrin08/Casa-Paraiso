<form method="POST" action="{{ route('admin.appointments.complete', $appointment) }}" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
    @csrf
    <input type="hidden" name="_modal" value="{{ $modalName }}">

    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Finish service') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Record the visit and payment') }}</h2>
            <p class="mt-2 text-sm text-casa-muted">{{ $appointment->customerProfile?->user?->name }} · {{ $appointment->service?->name }}</p>
        </div>
        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="completion_amount" :value="__('Amount')" />
                <x-text-input id="completion_amount" name="amount" type="number" step="0.01" min="0" class="mt-2" :value="old('amount', $appointment->expectedAmount())" required />
                <x-input-error class="mt-2" :messages="$errors->get('amount')" />
            </div>
            <div>
                <x-input-label for="completion_status" :value="__('Payment status')" />
                <select id="completion_status" name="payment_status" class="casa-input mt-2" required>
                    @foreach ([\App\Models\Transaction::PAYMENT_UNPAID, \App\Models\Transaction::PAYMENT_PARTIAL, \App\Models\Transaction::PAYMENT_PAID] as $option)
                        <option value="{{ $option }}" @selected(old('payment_status', \App\Models\Transaction::PAYMENT_PAID) === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('payment_status')" />
            </div>
            <div>
                <x-input-label for="completion_method" :value="__('Payment method')" />
                <select id="completion_method" name="payment_method" class="casa-input mt-2">
                    <option value="">{{ __('Not received yet') }}</option>
                    @foreach (\App\Models\Transaction::PAYMENT_METHODS as $option)
                        <option value="{{ $option }}" @selected(old('payment_method', $transaction->payment_method) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('payment_method')" />
            </div>
            <div>
                <x-input-label for="completion_paid_at" :value="__('Payment date')" />
                <x-text-input id="completion_paid_at" name="paid_at" type="datetime-local" class="mt-2" :value="old('paid_at', now()->format('Y-m-d\TH:i'))" />
                <x-input-error class="mt-2" :messages="$errors->get('paid_at')" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="completion_notes" :value="__('Transaction notes')" />
                <textarea id="completion_notes" name="notes" rows="4" class="casa-input mt-2">{{ old('notes') }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('notes')" />
            </div>
        </div>
    </x-app-card>
    <aside class="space-y-4">
        <x-app-card>
            <p class="casa-section-label">{{ __('One recorded outcome') }}</p>
            <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('Finishing saves the completed appointment and its linked transaction together. If either save fails, neither record changes.') }}</p>
        </x-app-card>
        <button type="submit" class="casa-button-primary w-full">{{ __('Finish and record transaction') }}</button>
        <button type="button" class="casa-button-secondary w-full" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">{{ __('Keep service open') }}</button>
    </aside>
</form>
