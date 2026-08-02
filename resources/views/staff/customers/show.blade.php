<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customer detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $customer->user->name }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Operational context for appointment service and customer care.') }}
            </p>
        </div>

        <a href="{{ route('staff.customers.index') }}" class="casa-button-secondary">{{ __('Back to lookup') }}</a>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Profile') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Contact and care context') }}</h2>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Phone') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $customer->user->phone ?: __('Not set') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Preference') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $customer->contact_preference ?: __('Not set') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Address') }}</dt>
                        <dd class="mt-2 text-sm leading-6 text-casa-muted">{{ $customer->address ?: __('No address on file.') }}</dd>
                    </div>
                </dl>
            </x-app-card>

            @include('staff.customers.partials.operational-history', ['title' => __('Appointment history'), 'records' => $customer->appointments, 'type' => 'appointments'])
            @include('staff.customers.partials.operational-history', ['title' => __('Feedback history'), 'records' => $customer->feedback, 'type' => 'feedback'])
        </section>

        <aside class="space-y-4">
            <x-stat-strip :items="[
                ['label' => __('Appointments'), 'value' => $customer->appointments_count, 'meta' => __('Known visits'), 'tone' => 'brown'],
                ['label' => __('Feedback'), 'value' => $customer->feedback_count, 'meta' => __('Service reviews'), 'tone' => 'gold'],
            ]" />
            <x-app-card>
                <p class="casa-section-label">{{ __('Note') }}</p>
                <p class="mt-3 text-sm leading-6 text-casa-muted">
                    {{ __('Therapist view is operational only. User account settings and internal admin controls stay in the admin workspace.') }}
                </p>
            </x-app-card>
        </aside>
    </div>
</x-app-layout>
