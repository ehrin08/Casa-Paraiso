<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customer detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $customer->user->name }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $customer->customer_code }} · {{ $customer->user->email }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.appointments.create', ['customer_profile_id' => $customer->id]) }}" class="casa-button-primary">{{ __('Add appointment') }}</a>
            <a href="{{ route('admin.customers.index') }}" class="casa-button-secondary">{{ __('All customers') }}</a>
        </div>
    </x-slot>

    @php $customerNotesModal = 'admin-customer-notes-'.$customer->id; @endphp

    <div class="space-y-6">
        <x-stat-strip :items="[
            ['label' => __('Appointments'), 'value' => $customer->appointments_count, 'meta' => __('Booking records'), 'tone' => 'brown'],
            ['label' => __('Transactions'), 'value' => $customer->transactions_count, 'meta' => __('Payment records'), 'tone' => 'green'],
            ['label' => __('Feedback'), 'value' => $customer->feedback_count, 'meta' => __('Submitted reviews'), 'tone' => 'gold'],
            ['label' => __('Rewards'), 'value' => $customer->promotion_suggestions_count, 'meta' => __('Customer rewards'), 'tone' => 'dark'],
        ]" />

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-6">
                <x-app-card>
                    <div class="border-b border-casa-border pb-5">
                        <p class="casa-section-label">{{ __('Profile') }}</p>
                        <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Contact details') }}</h2>
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
                        <div class="rounded-2xl bg-casa-bg p-4">
                            <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Birth date') }}</dt>
                            <dd class="mt-2 font-semibold text-casa-text">{{ $customer->birth_date?->format('M d, Y') ?: __('Not set') }}</dd>
                        </div>
                        <div class="rounded-2xl bg-casa-bg p-4">
                            <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('First visit') }}</dt>
                            <dd class="mt-2 font-semibold text-casa-text">{{ $customer->first_visit_at?->format('M d, Y') ?: __('Not yet') }}</dd>
                        </div>
                        <div class="rounded-2xl bg-casa-bg p-4 sm:col-span-2">
                            <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Address') }}</dt>
                            <dd class="mt-2 text-sm leading-6 text-casa-muted">{{ $customer->address ?: __('No address on file.') }}</dd>
                        </div>
                    </dl>
                </x-app-card>

                @include('admin.customers.partials.history-table', ['title' => __('Appointments'), 'records' => $customer->appointments, 'type' => 'appointments'])
                @include('admin.customers.partials.history-table', ['title' => __('Transactions'), 'records' => $customer->transactions, 'type' => 'transactions'])
                @include('admin.customers.partials.history-table', ['title' => __('Feedback'), 'records' => $customer->feedback, 'type' => 'feedback'])
                @include('admin.customers.partials.history-table', ['title' => __('Customer rewards'), 'records' => $customer->promotionSuggestions, 'type' => 'promotions'])
            </div>

            <aside class="space-y-4">
                <x-app-card>
                    <p class="casa-section-label">{{ __('Care notes') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Internal note') }}</h2>
                    <p class="mt-4 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $customer->notes ?: __('No internal note yet.') }}</p>
                    <button type="button" class="mt-5 casa-button-primary w-full" x-data="" x-on:click="$dispatch('open-modal', '{{ $customerNotesModal }}')">{{ __('Update notes') }}</button>
                </x-app-card>
            </aside>
        </section>
    </div>

    <x-modal :name="$customerNotesModal" :show="old('_modal') === $customerNotesModal" maxWidth="2xl" focusable>
        <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="casa-modal-form p-6">@csrf @method('PATCH')
            <input type="hidden" name="_modal" value="{{ $customerNotesModal }}"><p class="casa-section-label">{{ __('Care notes') }}</p><h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Update internal note') }}</h2><textarea name="notes" rows="8" class="casa-input mt-5">{{ old('notes', $customer->notes) }}</textarea><x-input-error class="mt-2" :messages="$errors->get('notes')" /><div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end" data-modal-actions><button type="button" class="casa-button-secondary" x-on:click="$dispatch('close-modal', '{{ $customerNotesModal }}')">{{ __('Cancel') }}</button><button type="submit" class="casa-button-primary">{{ __('Save notes') }}</button></div>
        </form>
    </x-modal>
</x-app-layout>
