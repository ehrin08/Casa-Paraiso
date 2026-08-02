<section>
    <header>
        <h2 class="font-display text-lg font-black text-casa-text">Profile information</h2>
        <p class="mt-1 text-sm leading-6 text-casa-muted">Your verified sign-in email identifies your account and cannot be changed here.</p>
    </header>
    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf @method('patch')
        <div>
            <x-input-label for="name" value="Name" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>
        <div>
            <x-input-label for="email" value="Sign-in email" />
            <x-text-input id="email" type="email" class="mt-1 block w-full opacity-70" :value="$user->email" disabled />
        </div>
        <div>
            <x-input-label for="phone" value="Phone (optional)" />
            <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $user->phone)" autocomplete="tel" />
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
        </div>
        @if ($user->isCustomer())
            <div>
                <x-input-label for="address" value="Address (optional)" />
                <textarea id="address" name="address" rows="4" class="casa-input mt-1 block w-full" autocomplete="street-address">{{ old('address', $user->customerProfile?->address) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('address')" />
            </div>
            <div>
                <x-input-label for="contact_preference" value="Preferred contact method (optional)" />
                <select id="contact_preference" name="contact_preference" class="casa-input mt-1 block w-full">
                    <option value="">No preference</option>
                    @foreach (\App\Models\CustomerProfile::CONTACT_PREFERENCES as $value => $label)
                        <option value="{{ $value }}" @selected(old('contact_preference', strtolower((string) $user->customerProfile?->contact_preference)) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('contact_preference')" />
            </div>
        @endif
        <x-primary-button>Save profile</x-primary-button>
    </form>
</section>
