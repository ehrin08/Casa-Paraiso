@props(['user'])

<div {{ $attributes->class(['team-photo-field']) }}>
    <x-user-avatar :user="$user" class="team-photo-preview" />
    <div class="team-photo-inputs">
        <x-input-label for="photo" :value="__('Photo (optional)')" />
        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="casa-input mt-2" aria-describedby="photo-help">
        <p id="photo-help" class="mt-2 text-sm leading-6 text-casa-muted">{{ __('JPEG, PNG, or WebP up to 2 MB. It is cropped to a square from the centre, so a head-and-shoulders photo works best.') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('photo')" />
        @if ($user->photo_path)
            <label class="mt-3 flex items-start gap-3 text-sm leading-6">
                <input type="hidden" name="remove_photo" value="0">
                <input type="checkbox" name="remove_photo" value="1" @checked(old('remove_photo', false)) class="mt-1 rounded border-casa-border text-casa-primary focus:ring-casa-gold">
                <span>{{ __('Remove the current photo and show initials. Uploading a new photo replaces it.') }}</span>
            </label>
        @endif
    </div>
</div>
