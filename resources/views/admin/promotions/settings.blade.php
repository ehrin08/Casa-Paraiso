<x-app-layout>
    <x-slot name="header">
        <div class="rewards-heading">
            <h1>{{ __('Automatic reward settings') }}</h1>
            <p>{{ __('Choose the complimentary add-on for each customer group.') }}</p>
        </div>
        <a href="{{ route('admin.promotions.index') }}" class="casa-button-secondary">{{ __('Back to customer rewards') }}</a>
    </x-slot>

    <div class="rewards-workspace rewards-editor" data-rewards-settings-editor>
        <form method="POST" action="{{ route('admin.promotions.settings.update') }}">
            @csrf
            @method('PATCH')
            <x-app-card>
                <div class="rewards-editor-heading">
                    <div>
                        <h2>{{ __('Customer reward configuration') }}</h2>
                        <p>{{ __('Changes affect future rewards. Issued rewards keep their existing add-on and expiration date.') }}</p>
                    </div>
                    <div class="rewards-filter-field">
                        <x-input-label for="promotion_voucher_validity_days" :value="__('Reward validity')" />
                        <select id="promotion_voucher_validity_days" name="promotion_voucher_validity_days" class="casa-input">
                            <option value="">{{ __('No expiration') }}</option>
                            @foreach (config('casa.customer_rewards.validity_options') as $days)
                                <option value="{{ $days }}" @selected((string) old('promotion_voucher_validity_days', $settings->promotion_voucher_validity_days) === (string) $days)>{{ trans_choice(':count day|:count days', $days) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('promotion_voucher_validity_days')" />
                    </div>
                </div>
                <x-input-error class="mt-3" :messages="$errors->get('groups')" />
                <div class="rewards-editor-groups">
                    @foreach ($presets as $preset)
                        @php $segment = $preset['segment']; @endphp
                        <section class="rewards-editor-group">
                            <div class="rewards-editor-group-heading">
                                <h3>{{ $preset['name'] }}</h3>
                                <label class="rewards-active-control">
                                    <input type="hidden" name="groups[{{ $preset['key'] }}][is_active]" value="0">
                                    <input type="checkbox" name="groups[{{ $preset['key'] }}][is_active]" value="1" @checked(old("groups.{$preset['key']}.is_active", $segment?->is_active)) class="rounded border-casa-border text-casa-primary focus:ring-casa-palm">
                                    {{ __('Active') }}<span class="sr-only">{{ $preset['name'] }}</span>
                                </label>
                            </div>
                            <p>{{ $preset['description'] }}</p>
                            <x-input-error :messages="$errors->get('groups.'.$preset['key'].'.is_active')" />
                            <div class="rewards-filter-field">
                                <x-input-label :for="'reward-'.$preset['key']" :value="__('Complimentary add-on')" />
                                <select id="{{ 'reward-'.$preset['key'] }}" name="groups[{{ $preset['key'] }}][addon_code]" class="casa-input" required>
                                    @foreach ($addons as $addon)
                                        <option value="{{ $addon->code }}" @selected(old("groups.{$preset['key']}.addon_code", $segment?->addon_code ?: $preset['addon_code']) === $addon->code)>{{ $addon->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('groups.'.$preset['key'].'.addon_code')" />
                            </div>
                        </section>
                    @endforeach
                </div>
                @if ($addons->isEmpty())
                    <p class="mt-4 text-sm text-casa-muted">{{ __('An active add-on is required to configure rewards.') }} <a href="{{ route('admin.addons.index') }}" class="font-bold text-casa-primary underline">{{ __('Manage add-ons') }}</a></p>
                @endif
                <div class="rewards-editor-actions">
                    <a href="{{ route('admin.promotions.index') }}" class="casa-button-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="casa-button-primary" @disabled($addons->isEmpty())>{{ __('Save customer rewards') }}</button>
                </div>
            </x-app-card>
        </form>
    </div>
</x-app-layout>
