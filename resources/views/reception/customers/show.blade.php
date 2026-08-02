<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customer detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $customer->user?->name }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $customer->customer_code }} &middot; {{ $customer->user?->email }}</p>
        </div>
        <a href="{{ route('reception.customers.index') }}" class="casa-button-secondary">{{ __('Customers') }}</a>
    </x-slot>

    <div class="grid gap-5 lg:grid-cols-[22rem_minmax(0,1fr)]">
        <x-app-card>
            <p class="casa-section-label">{{ __('Contact and operational notes') }}</p>
            <form method="POST" action="{{ route('reception.customers.update', $customer) }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <x-input-label for="phone" value="Phone"/>
                    <input id="phone" name="phone" value="{{ old('phone', $customer->user?->phone) }}" class="casa-input mt-2">
                    <x-input-error class="mt-2" :messages="$errors->get('phone')"/>
                </div>
                <div>
                    <x-input-label for="address" value="Address"/>
                    <textarea id="address" name="address" rows="3" class="casa-input mt-2">{{ old('address', $customer->address) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('address')"/>
                </div>
                <div>
                    <x-input-label for="contact_preference" value="Contact preference"/>
                    <select id="contact_preference" name="contact_preference" class="casa-input mt-2">
                        <option value="">{{ __('Not set') }}</option>
                        @foreach(\App\Models\CustomerProfile::CONTACT_PREFERENCES as $value => $label)
                            <option value="{{ $value }}" @selected(old('contact_preference', $customer->contact_preference) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('contact_preference')"/>
                </div>
                <div>
                    <x-input-label for="notes" value="Operational notes"/>
                    <textarea id="notes" name="notes" rows="5" class="casa-input mt-2">{{ old('notes', $customer->notes) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('notes')"/>
                </div>
                <button class="casa-button-primary w-full">{{ __('Save permitted details') }}</button>
            </form>
        </x-app-card>

        <div class="space-y-5">
            <x-app-card>
                <p class="casa-section-label">{{ __('Appointment history') }}</p>
                <div class="mt-4 space-y-2">
                    @forelse($customer->appointments as $appointment)
                        <a href="{{ route('reception.appointments.show', $appointment) }}" class="block rounded-xl border border-casa-border p-3">
                            <strong>{{ $appointment->service?->name }}</strong>
                            <span class="block text-sm text-casa-muted">{{ $appointment->scheduled_start_at?->format('M d, Y g:i A') }} &middot; {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-casa-muted">{{ __('No appointments.') }}</p>
                    @endforelse
                </div>
            </x-app-card>

            <x-app-card>
                <p class="casa-section-label">{{ __('Payment history') }}</p>
                <div class="mt-4 space-y-2">
                    @forelse($customer->transactions as $payment)
                        <a href="{{ route('reception.transactions.show', $payment) }}" class="flex justify-between rounded-xl border border-casa-border p-3">
                            <span>{{ $payment->transaction_number }}</span>
                            <strong>PHP {{ number_format((float) $payment->amount, 2) }}</strong>
                        </a>
                    @empty
                        <p class="text-sm text-casa-muted">{{ __('No payments.') }}</p>
                    @endforelse
                </div>
            </x-app-card>
        </div>
    </div>
</x-app-layout>
