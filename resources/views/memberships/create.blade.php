<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Memberships') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Issue membership card') }}</h1>
        </div>
        <a href="{{ route($routePrefix.'memberships.index') }}" class="casa-button-secondary">{{ __('All memberships') }}</a>
    </x-slot>

    <form method="POST" action="{{ route($routePrefix.'memberships.store') }}" class="mx-auto grid max-w-4xl gap-6" data-membership-form>
        @csrf
        <x-app-card>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="customer_profile_id" :value="__('Customer')" />
                    <select id="customer_profile_id" name="customer_profile_id" class="casa-input mt-2" required>
                        <option value="">{{ __('Select customer') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('customer_profile_id', $selectedCustomer) === $customer->id)>{{ $customer->display_name }} ({{ $customer->customer_code }})</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('customer_profile_id')" />
                </div>
                <div>
                    <x-input-label for="membership_tier_id" :value="__('Tier')" />
                    <select id="membership_tier_id" name="membership_tier_id" class="casa-input mt-2" required>
                        @foreach ($tiers as $tier)
                            <option value="{{ $tier->id }}" @selected((int) old('membership_tier_id', $selectedTier) === $tier->id)>{{ $tier->name }} · {{ trans_choice(':count month|:count months', $tier->validity_months) }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('membership_tier_id')" />
                </div>
                <div>
                    <x-input-label for="card_number" :value="__('Card number')" />
                    <x-text-input id="card_number" name="card_number" class="mt-2 block w-full" :value="old('card_number', $cardNumber)" required maxlength="40" />
                    <x-input-error class="mt-2" :messages="$errors->get('card_number')" />
                </div>
                <div>
                    <x-input-label for="issued_at" :value="__('Issued on')" />
                    <x-text-input id="issued_at" name="issued_at" type="date" class="mt-2 block w-full" :value="old('issued_at', $issuedAt)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('issued_at')" />
                </div>
                <div>
                    <x-input-label for="valid_until" :value="__('Valid until (optional)')" />
                    <x-text-input id="valid_until" name="valid_until" type="date" class="mt-2 block w-full" :value="old('valid_until')" />
                    <p class="mt-1 text-xs text-casa-muted">{{ __('Leave blank to use the tier validity.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('valid_until')" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="referred_by_customer_profile_id" :value="__('Referred by (optional)')" />
                    <select id="referred_by_customer_profile_id" name="referred_by_customer_profile_id" class="casa-input mt-2">
                        <option value="">{{ __('No referral') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('referred_by_customer_profile_id') === $customer->id)>{{ $customer->display_name }} ({{ $customer->customer_code }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-casa-muted">{{ __('Both the new member and the referrer receive the tier referral reward on their next service.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('referred_by_customer_profile_id')" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="notes" :value="__('Notes')" />
                    <textarea id="notes" name="notes" rows="3" class="casa-input mt-2 block w-full">{{ old('notes') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                </div>
            </div>
            <div class="mt-5 flex flex-col-reverse gap-3 border-t border-casa-border pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route($routePrefix.'memberships.index') }}" class="casa-button-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="casa-button-primary" @disabled($tiers->isEmpty())>{{ __('Issue card') }}</button>
            </div>
        </x-app-card>
    </form>
</x-app-layout>
