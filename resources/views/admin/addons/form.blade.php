<div class="mx-auto max-w-3xl">
    <x-app-card>
        <form method="POST" action="{{ $addon->exists ? route('admin.addons.update', $addon) : route('admin.addons.store') }}" class="space-y-5">
            @csrf
            @if ($addon->exists)
                @method('PATCH')
            @endif

            <div>
                <x-input-label for="name" value="{{ __('Name') }}" />
                <x-text-input id="name" name="name" class="mt-2 block w-full" value="{{ old('name', $addon->name) }}" required />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="description" value="{{ __('Description') }}" />
                <textarea id="description" name="description" class="casa-input mt-2 block min-h-28 w-full">{{ old('description', $addon->description) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('description')" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="duration_minutes" value="{{ __('Duration (minutes)') }}" />
                    <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="0" max="480" step="15" class="mt-2 block w-full" value="{{ old('duration_minutes', $addon->duration_minutes) }}" required />
                    <x-input-error class="mt-2" :messages="$errors->get('duration_minutes')" />
                </div>
                <div>
                    <x-input-label for="price" value="{{ __('Price') }}" />
                    <x-text-input id="price" name="price" type="number" min="0" max="999999.99" step="0.01" class="mt-2 block w-full" value="{{ old('price', $addon->price) }}" required />
                    <x-input-error class="mt-2" :messages="$errors->get('price')" />
                </div>
            </div>

            <label class="flex items-center gap-3 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $addon->is_active)) class="rounded border-casa-border text-casa-primary focus:ring-casa-gold">
                {{ __('Available for booking') }}
            </label>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.addons.index') }}" class="casa-button-secondary" data-panel-dismiss>{{ __('Cancel') }}</a>
                <button type="submit" class="casa-button-primary">{{ __('Save add-on') }}</button>
            </div>
        </form>
    </x-app-card>
</div>
