@php
    $cancelUrl = $cancelUrl ?? route('admin.transactions.index');
@endphp
<form method="POST" action="{{ $action }}" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Payment details') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Manual transaction') }}</h2>
        </div>

        <div class="mt-5 grid gap-5">
            <div>
                <x-input-label for="appointment_id" :value="__('Linked appointment')" />
                <select id="appointment_id" name="appointment_id" class="casa-input mt-2">
                    <option value="">{{ __('No appointment link') }}</option>
                    @foreach ($appointments as $appointment)
                        <option value="{{ $appointment->id }}" @selected((int) old('appointment_id', $transaction->appointment_id) === $appointment->id)>
                            {{ $appointment->appointment_number }} · {{ $appointment->customerProfile?->user?->name }} · {{ $appointment->service?->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('appointment_id')" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="customer_profile_id" :value="__('Customer')" />
                    <select id="customer_profile_id" name="customer_profile_id" class="casa-input mt-2" required>
                        <option value="">{{ __('Select customer') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('customer_profile_id', $transaction->customer_profile_id) === $customer->id)>
                                {{ $customer->user->name }} ({{ $customer->customer_code }})
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('customer_profile_id')" />
                </div>

                <div>
                    <x-input-label for="service_id" :value="__('Service')" />
                    <select id="service_id" name="service_id" class="casa-input mt-2">
                        <option value="">{{ __('General service') }}</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((int) old('service_id', $transaction->service_id) === $service->id)>
                                {{ $service->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('service_id')" />
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="amount" :value="__('Amount')" />
                    <x-text-input id="amount" name="amount" type="number" step="0.01" min="0" class="mt-2" :value="old('amount', $transaction->amount)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('amount')" />
                </div>

                <div>
                    <x-input-label for="paid_at" :value="__('Paid date')" />
                    <x-text-input id="paid_at" name="paid_at" type="datetime-local" class="mt-2" :value="old('paid_at', optional($transaction->paid_at)->format('Y-m-d\\TH:i'))" />
                    <x-input-error class="mt-2" :messages="$errors->get('paid_at')" />
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="payment_status" :value="__('Payment status')" />
                    <select id="payment_status" name="payment_status" class="casa-input mt-2" required>
                        @foreach (\App\Models\Transaction::PAYMENT_STATUSES as $option)
                            <option value="{{ $option }}" @selected(old('payment_status', $transaction->payment_status) === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('payment_status')" />
                </div>

                <div>
                    <x-input-label for="payment_method" :value="__('Payment method')" />
                    <select id="payment_method" name="payment_method" class="casa-input mt-2">
                        <option value="">{{ __('Not set') }}</option>
                        @foreach (\App\Models\Transaction::PAYMENT_METHODS as $option)
                            <option value="{{ $option }}" @selected(old('payment_method', $transaction->payment_method) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('payment_method')" />
                </div>
            </div>

            <div>
                <x-input-label for="notes" :value="__('Notes')" />
                <textarea id="notes" name="notes" rows="4" class="casa-input mt-2">{{ old('notes', $transaction->notes) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('notes')" />
            </div>
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card data-modal-actions>
            <p class="casa-section-label">{{ __('Manual only') }}</p>
            <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('This records payments received outside the system, such as cash, GCash, bank transfer, or another manual method.') }}</p>
        </x-app-card>
        <x-app-card>
            <div class="flex flex-col gap-3">
                <button type="submit" class="casa-button-primary w-full">{{ $submitLabel }}</button>
                <a href="{{ $cancelUrl }}" class="casa-button-secondary w-full">{{ __('Cancel') }}</a>
            </div>
        </x-app-card>
    </aside>
</form>
