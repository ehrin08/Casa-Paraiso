@props(['addons', 'customer' => false, 'selectedCodes' => []])
<details class="booking-optional" @if (count(old('addon_codes', []))) open @endif>
    <summary>{{ __('Paid add-ons (optional)') }} <span x-show="addonCodes.length" x-text="`· ${addonCodes.length} selected`"></span></summary>
    <p class="mt-3 text-sm text-casa-muted">{{ __('Back Massage adds 30 minutes to your appointment.') }}</p>
    <div class="booking-addon-grid">
        @foreach ($addons as $addon)
            <label class="booking-addon">
                <input type="checkbox" name="addon_codes[]" value="{{ $addon['code'] }}" x-model="addonCodes" x-on:change="addonChanged()" @checked(in_array($addon['code'], old('addon_codes', $selectedCodes))) @if ($customer) x-bind:disabled="selectedVoucher?.addon_code === @js($addon['code'])" @endif class="rounded border-casa-border text-casa-palm focus:ring-casa-palm">
                <span><strong>{{ $addon['name'] }}</strong><span>PHP {{ number_format((float) $addon['price'], 2) }}@if (($addon['duration_minutes'] ?? 0) > 0) · +{{ $addon['duration_minutes'] }} {{ __('min') }}@endif</span></span>
            </label>
        @endforeach
    </div>
    <x-input-error class="mt-3" :messages="$errors->get('addon_codes')" />
</details>
