<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Team page') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Edit team card') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __(':name’s photo, title and place on the homepage chart. Sign-in details stay in User accounts.', ['name' => $member->name]) }}
            </p>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('admin.team.update', $member) }}" enctype="multipart/form-data" data-turbo="false" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" data-team-card-form>
        @csrf
        @method('PATCH')

        <x-app-card>
            <div class="border-b border-casa-border pb-5">
                <p class="casa-section-label">{{ $member->roleLabel() }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ $member->name }}</h2>
            </div>

            <div class="mt-5 grid gap-5">
                <x-team-photo-field :user="$member" />

                <div>
                    <x-input-label for="public_title" :value="__('Public title')" />
                    <x-text-input id="public_title" name="public_title" type="text" class="mt-2" :value="old('public_title', $member->public_title)" maxlength="80" :placeholder="$member->roleLabel()" aria-describedby="public-title-help" />
                    <p id="public-title-help" class="mt-2 text-sm leading-6 text-casa-muted">{{ __('For example “Senior Therapist”. Leave blank to show :role.', ['role' => $member->roleLabel()]) }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('public_title')" />
                </div>

                <div>
                    <x-input-label for="public_bio" :value="__('Short introduction (optional)')" />
                    <textarea id="public_bio" name="public_bio" rows="3" maxlength="280" class="casa-input mt-2">{{ old('public_bio', $member->public_bio) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('public_bio')" />
                </div>
            </div>
        </x-app-card>

        <aside class="space-y-4">
            <x-app-card>
                <h2 class="font-display text-xl font-black text-casa-text">{{ __('Homepage chart') }}</h2>

                <label class="mt-4 flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-bg p-4">
                    <input type="hidden" name="show_on_org_chart" value="0">
                    <input type="checkbox" name="show_on_org_chart" value="1" @checked(old('show_on_org_chart', $member->show_on_org_chart)) class="mt-1 rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                    <span>
                        <span class="block text-sm font-bold text-casa-text">{{ __('Show on the website') }}</span>
                        <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ __('Inactive accounts are always hidden.') }}</span>
                    </span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('show_on_org_chart')" />

                <div class="mt-4">
                    <x-input-label for="org_chart_tier" :value="__('Chart level')" />
                    <select id="org_chart_tier" name="org_chart_tier" class="casa-input mt-2">
                        <option value="">{{ __('Not placed') }}</option>
                        @foreach ([\App\Models\User::ORG_TIER_OWNER => __('Owner'), \App\Models\User::ORG_TIER_LEADERSHIP => __('Leadership'), \App\Models\User::ORG_TIER_TEAM => __('Team')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('org_chart_tier', $member->org_chart_tier) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('org_chart_tier')" />
                </div>

                <div class="mt-4">
                    <x-input-label for="org_chart_order" :value="__('Order within level')" />
                    <x-text-input id="org_chart_order" name="org_chart_order" type="number" min="0" max="999" class="mt-2" :value="old('org_chart_order', $member->org_chart_order)" aria-describedby="org-order-help" />
                    <p id="org-order-help" class="mt-2 text-sm leading-6 text-casa-muted">{{ __('Lower numbers come first; equal numbers sort by name.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('org_chart_order')" />
                </div>
            </x-app-card>

            <x-app-card>
                <div class="flex flex-col gap-3">
                    <button type="submit" class="casa-button-primary w-full">{{ __('Save team card') }}</button>
                    <a href="{{ route('admin.team.index') }}" class="casa-button-secondary w-full" data-panel-dismiss>{{ __('Cancel') }}</a>
                </div>
            </x-app-card>
        </aside>
    </form>
</x-app-layout>
