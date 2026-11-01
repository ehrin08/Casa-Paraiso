@php $cancelUrl = $cancelUrl ?? route('admin.addons.index'); @endphp
<form method="POST" action="{{ $action }}" data-turbo="false" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Add-on details') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Extra information') }}</h2>
        </div>

        <div class="mt-5 grid gap-5">
            <div>
                <x-input-label for="name" :value="__('Add-on name')" />
                <x-text-input id="name" name="name" type="text" class="mt-2" :value="old('name', $addon->name)" required autofocus />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="description" :value="__('Description')" />
                <textarea id="description" name="description" rows="5" class="casa-input mt-2">{{ old('description', $addon->description) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('description')" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="duration_minutes" :value="__('Duration minutes')" />
                    <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="0" max="480" step="15" class="mt-2" :value="old('duration_minutes', $addon->duration_minutes)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('duration_minutes')" />
                </div>

                <div>
                    <x-input-label for="price" :value="__('Price')" />
                    <x-text-input id="price" name="price" type="number" min="0" max="999999.99" step="0.01" class="mt-2" :value="old('price', $addon->price)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('price')" />
                </div>
            </div>

            <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-bg p-4">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $addon->is_active)) class="mt-1 rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                <span>
                    <span class="block text-sm font-bold text-casa-text">{{ __('Active in catalog') }}</span>
                    <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ __('Active add-ons can be chosen when customers and staff book appointments.') }}</span>
                </span>
            </label>
            <x-input-error class="mt-2" :messages="$errors->get('is_active')" />
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card data-modal-actions>
            <p class="casa-section-label">{{ __('Generated fields') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Code is automatic') }}</h2>
            <p class="mt-3 text-sm leading-6 text-casa-muted">
                {{ __('The system generates a unique code from the add-on name when it is created. The code stays the same after renaming so booked appointments and add-on vouchers keep matching.') }}
            </p>
            @if ($addon->exists)
                <p class="mt-3 text-sm font-semibold text-casa-text">{{ $addon->code }}</p>
            @endif
        </x-app-card>

        <x-app-card>
            <div class="flex flex-col gap-3">
                <button type="submit" class="casa-button-primary w-full">{{ $submitLabel }}</button>
                <a href="{{ $cancelUrl }}" class="casa-button-secondary w-full" data-panel-dismiss>{{ __('Cancel') }}</a>
            </div>
        </x-app-card>
    </aside>
</form>
