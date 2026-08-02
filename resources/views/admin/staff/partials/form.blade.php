@php $cancelUrl = $cancelUrl ?? route('admin.staff.index'); @endphp
<form method="POST" action="{{ $action }}" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if (session('eligibility_conflicts'))
        <div class="rounded-2xl border border-red-200 bg-red-50 p-5 lg:col-span-2" role="alert">
            <p class="text-sm font-extrabold text-red-800">{{ __('Resolve these confirmed appointments before changing therapist eligibility') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (session('eligibility_conflicts') as $conflict)
                    <a href="{{ $conflict['url'] }}" class="rounded-full border border-red-200 bg-white px-3 py-2 text-xs font-extrabold text-red-800 hover:border-red-400">
                        {{ $conflict['number'] }} · {{ \Illuminate\Support\Carbon::parse($conflict['starts_at'])->format('M d, g:i A') }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="space-y-6">
        <x-app-card data-modal-actions>
            <div class="border-b border-casa-border pb-5">
                <p class="casa-section-label">{{ __('Account') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Sign-in account details') }}</h2>
            </div>

            <div class="mt-5 grid gap-5">
                <div>
                    <x-input-label for="name" :value="__('Full name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-2" :value="old('name', $staffUser->name)" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" name="email" type="email" class="mt-2" :value="old('email', $staffUser->email)" required :disabled="$method !== 'POST'" />
                        @if($method !== 'POST')<input type="hidden" name="email" value="{{ $staffUser->email }}"><p class="mt-2 text-xs text-casa-muted">Sign-in email changes are managed in User access.</p>@endif
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>

                    <div>
                        <x-input-label for="phone" :value="__('Phone')" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-2" :value="old('phone', $staffUser->phone)" />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>
                </div>

                <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-bg p-4">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staffUser->is_active)) class="mt-1 rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                    <span>
                        <span class="block text-sm font-bold text-casa-text">{{ __('Active login account') }}</span>
                        <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ __('Inactive therapists cannot sign in or access the protected therapist workspace.') }}</span>
                    </span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('is_active')" />
            </div>
        </x-app-card>

        <x-app-card>
            <div class="border-b border-casa-border pb-5">
                <p class="casa-section-label">{{ __('Profile') }}</p>
                <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Operational details') }}</h2>
            </div>

            <div class="mt-5 grid gap-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="position" :value="__('Position')" />
                        <x-text-input id="position" name="position" type="text" class="mt-2" :value="old('position', $staffProfile->position)" />
                        <x-input-error class="mt-2" :messages="$errors->get('position')" />
                    </div>

                    <div>
                        <x-input-label for="hire_date" :value="__('Hire date')" />
                        <x-text-input id="hire_date" name="hire_date" type="date" class="mt-2" :value="old('hire_date', $staffProfile->hire_date?->format('Y-m-d'))" />
                        <x-input-error class="mt-2" :messages="$errors->get('hire_date')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="specialization" :value="__('Specialization')" />
                    <x-text-input id="specialization" name="specialization" type="text" class="mt-2" :value="old('specialization', $staffProfile->specialization)" />
                    <x-input-error class="mt-2" :messages="$errors->get('specialization')" />
                </div>

                <div>
                    <x-input-label for="bio" :value="__('Bio')" />
                    <textarea id="bio" name="bio" rows="5" class="casa-input mt-2">{{ old('bio', $staffProfile->bio) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('bio')" />
                </div>

                <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-bg p-4">
                    <input type="hidden" name="is_bookable" value="0">
                    <input type="checkbox" name="is_bookable" value="1" @checked(old('is_bookable', $staffProfile->is_bookable)) class="mt-1 rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                    <span>
                        <span class="block text-sm font-bold text-casa-text">{{ __('Bookable for appointments') }}</span>
                        <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ __('Bookable therapists can be assigned schedules and confirmed appointments.') }}</span>
                    </span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('is_bookable')" />
            </div>
        </x-app-card>
    </div>

    <aside class="space-y-4">
        <x-app-card>
            <p class="casa-section-label">{{ __('Service eligibility') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Assigned treatments') }}</h2>
            <p class="mt-3 text-sm leading-6 text-casa-muted">
                {{ __('Active services are assignable. An inactive service already assigned to this therapist remains available here so existing bookings can be preserved safely.') }}
            </p>

            <div class="mt-5 space-y-3">
                @php
                    $selectedServiceIds = collect(old('service_ids', $assignedServiceIds))->map(fn ($id) => (int) $id)->all();
                @endphp
                @forelse ($services as $service)
                    <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-bg p-4">
                        <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, $selectedServiceIds, true)) class="mt-1 rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                        <span>
                            <span class="block text-sm font-bold text-casa-text">
                                {{ $service->name }}
                                @if (! $service->is_active)<span class="text-casa-muted">{{ __('(Inactive)') }}</span>@endif
                            </span>
                            <span class="mt-1 block text-sm leading-6 text-casa-muted">
                                {{ $service->duration_minutes }} {{ __('min') }} - PHP {{ number_format((float) $service->price, 2) }}
                            </span>
                        </span>
                    </label>
                @empty
                    <x-empty-state
                        title="{{ __('No active services') }}"
                        description="{{ __('Add or activate services before assigning therapist eligibility.') }}"
                    />
                @endforelse
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('service_ids')" />
            <x-input-error class="mt-2" :messages="$errors->get('service_ids.*')" />
        </x-app-card>

        <x-app-card>
            <div class="flex flex-col gap-3">
                <button type="submit" class="casa-button-primary w-full">{{ $submitLabel }}</button>
                <a href="{{ $cancelUrl }}" class="casa-button-secondary w-full">{{ __('Cancel') }}</a>
            </div>
        </x-app-card>
    </aside>
</form>
