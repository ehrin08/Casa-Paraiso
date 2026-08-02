<form method="POST" action="{{ $action }}" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-app-card>
        <div class="border-b border-casa-border pb-5">
            <p class="casa-section-label">{{ __('Recurring availability') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Shift details') }}</h2>
        </div>

        <div class="mt-5 grid gap-5">
            <div>
                <x-input-label for="day_of_week" :value="__('Day of week')" />
                <select id="day_of_week" name="day_of_week" class="casa-input mt-2" required>
                    @foreach (\App\Models\StaffWeeklySchedule::DAYS as $dayValue => $dayLabel)
                        <option value="{{ $dayValue }}" @selected((int) old('day_of_week', $weeklySchedule->day_of_week) === $dayValue)>
                            {{ $dayLabel }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('day_of_week')" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="start_time" :value="__('Start time')" />
                    <x-text-input id="start_time" name="start_time" type="time" class="mt-2" :value="old('start_time', substr((string) $weeklySchedule->start_time, 0, 5))" required />
                    <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                </div>

                <div>
                    <x-input-label for="end_time" :value="__('End time')" />
                    <x-text-input id="end_time" name="end_time" type="time" class="mt-2" :value="old('end_time', substr((string) $weeklySchedule->end_time, 0, 5))" required />
                    <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
                </div>
            </div>

            <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-sand/45 p-4">
                <input type="hidden" name="ends_next_day" value="0">
                <input type="checkbox" name="ends_next_day" value="1" @checked(old('ends_next_day', $weeklySchedule->ends_next_day ?? false)) class="mt-1 rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                <span>
                    <span class="block text-sm font-bold text-casa-text">{{ __('Ends at midnight') }}</span>
                    <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ __('Set the end time to 12:00 AM and enable this for a shift that runs through the end of the business day.') }}</span>
                </span>
            </label>
            <x-input-error class="mt-2" :messages="$errors->get('ends_next_day')" />

            <label class="flex items-start gap-3 rounded-2xl border border-casa-border bg-casa-bg p-4">
                <input type="hidden" name="is_available" value="0">
                <input type="checkbox" name="is_available" value="1" @checked(old('is_available', $weeklySchedule->is_available ?? true)) class="mt-1 rounded border-casa-border text-casa-primary shadow-sm focus:ring-casa-gold">
                <span>
                    <span class="block text-sm font-bold text-casa-text">{{ __('Available for bookings') }}</span>
                    <span class="mt-1 block text-sm leading-6 text-casa-muted">{{ __('Leave enabled for normal working hours. Disabled rows remain visible for operational planning.') }}</span>
                </span>
            </label>
            <x-input-error class="mt-2" :messages="$errors->get('is_available')" />
        </div>
    </x-app-card>

    <aside class="space-y-4">
        <x-app-card data-modal-actions>
            <p class="casa-section-label">{{ __('Overlap rule') }}</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">{{ __('Split shifts are allowed') }}</h2>
            <p class="mt-3 text-sm leading-6 text-casa-muted">
                {{ __('Create multiple rows for the same day when shifts do not overlap. Bookable shifts stay within 1:00 PM to 12:00 midnight.') }}
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
