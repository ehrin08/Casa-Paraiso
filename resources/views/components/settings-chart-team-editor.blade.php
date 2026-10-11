@props(['team'])
<div {{ $attributes->class(['settings-team-editor']) }} x-data="organizationChartEditor" data-initial-team="{{ json_encode($team) }}">
    <div class="settings-team-heading">
        <div>
            <h3>{{ __('Team roles') }}</h3>
            <p class="settings-help">{{ __('Add up to eight roles that report to the administrator.') }}</p>
        </div>
        <button type="button" class="casa-button-secondary" x-cloak @click="addRole" :disabled="team.length >= 8">{{ __('Add team role') }}</button>
    </div>
    <div class="settings-team-fields">
        <template x-for="(role, index) in team" :key="role.key">
            <fieldset class="settings-role-fields">
                <legend>{{ __('Team role') }} <span x-text="index + 1"></span></legend>
                <button type="button" class="settings-remove-role" @click="removeRole(index)">{{ __('Remove role') }}</button>
                <div class="settings-fields">
                    <div>
                        <label class="casa-label" :for="`organization_team_title_${role.key}`">{{ __('Role title') }}</label>
                        <input type="text" maxlength="100" class="casa-input" :id="`organization_team_title_${role.key}`" :name="`organization_chart[team][${index}][title]`" x-model="role.title">
                    </div>
                    <div>
                        <label class="casa-label" :for="`organization_team_name_${role.key}`">{{ __('Person name') }}</label>
                        <input type="text" maxlength="100" class="casa-input" :id="`organization_team_name_${role.key}`" :name="`organization_chart[team][${index}][name]`" x-model="role.name">
                    </div>
                    <div>
                        <label class="casa-label" :for="`organization_team_description_${role.key}`">{{ __('Role summary') }}</label>
                        <textarea rows="2" maxlength="300" class="casa-input" :id="`organization_team_description_${role.key}`" :name="`organization_chart[team][${index}][description]`" x-model="role.description"></textarea>
                    </div>
                    <div x-data="orgPhotoUpload(role.photoUrl)">
                        <label class="casa-label" :for="`organization_team_photo_${role.key}`">{{ __('Photo') }}</label>
                        <div class="settings-photo-input">
                            <img :src="previewUrl || undefined" x-show="previewUrl && !role.removePhoto" alt="{{ __('Photo preview') }}" width="44" height="44">
                            <input type="file" accept="image/jpeg,image/png,image/webp" class="casa-input" :id="`organization_team_photo_${role.key}`" :name="`organization_chart_photos[team][${index}]`" @change="handleFileChange($event); if ($event.target.files.length) role.removePhoto = false">
                        </div>
                        <label class="settings-remove-photo" x-show="role.photo">
                            <input type="checkbox" :name="`organization_chart_remove_photos[team][${index}]`" value="1" x-model="role.removePhoto">
                            {{ __('Remove saved photo') }}
                        </label>
                        <input type="hidden" :name="`organization_chart[team][${index}][photo]`" :value="role.photo || ''">
                    </div>
                </div>
            </fieldset>
        </template>
    </div>
    <noscript>
        <p class="settings-help">{{ __('Edit saved team roles below. Fill an empty slot to add a role; clear its title, name and summary to remove it.') }}</p>
        @foreach (range(0, 7) as $index)
            <x-settings-chart-fields :role="$team[$index] ?? []" tier="team" :index="$index" :label="__('Team role').' '.($index + 1)" />
        @endforeach
    </noscript>
    @foreach (['organization_chart.team', 'organization_chart.team.*', 'organization_chart.team.*.title', 'organization_chart.team.*.name', 'organization_chart.team.*.description', 'organization_chart.team.*.photo', 'organization_chart_photos.team.*', 'organization_chart_remove_photos.team.*'] as $field)
        <x-input-error :messages="\Illuminate\Support\Arr::flatten($errors->get($field))" />
    @endforeach
</div>
