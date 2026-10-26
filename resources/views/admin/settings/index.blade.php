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
        $organizationChart = old('organization_chart', $settings->publicOrganizationChart()) ?? [];
        $organizationTeam = collect(data_get($organizationChart, 'team', []))->map(function ($role, $index) {
            $role['photoUrl'] = \App\Models\ApplicationSetting::orgChartPhotoUrl($role['photo'] ?? null);
            $role['removePhoto'] = (bool) old("organization_chart_remove_photos.team.{$index}", false);
            return $role;
        })->values()->all();
        $savedChart = $settings->publicOrganizationChart();
        $hasSavedChart = isset($savedChart['owner']) || isset($savedChart['admin']) || $savedChart['team'] !== [];
        $publicDetailsInvalid = $errors->hasAny(['location_landmarks', 'facebook_url', 'messenger_url', 'map_url']);
        $chartInvalid = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'organization_chart'));
    @endphp

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="settings-workspace" data-settings-workspace>
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

        <section class="settings-card settings-chart-card" aria-labelledby="organization-chart-heading">
                <h2 id="organization-chart-heading">{{ __('Public organization chart') }}</h2>
                <p class="settings-description">{{ __('Your saved team structure, shown on the public landing page.') }}</p>
                @if ($hasSavedChart)
                    <ol class="settings-chart" aria-label="{{ __('Owner, Administrator and Team reporting structure') }}" tabindex="0" data-settings-chart-preview>
                        @foreach (['owner' => __('Owner'), 'admin' => __('Administrator')] as $tier => $label)
                            @if (isset($savedChart[$tier]))
                                <li class="settings-chart-tier">
                                    <p class="settings-chart-tier-label">{{ $label }}</p>
                                    <x-settings-chart-node :role="$savedChart[$tier]" :label="$label" />
                                </li>
                            @endif
                        @endforeach
                        @if ($savedChart['team'] !== [])
                            <li class="settings-chart-tier settings-chart-team">
                                <p class="settings-chart-tier-label settings-chart-team-label">{{ __('Team') }}</p>
                                @foreach ($savedChart['team'] as $role)
                                    <x-settings-chart-node :role="$role" :label="__('Team role')" />
                                @endforeach
                            </li>
                        @endif
                    </ol>
                @else
                    <div class="settings-chart-empty" data-settings-chart-empty>
                        <span class="settings-icon-circle"><x-nav-icon name="team" /></span>
                        <p>{{ __('No public organization chart yet.') }}</p>
                        <p class="settings-help">{{ __('Open Edit chart to add your owner, administrator and team roles.') }}</p>
                    </div>
                @endif
                <details id="settings-chart-editor" class="settings-disclosure settings-chart-editor" @if($chartInvalid) open @endif>
                    <summary><x-nav-icon name="settings" />{{ __('Edit chart') }}</summary>
                    <dialog open class="settings-chart-dialog" data-settings-chart-dialog aria-labelledby="settings-chart-dialog-heading" aria-describedby="settings-chart-dialog-help">
                    <header class="settings-chart-dialog-header">
                        <div>
                            <h2 id="settings-chart-dialog-heading">{{ __('Edit organization chart') }}</h2>
                            <p id="settings-chart-dialog-help" class="settings-help">{{ __('Edits are kept when you close this editor. Use Save changes on the Settings page to publish them.') }}</p>
                        </div>
                        <button type="button" class="casa-button-secondary" data-settings-chart-close hidden aria-label="{{ __('Close chart editor') }}">{{ __('Close') }}</button>
                    </header>
                    <div class="settings-chart-editor-body">
                        <p class="settings-help">{{ __('Use public role labels and short responsibilities. Save changes to publish the chart; leave every field blank to hide it.') }}</p>
                        <div class="settings-chart-leadership">
                        @foreach (['owner' => __('Owner / Leadership'), 'admin' => __('Administration')] as $tier => $label)
                            <x-settings-chart-fields :role="data_get($organizationChart, $tier, [])" :tier="$tier" :label="$label" />
                        @endforeach
                        </div>
                        <x-settings-chart-team-editor :team="$organizationTeam" />
                    </div>
                    <footer class="settings-chart-dialog-footer" hidden>
                        <p class="settings-help">{{ __('Use the page’s Save changes button to publish your edits.') }}</p>
                        <button type="button" class="casa-button-primary" data-settings-chart-close>{{ __('Done') }}</button>
                    </footer>
                    </dialog>
                </details>
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
