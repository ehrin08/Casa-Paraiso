<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Add-on detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $addon->name }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $addon->description ?: __('Booking extra profile, added duration, and price.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.addons.index') }}" class="casa-button-secondary">{{ __('All add-ons') }}</a>
            <a data-panel-link data-turbo="false" aria-haspopup="dialog" href="{{ route('admin.addons.edit', $addon) }}" class="casa-button-primary">{{ __('Edit add-on') }}</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-stat-strip :items="[
            ['label' => __('Duration'), 'value' => $addon->duration_minutes.' min', 'meta' => __('Added booking time'), 'tone' => 'brown'],
            ['label' => __('Price'), 'value' => 'PHP '.number_format((float) $addon->price, 2), 'meta' => __('Published rate'), 'tone' => 'green'],
            ['label' => __('Bookings'), 'value' => $addon->bookings_count, 'meta' => __('Appointments with this add-on'), 'tone' => 'gold'],
            ['label' => __('Status'), 'value' => $addon->is_active ? __('Active') : __('Inactive'), 'meta' => __('Catalog visibility'), 'tone' => 'dark'],
        ]" />

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <x-app-card>
                <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="casa-section-label">{{ __('Extra profile') }}</p>
                        <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Add-on information') }}</h2>
                    </div>
                    <x-status-badge :tone="$addon->is_active ? 'success' : 'dark'">
                        {{ $addon->is_active ? __('Active') : __('Inactive') }}
                    </x-status-badge>
                </div>

                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Code') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $addon->code }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Appointments') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ trans_choice(':count record|:count records', $addon->bookings_count) }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Description') }}</dt>
                        <dd class="mt-2 text-sm leading-6 text-casa-muted">
                            {{ $addon->description ?: __('No description has been added yet.') }}
                        </dd>
                    </div>
                </dl>
            </x-app-card>

            <aside class="casa-dark-panel rounded-[20px] p-6 shadow-casa-card">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-casa-gold">{{ __('Where it appears') }}</p>
                <h2 class="mt-4 font-display text-2xl font-black text-white">{{ __('Offered on every booking.') }}</h2>
                <p class="mt-4 text-sm leading-7 text-casa-bg/80">
                    {{ __('Active add-ons can be added to any treatment. Their duration extends the appointment and their price is added to the total.') }}
                </p>
                <div class="casa-divider my-6"></div>
                <div class="space-y-3 text-sm font-semibold text-casa-bg/80">
                    <p>{{ __('Customer booking') }}</p>
                    <p>{{ __('Reception and admin appointments') }}</p>
                    <p>{{ __('RFM add-on vouchers') }}</p>
                </div>
            </aside>
        </section>
    </div>
</x-app-layout>
