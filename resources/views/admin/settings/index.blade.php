<x-app-layout>
    <x-slot name="header">
        <x-page-heading>
            <div class="settings-heading">
                <h1>{{ __('Settings') }}</h1>
                <p>{{ __('Manage your business information, system settings and organization details.') }}</p>
            </div>
        </x-page-heading>
    </x-slot>

    @if (! $settingsTableAvailable)
        <div role="alert" class="mb-4 rounded-xl border border-casa-border bg-casa-sand p-4 text-sm">
            {{ __('Settings are displayed with safe defaults. Saving becomes available after the pending application settings migration is explicitly approved and applied.') }}
        </div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-xl border border-casa-danger/20 bg-casa-danger-bg p-4 text-sm text-casa-danger-text">
            <p class="font-bold">{{ __('Please review the highlighted settings and try again.') }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $publicDetailsInvalid = $errors->hasAny(['location_landmarks', 'facebook_url', 'messenger_url', 'map_url']);
    @endphp

    <form method="POST" action="{{ route('admin.settings.update') }}" class="settings-workspace" data-settings-workspace>
        @csrf
        @method('PATCH')
        <x-input-error :messages="$errors->get('settings')" />
        <div class="settings-grid">
            <div class="settings-card-stack">
            <section class="settings-card" aria-labelledby="business-identity-heading">
                <h2 id="business-identity-heading">{{ __('Business identity') }}</h2>
                <p class="settings-description">{{ __('Your spa’s basic information used across the system.') }}</p>
                <div class="settings-fields settings-identity-fields">
                    <div class="settings-field-wide">
                        <x-input-label for="business_name" :value="__('Business name')" />
                        <x-text-input id="business_name" name="business_name" type="text" :value="old('business_name', $settings->business_name)" maxlength="255" required />
                        <x-input-error :messages="$errors->get('business_name')" />
                    </div>
                    <div>
                        <x-input-label for="contact_email" :value="__('Contact email')" />
                        <x-text-input id="contact_email" name="contact_email" type="email" :value="old('contact_email', $settings->contact_email)" maxlength="255" />
                        <x-input-error :messages="$errors->get('contact_email')" />
                    </div>
                    <div>
                        <x-input-label for="contact_phone" :value="__('Phone number')" />
                        <x-text-input id="contact_phone" name="contact_phone" type="tel" :value="old('contact_phone', $settings->contact_phone)" maxlength="50" />
                        <x-input-error :messages="$errors->get('contact_phone')" />
                    </div>
                    <div class="settings-field-wide">
                        <x-input-label for="business_address" :value="__('Business address')" />
                        <textarea id="business_address" name="business_address" rows="3" maxlength="1000" class="casa-input">{{ old('business_address', $settings->business_address) }}</textarea>
                        <x-input-error :messages="$errors->get('business_address')" />
                    </div>
                </div>
                <details id="settings-public-details" class="settings-disclosure" @if($publicDetailsInvalid) open @endif>
                    <summary>{{ __('More public details') }}</summary>
                    <div class="settings-fields">
                        <div>
                            <x-input-label for="location_landmarks" :value="__('Location landmarks')" />
                            <textarea id="location_landmarks" name="location_landmarks" rows="3" maxlength="500" class="casa-input">{{ old('location_landmarks', $settings->location_landmarks) }}</textarea>
                            <p class="settings-help">{{ __('Use practical directions, not a second formal address.') }}</p>
                            <x-input-error :messages="$errors->get('location_landmarks')" />
                        </div>
                        @foreach (['messenger_url' => __('Messenger link'), 'facebook_url' => __('Facebook page link'), 'map_url' => __('Google Maps link')] as $field => $label)
                            <div>
                                <x-input-label :for="$field" :value="$label" />
                                <x-text-input :id="$field" :name="$field" type="url" :value="old($field, $settings->{$field})" maxlength="2048" />
                                <x-input-error :messages="$errors->get($field)" />
                            </div>
                        @endforeach
                    </div>
                </details>
            </section>

            <section class="settings-card" aria-labelledby="company-history-heading">
                <h2 id="company-history-heading">{{ __('Company history') }}</h2>
                <p class="settings-description">{{ __('Share a brief history of your spa for the public landing page.') }}</p>
                <label for="company_history" class="sr-only">{{ __('Company history') }}</label>
                <textarea id="company_history" name="company_history" rows="5" maxlength="6000" class="casa-input settings-history" aria-describedby="company-history-help">{{ old('company_history', $settings->company_history) }}</textarea>
                <p id="company-history-help" class="settings-help">{{ __('Plain text only. Paragraph breaks are preserved; blank history stays hidden.') }}</p>
                <x-input-error :messages="$errors->get('company_history')" />
            </section>
            </div>

            <div class="settings-card-stack">
                <section class="settings-card" aria-labelledby="settings-payments-heading">
                    <h2 id="settings-payments-heading">{{ __('Payments') }}</h2>
                    <p class="settings-description">{{ __('Set your default payment method for new transactions.') }}</p>
                    <div class="settings-fields">
                        <div>
                            <x-input-label for="default_payment_method" :value="__('Default payment method')" />
                            <select id="default_payment_method" name="default_payment_method" class="casa-input" required aria-describedby="payment-default-help">
                                @foreach (\App\Models\Transaction::PAYMENT_METHODS as $method)
                                    <option value="{{ $method }}" @selected(old('default_payment_method', $settings->default_payment_method) === $method)>{{ ucfirst(str_replace('_', ' ', $method)) }}</option>
                                @endforeach
                            </select>
                            <p id="payment-default-help" class="settings-help">{{ __('Pre-selected for new Admin and Receptionist payments. It does not mark a payment as settled.') }}</p>
                            <x-input-error :messages="$errors->get('default_payment_method')" />
                        </div>
                    </div>
                </section>
                <section class="settings-card" aria-labelledby="environment-checks-heading">
                    <h2 id="environment-checks-heading">{{ __('Environment checks') }}</h2>
                    <p class="settings-description">{{ __('Review security configuration for the current environment.') }}</p>
                    <ul class="settings-checks">
                        @foreach ($securityChecks as $check)
                            <li>
                                <span class="settings-icon-circle"><x-nav-icon name="{{ ['settings', 'profile', 'errors', 'team'][$loop->index] }}" /></span>
                                <div class="settings-check-copy">
                                    <h3>{{ $check['label'] }}</h3>
                                    <p>{{ $check['meta'] }}</p>
                                </div>
                                <span @class(['settings-status', 'settings-status--safe' => $check['tone'] === 'green', 'settings-status--review' => $check['tone'] !== 'green'])>{{ $check['value'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="settings-help">{{ __('Configuration checks only. Review the production checklist before every release.') }}</p>
                </section>
            </div>
        </div>

        <section class="settings-card settings-team-card" aria-labelledby="organization-chart-heading" data-settings-team-link>
            <span class="settings-icon-circle"><x-nav-icon name="team" /></span>
            <div>
                <h2 id="organization-chart-heading">{{ __('Public team chart') }}</h2>
                <p class="settings-description">{{ __('The homepage chart is built from staff accounts. Add photos, titles and levels on the Team page; staff can also update their own photo from My profile.') }}</p>
            </div>
            <a href="{{ route('admin.team.index') }}" class="casa-button-secondary">{{ __('Open Team page') }}</a>
        </section>

        <details class="settings-card settings-disclosure settings-operating-details">
            <summary>{{ __('Operating rules and user access') }}</summary>
            <x-stat-strip :items="[
                ['label' => __('Hours'), 'value' => config('casa.business_hours.window'), 'meta' => config('casa.business_hours.summary'), 'tone' => 'brown'],
                ['label' => __('Timezone'), 'value' => config('casa.business_hours.timezone'), 'meta' => __('Calendar authority'), 'tone' => 'dark'],
                ['label' => __('Booking intervals'), 'value' => config('casa.business_hours.slot_interval_minutes').' '.__('minutes'), 'meta' => __('Confirmed immediately'), 'tone' => 'green'],
                ['label' => __('Therapist rate'), 'value' => number_format((float) config('casa.commissions.therapist_rate') * 100, 0).'%', 'meta' => __('Snapshotted per earning'), 'tone' => 'gold'],
            ]" />
            <div class="settings-access">
                <h3>{{ __('User administration') }}</h3>
                <p>{{ auth()->user()->isSuperAdmin() ? __('Provision Receptionists, Therapists, Customers, and Administrators from the protected user workspace.') : __('Only a Super Administrator can provision accounts, change roles, or activate and deactivate users.') }}</p>
                @if (auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.users.index') }}" class="casa-button-secondary">{{ __('Manage user access') }}</a>
                @endif
            </div>
        </details>
        <div class="settings-actions">
            <button type="submit" class="casa-button-primary" @disabled(! $settingsTableAvailable)>{{ __('Save changes') }}</button>
        </div>
    </form>
</x-app-layout>
