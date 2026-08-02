<x-app-layout>
    <x-slot name="header">
        <x-page-heading>
            <div>
                <p class="casa-section-label">{{ __('Administration') }}</p>
                <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Settings') }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                    {{ __('Maintain public business details and practical payment defaults. Scheduling and commission rules remain code-controlled safeguards.') }}
                </p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="casa-button-secondary">{{ __('Back to dashboard') }}</a>
        </x-page-heading>
    </x-slot>

    @if (! $settingsTableAvailable)
        <div role="alert" class="mb-5 rounded-2xl border border-casa-brass/40 bg-casa-brass/10 px-5 py-4 text-sm font-semibold leading-6 text-casa-cacao-dark">
            {{ __('Settings are displayed with safe defaults. Saving becomes available after the pending application settings migration is explicitly approved and applied.') }}
        </div>
    @endif

    <x-input-error class="mb-5" :messages="$errors->get('settings')" />

    <x-stat-strip :items="[
        ['label' => __('Hours'), 'value' => config('casa.business_hours.window'), 'meta' => config('casa.business_hours.summary'), 'tone' => 'brown'],
        ['label' => __('Timezone'), 'value' => config('casa.business_hours.timezone'), 'meta' => __('Calendar authority'), 'tone' => 'dark'],
        ['label' => __('Booking intervals'), 'value' => config('casa.business_hours.slot_interval_minutes').' '.__('minutes'), 'meta' => __('Confirmed immediately'), 'tone' => 'green'],
        ['label' => __('Therapist rate'), 'value' => number_format((float) config('casa.commissions.therapist_rate') * 100, 0).'%', 'meta' => __('Snapshotted per earning'), 'tone' => 'gold'],
    ]" class="mb-5" />

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PATCH')

            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Business profile') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Public identity and contact details') }}</h2>
                </div>

                <div class="mt-5 grid gap-5">
                    <div>
                        <x-input-label for="business_name" :value="__('Business name')" />
                        <x-text-input id="business_name" name="business_name" type="text" class="mt-2" :value="old('business_name', $settings->business_name)" required autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('business_name')" />
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="contact_email" :value="__('Contact email')" />
                            <x-text-input id="contact_email" name="contact_email" type="email" class="mt-2" :value="old('contact_email', $settings->contact_email)" />
                            <x-input-error class="mt-2" :messages="$errors->get('contact_email')" />
                        </div>
                        <div>
                            <x-input-label for="contact_phone" :value="__('Contact phone')" />
                            <x-text-input id="contact_phone" name="contact_phone" type="text" class="mt-2" :value="old('contact_phone', $settings->contact_phone)" />
                            <x-input-error class="mt-2" :messages="$errors->get('contact_phone')" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="business_address" :value="__('Business address')" />
                        <textarea id="business_address" name="business_address" rows="4" class="casa-input mt-2">{{ old('business_address', $settings->business_address) }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('business_address')" />
                    </div>

                    <div>
                        <x-input-label for="location_landmarks" :value="__('Location landmarks')" />
                        <textarea id="location_landmarks" name="location_landmarks" rows="3" class="casa-input mt-2">{{ old('location_landmarks', $settings->location_landmarks) }}</textarea>
                        <p class="mt-2 text-sm leading-6 text-casa-muted">{{ __('Use practical directions, not a second formal address.') }}</p>
                        <x-input-error class="mt-2" :messages="$errors->get('location_landmarks')" />
                    </div>

                    <div class="grid gap-5 sm:grid-cols-3">
                        <div>
                            <x-input-label for="messenger_url" :value="__('Messenger link')" />
                            <x-text-input id="messenger_url" name="messenger_url" type="url" class="mt-2" :value="old('messenger_url', $settings->messenger_url)" />
                            <x-input-error class="mt-2" :messages="$errors->get('messenger_url')" />
                        </div>
                        <div>
                            <x-input-label for="facebook_url" :value="__('Facebook page link')" />
                            <x-text-input id="facebook_url" name="facebook_url" type="url" class="mt-2" :value="old('facebook_url', $settings->facebook_url)" />
                            <x-input-error class="mt-2" :messages="$errors->get('facebook_url')" />
                        </div>
                        <div>
                            <x-input-label for="map_url" :value="__('Google Maps link')" />
                            <x-text-input id="map_url" name="map_url" type="url" class="mt-2" :value="old('map_url', $settings->map_url)" />
                            <x-input-error class="mt-2" :messages="$errors->get('map_url')" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="default_payment_method" :value="__('Default payment method')" />
                        <select id="default_payment_method" name="default_payment_method" class="casa-input mt-2" required>
                            @foreach (\App\Models\Transaction::PAYMENT_METHODS as $method)
                                <option value="{{ $method }}" @selected(old('default_payment_method', $settings->default_payment_method) === $method)>{{ ucfirst(str_replace('_', ' ', $method)) }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-sm leading-6 text-casa-muted">{{ __('Used to prefill new Admin and Receptionist payment forms. It never marks a payment as settled by itself.') }}</p>
                        <x-input-error class="mt-2" :messages="$errors->get('default_payment_method')" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end border-t border-casa-border pt-5">
                    <button type="submit" class="casa-button-primary" @disabled(! $settingsTableAvailable)>{{ __('Save settings') }}</button>
                </div>
            </x-app-card>
        </form>

        <aside class="space-y-5">
            <x-app-card>
                <p class="casa-section-label">{{ __('Security readiness') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Environment checks') }}</h2>
                <div class="mt-5 grid gap-3">
                    @foreach ($securityChecks as $check)
                        <x-metric-card :label="$check['label']" :value="$check['value']" :meta="$check['meta']" :tone="$check['tone']" />
                    @endforeach
                </div>
                <p class="mt-4 text-sm leading-6 text-casa-muted">{{ __('These checks summarize configuration only. Review the documented production checklist before every release.') }}</p>
            </x-app-card>

            <x-app-card>
                <p class="casa-section-label">{{ __('Access control') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('User administration') }}</h2>
                <p class="mt-3 text-sm leading-6 text-casa-muted">
                    {{ auth()->user()->isSuperAdmin()
                        ? __('Provision Receptionists, Therapists, Customers, and Administrators from the protected user workspace.')
                        : __('Only a Super Administrator can provision accounts, change roles, or activate and deactivate users.') }}
                </p>
                @if (auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.users.index') }}" class="casa-button-secondary mt-4 w-full">{{ __('Manage user access') }}</a>
                @endif
            </x-app-card>
        </aside>
    </div>
</x-app-layout>
