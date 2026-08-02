<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Service detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $service->name }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $service->description ?: __('Treatment profile, booking duration, and future workflow connections.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.services.index') }}" class="casa-button-secondary">{{ __('All services') }}</a>
            <a href="{{ route('admin.services.edit', $service) }}" class="casa-button-primary">{{ __('Edit service') }}</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-stat-strip :items="[
            ['label' => __('Duration'), 'value' => $service->duration_minutes.' min', 'meta' => __('Booking length'), 'tone' => 'brown'],
            ['label' => __('Price'), 'value' => 'PHP '.number_format((float) $service->price, 2), 'meta' => __('Published rate'), 'tone' => 'green'],
            ['label' => __('Therapists'), 'value' => $service->staff_profiles_count, 'meta' => __('Assigned providers'), 'tone' => 'gold'],
            ['label' => __('Status'), 'value' => $service->is_active ? __('Active') : __('Inactive'), 'meta' => __('Catalog visibility'), 'tone' => 'dark'],
        ]" />

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <x-app-card>
                <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="casa-section-label">{{ __('Treatment profile') }}</p>
                        <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Service information') }}</h2>
                    </div>
                    <x-status-badge :tone="$service->is_active ? 'success' : 'dark'">
                        {{ $service->is_active ? __('Active') : __('Inactive') }}
                    </x-status-badge>
                </div>

                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Slug') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $service->slug }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Appointments') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ trans_choice(':count record|:count records', $service->appointments_count) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Description') }}</dt>
                        <dd class="mt-2 text-sm leading-6 text-casa-muted">
                            {{ $service->description ?: __('No description has been added yet.') }}
                        </dd>
                    </div>
                </dl>
            </x-app-card>

            <aside class="casa-dark-panel rounded-[24px] p-6 shadow-casa-card">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-casa-gold">{{ __('Scheduling coverage') }}</p>
                <h2 class="mt-4 font-display text-2xl font-black text-white">{{ __('Ready for therapist assignment.') }}</h2>
                <p class="mt-4 text-sm leading-7 text-casa-bg/80">
                    {{ __('Assign active therapists to this service so confirmed bookings can use their eligible schedules.') }}
                </p>
                <div class="casa-divider my-6"></div>
                <div class="space-y-3 text-sm font-semibold text-casa-bg/80">
                    <p>{{ __('Therapist assignments') }}</p>
                    <p>{{ __('Weekly schedules') }}</p>
                    <p>{{ __('Confirmed customer bookings') }}</p>
                </div>
            </aside>
        </section>
    </div>
</x-app-layout>
