@php
    $isModal = $isModal ?? false;
    $customerNotesModal = 'admin-customer-notes-'.$customer->id;
    $notesInvalid = old('_modal') === $customerNotesModal && $errors->any();
    $noteValue = $notesInvalid ? (is_string(old('notes')) ? old('notes') : '') : $customer->notes;
@endphp

<div class="space-y-6">
    <x-stat-strip :items="[
        ['label' => __('Appointments'), 'value' => $customer->appointments_count, 'meta' => __('Booking records'), 'tone' => 'brown'],
        ['label' => __('Transactions'), 'value' => $customer->transactions_count, 'meta' => __('Payment records'), 'tone' => 'green'],
        ['label' => __('Feedback'), 'value' => $customer->feedback_count, 'meta' => __('Submitted reviews'), 'tone' => 'gold'],
        ['label' => __('Rewards'), 'value' => $customer->promotion_suggestions_count, 'meta' => __('Customer rewards'), 'tone' => 'dark'],
    ]" />

    <section class="customer-detail-layout grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6">
            <x-app-card>
                <div class="border-b border-casa-border pb-5">
                    <p class="casa-section-label">{{ __('Profile') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Contact details') }}</h2>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-casa-bg p-4">
                        <dt class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ __('Phone') }}</dt>
                        <dd class="mt-2 font-semibold text-casa-text">{{ $customer->display_phone ?: __('Not set') }}</dd>
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
                        <dd class="mt-2 break-words text-sm leading-6 text-casa-muted">{{ $customer->address ?: __('No address on file.') }}</dd>
                    </div>
                </dl>
            </x-app-card>

            @include('admin.customers.partials.history-table', ['title' => __('Appointments'), 'records' => $customer->appointments, 'type' => 'appointments'])
            @include('admin.customers.partials.history-table', ['title' => __('Transactions'), 'records' => $customer->transactions, 'type' => 'transactions'])
            @include('admin.customers.partials.history-table', ['title' => __('Feedback'), 'records' => $customer->feedback, 'type' => 'feedback'])
            @include('admin.customers.partials.history-table', ['title' => __('Customer rewards'), 'records' => $customer->promotionSuggestions, 'type' => 'promotions'])
        </div>

        <aside class="min-w-0 space-y-4">
            <x-app-card>
                <div @if ($isModal) x-data="{ editing: @js($notesInvalid) }" @endif>
                    <p class="casa-section-label">{{ __('Care notes') }}</p>
                    <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Internal note') }}</h2>
                    <p class="mt-4 whitespace-pre-line break-words text-sm leading-6 text-casa-muted" @if ($isModal) x-show="!editing" @endif>{{ $customer->notes ?: __('No internal note yet.') }}</p>
                    @if ($isModal)
                        <button type="button" class="mt-5 casa-button-primary w-full" x-ref="update" x-show="!editing" x-on:click="editing = true; $nextTick(() => $refs.notes.focus())">{{ __('Update notes') }}</button>
                        <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="mt-5" x-show="editing" x-cloak data-customer-note-form>
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="_modal" value="{{ $customerNotesModal }}">
                            <input type="hidden" name="return_to" value="index">
                            @foreach (is_array($returnFilters ?? null) ? \Illuminate\Support\Arr::only($returnFilters, ['q', 'status', 'sort', 'direction', 'page']) : [] as $key => $value)
                                @if (is_string($value) || is_int($value))
                                    <input type="hidden" name="return_filters[{{ $key }}]" value="{{ $value }}">
                                @endif
                            @endforeach
                            <x-input-label :for="'customer-notes-'.$customer->id" :value="__('Update internal note')" />
                            <textarea id="customer-notes-{{ $customer->id }}" name="notes" rows="8" maxlength="5000" class="casa-input mt-2" x-ref="notes" @if ($notesInvalid) aria-invalid="true" data-customer-note-invalid @endif>{{ $noteValue }}</textarea>
                            <x-input-error class="mt-2" :messages="$notesInvalid ? $errors->all() : []" />
                            <div class="mt-4 flex flex-wrap justify-end gap-3">
                                <button type="button" class="casa-button-secondary" x-on:click="editing = false; $refs.notes.value = @js($customer->notes ?? ''); $nextTick(() => $refs.update.focus())">{{ __('Cancel') }}</button>
                                <button type="submit" class="casa-button-primary">{{ __('Save notes') }}</button>
                            </div>
                        </form>
                    @else
                        <button type="button" class="mt-5 casa-button-primary w-full" x-data="" x-on:click="$dispatch('open-modal', '{{ $customerNotesModal }}')">{{ __('Update notes') }}</button>
                    @endif
                </div>
            </x-app-card>
        </aside>
    </section>
</div>

@unless ($isModal)
    <x-modal :name="$customerNotesModal" :show="old('_modal') === $customerNotesModal" maxWidth="2xl" focusable>
        <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="casa-modal-form p-6">@csrf @method('PATCH')
            <input type="hidden" name="_modal" value="{{ $customerNotesModal }}"><p class="casa-section-label">{{ __('Care notes') }}</p><h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Update internal note') }}</h2><textarea name="notes" rows="8" class="casa-input mt-5">{{ old('notes', $customer->notes) }}</textarea><x-input-error class="mt-2" :messages="$errors->get('notes')" /><div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end" data-modal-actions><button type="button" class="casa-button-secondary" x-on:click="$dispatch('close-modal', '{{ $customerNotesModal }}')">{{ __('Cancel') }}</button><button type="submit" class="casa-button-primary">{{ __('Save notes') }}</button></div>
        </form>
    </x-modal>
@endunless
