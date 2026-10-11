@props(['role' => [], 'tier', 'label', 'index' => null])
@php
    $key = $tier === 'team' ? "team.{$index}" : $tier;
    $fieldPrefix = $tier === 'team' ? "organization_chart[team][{$index}]" : "organization_chart[{$tier}]";
    $photoField = $tier === 'team' ? "organization_chart_photos[team][{$index}]" : "organization_chart_photos[{$tier}]";
    $removeField = $tier === 'team' ? "organization_chart_remove_photos[team][{$index}]" : "organization_chart_remove_photos[{$tier}]";
    $id = 'organization_'.str_replace('.', '_', $key);
    $photoUrl = \App\Models\ApplicationSetting::orgChartPhotoUrl($role['photo'] ?? null);
@endphp
<fieldset {{ $attributes->class(['settings-role-fields']) }}>
    <legend>{{ $label }}</legend>
    <div class="settings-fields">
        @foreach (['title' => __('Role title'), 'name' => __('Person name')] as $field => $fieldLabel)
            <div>
                <x-input-label :for="$id.'_'.$field" :value="$fieldLabel" />
                <x-text-input :id="$id.'_'.$field" :name="$fieldPrefix.'['.$field.']'" type="text" maxlength="100" :value="$role[$field] ?? ''" />
                <x-input-error :messages="$errors->get('organization_chart.'.$key.'.'.$field)" />
            </div>
        @endforeach
        <div>
            <x-input-label :for="$id.'_description'" :value="__('Role summary')" />
            <textarea id="{{ $id }}_description" name="{{ $fieldPrefix }}[description]" rows="2" maxlength="300" class="casa-input">{{ $role['description'] ?? '' }}</textarea>
            <x-input-error :messages="$errors->get('organization_chart.'.$key.'.description')" />
        </div>
        <input type="hidden" name="{{ $fieldPrefix }}[photo]" value="{{ $role['photo'] ?? '' }}">
        <div x-data="orgPhotoUpload(@js($photoUrl))">
            <x-input-label :for="$id.'_photo'" :value="__('Photo')" />
            <div class="settings-photo-input">
                <img @if($photoUrl) src="{{ $photoUrl }}" @else style="display: none" @endif :src="previewUrl || undefined" x-show="previewUrl && !removing" alt="{{ __('Photo preview') }}" width="44" height="44">
                <input type="file" id="{{ $id }}_photo" name="{{ $photoField }}" accept="image/jpeg,image/png,image/webp" class="casa-input" @change="handleFileChange($event)">
            </div>
            @if (filled($role['photo'] ?? null))
                <label class="settings-remove-photo">
                    <input type="checkbox" name="{{ $removeField }}" value="1" @checked(old('organization_chart_remove_photos.'.$key)) @change="removing = $event.target.checked">
                    {{ __('Remove saved photo') }}
                </label>
            @endif
            <x-input-error :messages="$errors->get('organization_chart_photos.'.$key)" />
            <x-input-error :messages="$errors->get('organization_chart_remove_photos.'.$key)" />
        </div>
    </div>
</fieldset>
