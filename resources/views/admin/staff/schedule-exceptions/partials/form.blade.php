<form method="POST" action="{{ $action }}" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('One-off override') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Exception details') }}</h2>
        </div>

        <div class="mt-5 grid gap-5">
            <div>
                <x-input-label for="exception_date" :value="__('Date')" />
                <x-text-input id="exception_date" name="exception_date" type="date" class="mt-2" :value="old('exception_date', $scheduleException->exception_date?->format('Y-m-d'))" required />
                <x-input-error class="mt-2" :messages="$errors->get('exception_date')" />
            </div>

            <div>
                <x-input-label for="exception_type" :value="__('Type')" />
                <select id="exception_type" name="exception_type" class="casa-input mt-2" required>
                    <option value="{{ \App\Models\StaffScheduleException::TYPE_UNAVAILABLE }}" @selected(old('exception_type', $scheduleException->exception_type) === \App\Models\StaffScheduleException::TYPE_UNAVAILABLE)>
                        {{ __('Unavailable') }}
                    </option>
                    <option value="{{ \App\Models\StaffScheduleException::TYPE_AVAILABLE }}" @selected(old('exception_type', $scheduleException->exception_type) === \App\Models\StaffScheduleException::TYPE_AVAILABLE)>
                        {{ __('Available') }}
                    </option>
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('exception_type')" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="start_time" :value="__('Start time')" />
                    <x-text-input id="start_time" name="start_time" type="time" class="mt-2" :value="old('start_time', substr((string) $scheduleException->start_time, 0, 5))" />
                    <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                </div>

                <div>
                    <x-input-label for="end_time" :value="__('End time')" />
                    <x-text-input id="end_time" name="end_time" type="time" class="mt-2" :value="old('end_time', substr((string) $scheduleException->end_time, 0, 5))" />
                    <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
                </div>
            </div>

            <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-sand/45 p-4">
                <input type="hidden" name="ends_next_day" value="0">
                <input type="checkbox" name="ends_next_day" value="1" @checked(old('ends_next_day', $scheduleException->ends_next_day ?? false)) class="mt-1 rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                <span>
                    <span class="block text-sm font-bold text-casa-text">{{ __('Ends at midnight') }}</span>
                    <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ __('Use with a 12:00 AM end time for a partial-day override that reaches closing.') }}</span>
                </span>
            </label>
            <x-input-error class="mt-2" :messages="$errors->get('ends_next_day')" />

            <div>
                <x-input-label for="reason" :value="__('Reason')" />
                <textarea id="reason" name="reason" rows="5" class="casa-input mt-2">{{ old('reason', $scheduleException->reason) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('reason')" />
            </div>
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card data-modal-actions>
            <p class="casa-section-label">{{ __('Exception rules') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Full day or timed') }}</h2>
            <p class="mt-3 text-sm leading-6 text-casa-muted">
                {{ __('Unavailable exceptions may leave times blank for a full-day block. Available exceptions always need start and end times.') }}
            </p>
        </x-app-card>

        <x-app-card>
            <div class="flex flex-col gap-3">
                <button type="submit" class="casa-button-primary w-full">{{ $submitLabel }}</button>
                <a href="{{ route('admin.staff.show', $staffProfile) }}" class="casa-button-secondary w-full">{{ __('Cancel') }}</a>
            </div>
        </x-app-card>
    </aside>
</form>
