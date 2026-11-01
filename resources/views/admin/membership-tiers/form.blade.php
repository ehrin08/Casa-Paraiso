<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Membership tiers') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $tier->exists ? __('Edit :name', ['name' => $tier->name]) : __('Add membership tier') }}</h1>
        </div>
        <a href="{{ route('admin.membership-tiers.index') }}" class="casa-button-secondary">{{ __('All tiers') }}</a>
    </x-slot>

    @php
        $selectedAddons = old('addon_discount_codes', $tier->addon_discount_codes ?? []);
    @endphp

    <form method="POST" action="{{ $tier->exists ? route('admin.membership-tiers.update', $tier) : route('admin.membership-tiers.store') }}" class="mx-auto max-w-4xl" data-membership-tier-form>
        @csrf
        @if ($tier->exists) @method('PATCH') @endif
        <x-app-card>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" :value="__('Tier name')" />
                    <x-text-input id="name" name="name" class="mt-2 block w-full" :value="old('name', $tier->name)" required maxlength="100" />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <x-input-label for="stars" :value="__('Stars')" />
                        <x-text-input id="stars" name="stars" type="number" min="0" max="5" class="mt-2 block w-full" :value="old('stars', $tier->stars)" required />
                    </div>
                    <div>
                        <x-input-label for="rank" :value="__('Rank')" />
                        <x-text-input id="rank" name="rank" type="number" min="0" max="1000" class="mt-2 block w-full" :value="old('rank', $tier->rank ?? 0)" required />
                    </div>
                    <div>
                        <x-input-label for="validity_months" :value="__('Months valid')" />
                        <x-text-input id="validity_months" name="validity_months" type="number" min="1" max="120" class="mt-2 block w-full" :value="old('validity_months', $tier->validity_months)" required />
                    </div>
                    <div class="col-span-3"><x-input-error :messages="[...$errors->get('stars'), ...$errors->get('rank'), ...$errors->get('validity_months')]" /><p class="text-xs text-casa-muted">{{ __('A higher rank is a better card; RFM recommends upgrades by rank.') }}</p></div>
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="description" :value="__('Description')" />
                    <textarea id="description" name="description" rows="2" class="casa-input mt-2 block w-full" maxlength="1000">{{ old('description', $tier->description) }}</textarea>
                </div>

                <div>
                    <x-input-label for="service_discount_percent" :value="__('Service discount (%)')" />
                    <x-text-input id="service_discount_percent" name="service_discount_percent" type="number" min="0" max="100" step="0.01" class="mt-2 block w-full" :value="old('service_discount_percent', $tier->service_discount_percent ?? 0)" />
                    <x-input-error class="mt-2" :messages="$errors->get('service_discount_percent')" />
                </div>
                <div>
                    <x-input-label for="vip_room_price" :value="__('VIP Room member price (PHP, optional)')" />
                    <x-text-input id="vip_room_price" name="vip_room_price" type="number" min="0" step="0.01" class="mt-2 block w-full" :value="old('vip_room_price', $tier->vip_room_price)" />
                    <x-input-error class="mt-2" :messages="$errors->get('vip_room_price')" />
                </div>
                <div>
                    <x-input-label for="addon_discount_percent" :value="__('Add-on discount (%)')" />
                    <x-text-input id="addon_discount_percent" name="addon_discount_percent" type="number" min="0" max="100" step="0.01" class="mt-2 block w-full" :value="old('addon_discount_percent', $tier->addon_discount_percent ?? 0)" />
                    <x-input-error class="mt-2" :messages="$errors->get('addon_discount_percent')" />
                </div>
                <fieldset>
                    <legend class="text-sm font-semibold">{{ __('Add-ons that get the discount') }}</legend>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($addons as $addon)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="addon_discount_codes[]" value="{{ $addon->code }}" @checked(in_array($addon->code, $selectedAddons, true)) class="rounded border-casa-border text-casa-primary focus:ring-casa-palm">{{ $addon->name }}</label>
                        @endforeach
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('addon_discount_codes')" />
                </fieldset>

                <div>
                    <x-input-label for="birthday_treat_label" :value="__('Birthday treat name')" />
                    <x-text-input id="birthday_treat_label" name="birthday_treat_label" class="mt-2 block w-full" :value="old('birthday_treat_label', $tier->birthday_treat_label)" maxlength="100" />
                    <p class="mt-1 text-xs text-casa-muted">{{ __('Given free once a year during the member’s birth month.') }}</p>
                </div>
                <div class="grid gap-3">
                    <div>
                        <x-input-label for="birthday_treat_addon_code" :value="__('Treat is this add-on')" />
                        <select id="birthday_treat_addon_code" name="birthday_treat_addon_code" class="casa-input mt-2">
                            <option value="">{{ __('Not an add-on') }}</option>
                            @foreach ($addons as $addon)<option value="{{ $addon->code }}" @selected(old('birthday_treat_addon_code', $tier->birthday_treat_addon_code) === $addon->code)>{{ $addon->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="birthday_treat_service_id" :value="__('Or worth this service')" />
                        <select id="birthday_treat_service_id" name="birthday_treat_service_id" class="casa-input mt-2">
                            <option value="">{{ __('Not a service') }}</option>
                            @foreach ($services as $service)<option value="{{ $service->id }}" @selected((int) old('birthday_treat_service_id', $tier->birthday_treat_service_id) === $service->id)>{{ $service->name }} · PHP {{ number_format((float) $service->price, 2) }}</option>@endforeach
                        </select>
                    </div>
                    <x-input-error :messages="[...$errors->get('birthday_treat_addon_code'), ...$errors->get('birthday_treat_service_id')]" />
                </div>

                <div>
                    <x-input-label for="referral_reward_amount" :value="__('Referral reward (PHP each)')" />
                    <x-text-input id="referral_reward_amount" name="referral_reward_amount" type="number" min="0" step="0.01" class="mt-2 block w-full" :value="old('referral_reward_amount', $tier->referral_reward_amount ?? 0)" />
                    <x-input-error class="mt-2" :messages="$errors->get('referral_reward_amount')" />
                </div>
                <label class="flex items-center gap-3 self-end text-sm font-bold">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $tier->is_active)) class="rounded border-casa-border text-casa-primary focus:ring-casa-palm">
                    {{ __('Active (new cards can be issued)') }}
                </label>
            </div>
            <div class="mt-5 flex flex-col-reverse gap-3 border-t border-casa-border pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.membership-tiers.index') }}" class="casa-button-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="casa-button-primary">{{ __('Save tier') }}</button>
            </div>
        </x-app-card>
    </form>
</x-app-layout>
