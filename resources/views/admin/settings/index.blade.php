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

    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm leading-6 text-red-800">
            <p class="font-bold">{{ __('Please review the highlighted settings and try again.') }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <x-input-error class="mb-5" :messages="$errors->get('settings')" />

    <x-stat-strip :items="[
        ['label' => __('Hours'), 'value' => config('casa.business_hours.window'), 'meta' => config('casa.business_hours.summary'), 'tone' => 'brown'],
        ['label' => __('Timezone'), 'value' => config('casa.business_hours.timezone'), 'meta' => __('Calendar authority'), 'tone' => 'dark'],
        ['label' => __('Booking intervals'), 'value' => config('casa.business_hours.slot_interval_minutes').' '.__('minutes'), 'meta' => __('Confirmed immediately'), 'tone' => 'green'],
        ['label' => __('Therapist rate'), 'value' => number_format((float) config('casa.commissions.therapist_rate') * 100, 0).'%', 'meta' => __('Snapshotted per earning'), 'tone' => 'gold'],
    ]" class="mb-5" />

    @php
        $organizationChart = old('organization_chart', $settings->publicOrganizationChart()) ?? [];
        $organizationTeam = collect(data_get($organizationChart, 'team', []))->map(function ($role) {
            $role['photoUrl'] = \App\Models\ApplicationSetting::orgChartPhotoUrl($role['photo'] ?? null);
            return $role;
        })->values()->all();
    @endphp

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PATCH')

        <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">

            <x-app-card padding="p-4">
                <div class="border-b border-casa-border pb-4">
                    <p class="casa-section-label">{{ __('Business profile') }}</p>
                    <h2 class="mt-1.5 font-display text-xl font-black text-casa-text">{{ __('Public identity and contact details') }}</h2>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="business_name" :value="__('Business name')" />
                        <x-text-input id="business_name" name="business_name" type="text" class="mt-2" :value="old('business_name', $settings->business_name)" required autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('business_name')" />
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

                    <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
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

                    <div class="grid gap-4 sm:col-span-2 sm:grid-cols-3">
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

                </div>

                <div class="mt-4 flex justify-end border-t border-casa-border pt-4">
                    <button type="submit" class="casa-button-primary" @disabled(! $settingsTableAvailable)>{{ __('Save settings') }}</button>
                </div>
            </x-app-card>

            <aside class="space-y-4">
                <x-app-card padding="p-4">
                    <p class="casa-section-label">{{ __('Security readiness') }}</p>
                    <h2 class="mt-1.5 font-display text-xl font-black text-casa-text">{{ __('Environment checks') }}</h2>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                        @foreach ($securityChecks as $check)
                            <x-metric-card :label="$check['label']" :value="$check['value']" :meta="$check['meta']" :tone="$check['tone']" />
                        @endforeach
                    </div>
                    <p class="mt-3 text-sm leading-6 text-casa-muted">{{ __('These checks summarize configuration only. Review the documented production checklist before every release.') }}</p>
                </x-app-card>

                <x-app-card padding="p-4">
                    <p class="casa-section-label">{{ __('Access control') }}</p>
                    <h2 class="mt-1.5 font-display text-xl font-black text-casa-text">{{ __('User administration') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-casa-muted">
                        {{ auth()->user()->isSuperAdmin()
                            ? __('Provision Receptionists, Therapists, Customers, and Administrators from the protected user workspace.')
                            : __('Only a Super Administrator can provision accounts, change roles, or activate and deactivate users.') }}
                    </p>
                    @if (auth()->user()->isSuperAdmin())
                        <a href="{{ route('admin.users.index') }}" class="casa-button-secondary mt-3 w-full">{{ __('Manage user access') }}</a>
                    @endif
                </x-app-card>
            </aside>
        </div>

            <x-app-card padding="p-4">
                <div class="border-b border-casa-border pb-4">
                    <p class="casa-section-label">{{ __('Landing page story') }}</p>
                    <h2 class="mt-1.5 font-display text-xl font-black text-casa-text">{{ __('Company history and organizational chart') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-casa-muted">{{ __('Publish verified company information for visitors. If every field is blank, the About section stays hidden.') }}</p>
                </div>

                <div class="mt-4">
                    <x-input-label for="company_history" :value="__('Company history')" />
                    <textarea id="company_history" name="company_history" rows="6" maxlength="6000" class="casa-input mt-2" placeholder="{{ __('Share the verified story of Casa Paraiso, including meaningful milestones and its purpose today.') }}">{{ old('company_history', $settings->company_history) }}</textarea>
                    <p class="mt-2 text-sm leading-6 text-casa-muted">{{ __('Plain text only. Paragraph breaks are preserved on the public landing page.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('company_history')" />
                </div>

                    <div class="mt-4 border-t border-casa-border pt-4">
                    <div>
                        <p class="casa-section-label">{{ __('Reporting structure') }}</p>
                        <h3 class="mt-1.5 text-lg font-black text-casa-text">{{ __('Owner → Administrator → Team') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-casa-muted">{{ __('Use public role labels and short responsibilities. Do not enter private account details.') }}</p>
                    </div>

                    <div class="mt-4 grid gap-4 xl:grid-cols-2">
                        {{-- Owner tier --}}
                        <div class="rounded-2xl border border-casa-border bg-casa-sand/25 p-4">
                            <p class="text-sm font-black text-casa-cacao">{{ __('Owner / Leadership') }}</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="organization_owner_title" :value="__('Role title')" />
                                    <x-text-input id="organization_owner_title" name="organization_chart[owner][title]" type="text" maxlength="100" class="mt-2" :value="data_get($organizationChart, 'owner.title')" placeholder="{{ __('Owner / Super Administrator') }}" />
                                    <x-input-error class="mt-2" :messages="$errors->get('organization_chart.owner.title')" />
                                    <input type="hidden" name="organization_chart[owner][photo]" value="{{ data_get($organizationChart, 'owner.photo') }}">
                                </div>
                                <div>
                                    <x-input-label for="organization_owner_name" :value="__('Person name')" />
                                    <x-text-input id="organization_owner_name" name="organization_chart[owner][name]" type="text" maxlength="100" class="mt-2" :value="data_get($organizationChart, 'owner.name')" placeholder="{{ __('Jane Doe') }}" />
                                    <x-input-error class="mt-2" :messages="$errors->get('organization_chart.owner.name')" />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-input-label for="organization_owner_description" :value="__('Role summary')" />
                                    <textarea id="organization_owner_description" name="organization_chart[owner][description]" rows="2" maxlength="300" class="casa-input mt-2">{{ data_get($organizationChart, 'owner.description') }}</textarea>
                                    <x-input-error class="mt-2" :messages="$errors->get('organization_chart.owner.description')" />
                                </div>
                                <div class="sm:col-span-2" x-data="orgPhotoUpload('{{ \App\Models\ApplicationSetting::orgChartPhotoUrl(data_get($organizationChart, 'owner.photo')) }}')">
                                    <x-input-label :value="__('Photo')" />
                                        <div class="mt-2 flex flex-wrap items-center gap-3">
                                        <div class="relative h-12 w-12 shrink-0 overflow-hidden rounded-full border-2 border-casa-brass/50 bg-casa-sand">
                                            <template x-if="previewUrl">
                                                <img :src="previewUrl" alt="{{ __('Photo preview') }}" class="h-full w-full object-cover">
                                            </template>
                                            <template x-if="!previewUrl">
                                                <span class="flex h-full w-full items-center justify-center text-sm font-bold text-casa-muted">{{ strtoupper(substr(data_get($organizationChart, 'owner.name', data_get($organizationChart, 'owner.title', 'O')), 0, 1)) }}</span>
                                            </template>
                                        </div>
                                            <div class="min-w-0 flex-1">
                                            <input type="file" id="organization_owner_photo" name="organization_chart_photos[owner]" accept="image/jpeg,image/png,image/webp" class="casa-input text-sm" @change="handleFileChange($event)">
                                        </div>
                                        <template x-if="hasExisting && !removing">
                                            <button type="button" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-bold text-red-700 transition hover:bg-red-50" @click="removing = true">
                                                {{ __('Remove') }}
                                            </button>
                                        </template>
                                    </div>
                                    <input type="hidden" name="organization_chart_remove_photos[owner]" x-bind:value="removing ? '1' : ''">
                                    <x-input-error class="mt-2" :messages="$errors->get('organization_chart_photos.owner')" />
                                </div>
                            </div>
                        </div>

                        {{-- Admin tier --}}
                        <div class="rounded-2xl border border-casa-border bg-casa-sand/25 p-4">
                            <p class="text-sm font-black text-casa-cacao">{{ __('Administration') }}</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="organization_admin_title" :value="__('Role title')" />
                                    <x-text-input id="organization_admin_title" name="organization_chart[admin][title]" type="text" maxlength="100" class="mt-2" :value="data_get($organizationChart, 'admin.title')" placeholder="{{ __('Administrator') }}" />
                                    <x-input-error class="mt-2" :messages="$errors->get('organization_chart.admin.title')" />
                                    <input type="hidden" name="organization_chart[admin][photo]" value="{{ data_get($organizationChart, 'admin.photo') }}">
                                </div>
                                <div>
                                    <x-input-label for="organization_admin_name" :value="__('Person name')" />
                                    <x-text-input id="organization_admin_name" name="organization_chart[admin][name]" type="text" maxlength="100" class="mt-2" :value="data_get($organizationChart, 'admin.name')" placeholder="{{ __('John Smith') }}" />
                                    <x-input-error class="mt-2" :messages="$errors->get('organization_chart.admin.name')" />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-input-label for="organization_admin_description" :value="__('Role summary')" />
                                    <textarea id="organization_admin_description" name="organization_chart[admin][description]" rows="2" maxlength="300" class="casa-input mt-2">{{ data_get($organizationChart, 'admin.description') }}</textarea>
                                    <x-input-error class="mt-2" :messages="$errors->get('organization_chart.admin.description')" />
                                </div>
                                <div class="sm:col-span-2" x-data="orgPhotoUpload('{{ \App\Models\ApplicationSetting::orgChartPhotoUrl(data_get($organizationChart, 'admin.photo')) }}')">
                                    <x-input-label :value="__('Photo')" />
                                        <div class="mt-2 flex flex-wrap items-center gap-3">
                                        <div class="relative h-12 w-12 shrink-0 overflow-hidden rounded-full border-2 border-white/30 bg-casa-sand">
                                            <template x-if="previewUrl">
                                                <img :src="previewUrl" alt="{{ __('Photo preview') }}" class="h-full w-full object-cover">
                                            </template>
                                            <template x-if="!previewUrl">
                                                <span class="flex h-full w-full items-center justify-center text-sm font-bold text-casa-muted">{{ strtoupper(substr(data_get($organizationChart, 'admin.name', data_get($organizationChart, 'admin.title', 'A')), 0, 1)) }}</span>
                                            </template>
                                        </div>
                                            <div class="min-w-0 flex-1">
                                            <input type="file" id="organization_admin_photo" name="organization_chart_photos[admin]" accept="image/jpeg,image/png,image/webp" class="casa-input text-sm" @change="handleFileChange($event)">
                                        </div>
                                        <template x-if="hasExisting && !removing">
                                            <button type="button" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-bold text-red-700 transition hover:bg-red-50" @click="removing = true">
                                                {{ __('Remove') }}
                                            </button>
                                        </template>
                                    </div>
                                    <input type="hidden" name="organization_chart_remove_photos[admin]" x-bind:value="removing ? '1' : ''">
                                    <x-input-error class="mt-2" :messages="$errors->get('organization_chart_photos.admin')" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4" x-data="organizationChartEditor" data-initial-team="{{ json_encode($organizationTeam) }}">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h4 class="font-black text-casa-text">{{ __('Team roles') }}</h4>
                                <p class="mt-1 text-sm leading-6 text-casa-muted">{{ __('Add up to eight roles that report to the administrator tier.') }}</p>
                            </div>
                            <button type="button" class="casa-button-secondary" x-on:click="addRole" x-bind:disabled="team.length >= 8">{{ __('Add team role') }}</button>
                        </div>

                        <div class="mt-3 grid gap-3 2xl:grid-cols-2">
                            <template x-for="(role, index) in team" x-bind:key="index">
                                <div class="rounded-2xl border border-casa-border bg-casa-sand/35 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-black text-casa-cacao">{{ __('Team role') }} <span x-text="index + 1"></span></p>
                                        <button type="button" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-bold text-red-700 transition hover:bg-red-50" x-on:click="removeRole(index)">{{ __('Remove') }}</button>
                                    </div>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label class="casa-label" x-bind:for="`organization_team_title_${index}`">{{ __('Role title') }}</label>
                                            <input type="text" maxlength="100" class="casa-input mt-2" x-bind:id="`organization_team_title_${index}`" x-bind:name="`organization_chart[team][${index}][title]`" x-model="role.title">
                                        </div>
                                        <div>
                                            <label class="casa-label" x-bind:for="`organization_team_name_${index}`">{{ __('Person name') }}</label>
                                            <input type="text" maxlength="100" class="casa-input mt-2" x-bind:id="`organization_team_name_${index}`" x-bind:name="`organization_chart[team][${index}][name]`" x-model="role.name">
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="casa-label" x-bind:for="`organization_team_description_${index}`">{{ __('Role summary') }}</label>
                                            <textarea rows="2" maxlength="300" class="casa-input mt-2" x-bind:id="`organization_team_description_${index}`" x-bind:name="`organization_chart[team][${index}][description]`" x-model="role.description"></textarea>
                                        </div>
                                        <div class="sm:col-span-2" x-data="{ teamPreview: role.photoUrl || '', teamRemoving: false }">
                                            <label class="casa-label">{{ __('Photo') }}</label>
                                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                                <div class="relative h-10 w-10 shrink-0 overflow-hidden rounded-full border-2 border-white/30 bg-casa-sand">
                                                    <template x-if="teamPreview && !teamRemoving">
                                                        <img :src="teamPreview" alt="" class="h-full w-full object-cover">
                                                    </template>
                                                    <template x-if="!teamPreview || teamRemoving">
                                                        <span class="flex h-full w-full items-center justify-center text-xs font-bold text-casa-muted" x-text="(role.name || role.title || 'T').charAt(0).toUpperCase()"></span>
                                                    </template>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <input type="file" accept="image/jpeg,image/png,image/webp" class="casa-input text-sm" x-bind:name="`organization_chart_photos[team][${index}]`" @change="if ($event.target.files[0]) { teamPreview = URL.createObjectURL($event.target.files[0]); teamRemoving = false; }">
                                                </div>
                                                <template x-if="role.photoUrl && !teamRemoving">
                                                    <button type="button" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-bold text-red-700 transition hover:bg-red-50" @click="teamRemoving = true; teamPreview = ''">
                                                        {{ __('Remove') }}
                                                    </button>
                                                </template>
                                            </div>
                                            <input type="hidden" x-bind:name="`organization_chart[team][${index}][photo]`" x-bind:value="role.photo || ''">
                                            <input type="hidden" x-bind:name="`organization_chart_remove_photos[team][${index}]`" x-bind:value="teamRemoving ? '1' : ''">
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('organization_chart.team')" />
                        <x-input-error class="mt-2" :messages="$errors->get('organization_chart.team.*.title')" />
                        <x-input-error class="mt-2" :messages="$errors->get('organization_chart.team.*.name')" />
                        <x-input-error class="mt-2" :messages="$errors->get('organization_chart.team.*.description')" />
                        <x-input-error class="mt-2" :messages="$errors->get('organization_chart_photos.team.*')" />
                    </div>
                </div>

                <div class="mt-4 flex justify-end border-t border-casa-border pt-4">
                    <button type="submit" class="casa-button-primary" @disabled(! $settingsTableAvailable)>{{ __('Save landing content') }}</button>
                </div>
            </x-app-card>
    </form>
</x-app-layout>
