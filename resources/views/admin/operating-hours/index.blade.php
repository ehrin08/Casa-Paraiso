@php
    use App\Http\Requests\Admin\OperatingHourRuleRequest;
    use App\Models\ApplicationSetting;
    use App\Models\OperatingHourRule;

    $typeOptions = [
        OperatingHourRule::TYPE_CLOSED => __('Closed all day'),
        OperatingHourRule::TYPE_SPECIAL_HOURS => __('Special hours'),
        OperatingHourRule::TYPE_PARTIAL_CLOSURE => __('Closed part of the day'),
    ];
    $endOptions = [
        OperatingHourRuleRequest::END_SAME_DAY => __('This day only'),
        OperatingHourRuleRequest::END_UNTIL => __('Until a date'),
        OperatingHourRuleRequest::END_INDEFINITE => __('Until further notice'),
    ];
    $statusClasses = [
        'open' => 'bg-casa-paper text-casa-text',
        'special' => 'bg-casa-info-bg text-casa-text',
        'partial' => 'bg-casa-warning-bg text-casa-text',
        'closed' => 'bg-casa-danger-bg text-casa-text',
    ];
    $closingOptions = ['00:00' => __('12:00 midnight')] + collect($timeOptions)->except('00:00')->all();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Settings') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Operating hours') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Set the default hours, then change them on chosen dates. Customers, reception and admins can only book inside the hours shown here.') }}</p>
        </div>
        <a href="{{ route('admin.settings.index') }}" class="casa-button-secondary">{{ __('Back to settings') }}</a>
    </x-slot>

    <div class="space-y-5" data-operating-hours>
        @if ($conflicts->isNotEmpty())
            <x-app-card>
                <h2 class="text-lg font-black text-casa-text">{{ __('Appointments in the way') }}</h2>
                <p class="mt-1 text-sm leading-6 text-casa-muted">{{ __('The change was not saved. Move or cancel these confirmed appointments first, then try again.') }}</p>
                <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3" data-operating-hours-conflicts>
                    @foreach ($conflicts as $conflict)
                        <li class="rounded-casa-control bg-casa-bg px-3 py-2">
                            <a href="{{ route('admin.appointments.show', $conflict['id']) }}" class="font-bold text-casa-primary">{{ $conflict['time'] }} · {{ $conflict['customer'] }}</a>
                            <span class="block text-casa-muted">{{ __('With :therapist', ['therapist' => $conflict['therapist']]) }}</span>
                        </li>
                    @endforeach
                </ul>
                <x-input-error class="mt-3" :messages="$errors->get('rules')" />
            </x-app-card>
        @endif

        <div class="grid gap-5 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
            <div class="space-y-5">
                <x-app-card>
                    <h2 class="text-lg font-black text-casa-text">{{ __('Default hours') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-casa-muted">{{ __('Used every day unless a change below says otherwise. Treatments must finish by closing time.') }}</p>
                    <form method="POST" action="{{ route('admin.operating-hours.defaults') }}" class="mt-4 space-y-4" data-operating-hours-defaults>
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label for="opens_at" :value="__('Opens')" />
                                <select id="opens_at" name="opens_at" class="casa-input mt-2 block w-full" required>
                                    @foreach ($timeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('opens_at', $settings->opensAt()) === $value)>{{ $value === '00:00' ? __('12:00 AM') : $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="closes_at" :value="__('Closes')" />
                                <select id="closes_at" name="closes_at" class="casa-input mt-2 block w-full" required>
                                    @foreach ($closingOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('closes_at', $settings->closesAt()) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('opens_at')" />
                        <x-input-error :messages="$errors->get('closes_at')" />
                        <button type="submit" class="casa-button-primary">{{ __('Save default hours') }}</button>
                    </form>
                </x-app-card>

                <x-app-card>
                    <h2 class="text-lg font-black text-casa-text">{{ __('Change the hours on dates') }}</h2>
                    <p class="mt-1 text-sm leading-6 text-casa-muted">{{ __('Close a day or a stretch of days, open with different hours, or close part of a day. Pick a date on the calendar to start here.') }}</p>
                    <form
                        method="POST"
                        action="{{ route('admin.operating-hours.rules.store') }}"
                        class="mt-4 space-y-4"
                        x-data="{ type: @js(old('type', OperatingHourRule::TYPE_CLOSED)), endMode: @js(old('end_mode', OperatingHourRuleRequest::END_SAME_DAY)) }"
                        data-operating-hours-rule-form
                    >
                        @csrf
                        <div>
                            <x-input-label for="rule_type" :value="__('Change')" />
                            <select id="rule_type" name="type" class="casa-input mt-2 block w-full" x-model="type" required>
                                @foreach ($typeOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type', OperatingHourRule::TYPE_CLOSED) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('type')" />
                        </div>
                        <div>
                            <x-input-label for="starts_on" :value="__('From')" />
                            <x-text-input id="starts_on" name="starts_on" type="date" class="mt-2 block w-full" value="{{ old('starts_on', $selectedDate) }}" min="{{ $today->toDateString() }}" required />
                            <x-input-error class="mt-2" :messages="$errors->get('starts_on')" />
                        </div>
                        <fieldset>
                            <legend class="text-sm font-bold text-casa-text">{{ __('Lasts') }}</legend>
                            <div class="mt-2 space-y-2">
                                @foreach ($endOptions as $value => $label)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="radio" name="end_mode" value="{{ $value }}" x-model="endMode" @checked(old('end_mode', OperatingHourRuleRequest::END_SAME_DAY) === $value)>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('end_mode')" />
                        </fieldset>
                        <div x-show="endMode === '{{ OperatingHourRuleRequest::END_UNTIL }}'" x-cloak>
                            <x-input-label for="ends_on" :value="__('Last date')" />
                            <x-text-input id="ends_on" name="ends_on" type="date" class="mt-2 block w-full" value="{{ old('ends_on') }}" min="{{ $today->toDateString() }}" />
                            <x-input-error class="mt-2" :messages="$errors->get('ends_on')" />
                        </div>
                        <p class="text-xs text-casa-muted" x-show="endMode === '{{ OperatingHourRuleRequest::END_INDEFINITE }}'" x-cloak>{{ __('It stays in place until you end it from the list.') }}</p>
                        <div class="grid grid-cols-2 gap-3" x-show="type !== '{{ OperatingHourRule::TYPE_CLOSED }}'" x-cloak>
                            <div>
                                <label for="start_time" class="casa-label">
                                    <span x-show="type === '{{ OperatingHourRule::TYPE_SPECIAL_HOURS }}'">{{ __('Opens') }}</span>
                                    <span x-show="type !== '{{ OperatingHourRule::TYPE_SPECIAL_HOURS }}'">{{ __('Closed from') }}</span>
                                </label>
                                <select id="start_time" name="start_time" class="casa-input mt-2 block w-full">
                                    <option value="">{{ __('Choose') }}</option>
                                    @foreach ($timeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('start_time') === $value)>{{ $value === '00:00' ? __('12:00 AM') : $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                            </div>
                            <div>
                                <label for="end_time" class="casa-label">
                                    <span x-show="type === '{{ OperatingHourRule::TYPE_SPECIAL_HOURS }}'">{{ __('Closes') }}</span>
                                    <span x-show="type !== '{{ OperatingHourRule::TYPE_SPECIAL_HOURS }}'">{{ __('Until') }}</span>
                                </label>
                                <select id="end_time" name="end_time" class="casa-input mt-2 block w-full">
                                    <option value="">{{ __('Choose') }}</option>
                                    @foreach ($closingOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('end_time') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="reason" :value="__('Reason')" />
                            <x-text-input id="reason" name="reason" class="mt-2 block w-full" value="{{ old('reason') }}" maxlength="255" placeholder="{{ __('For example: All Saints Day') }}" required />
                            <p class="mt-1 text-xs text-casa-muted">{{ __('Customers see this reason on the booking calendar for closed dates.') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('reason')" />
                        </div>
                        <p class="text-xs text-casa-muted">{{ __('A change that would leave confirmed appointments outside opening hours is not saved. You will see which ones to move first.') }}</p>
                        <button type="submit" class="casa-button-primary">{{ __('Save change') }}</button>
                    </form>
                </x-app-card>
            </div>

            <div class="space-y-5">
                <x-app-card>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-black text-casa-text">{{ $month->format('F Y') }}</h2>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.operating-hours.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="casa-button-secondary" aria-label="{{ __('Previous month') }}">&larr;</a>
                            <a href="{{ route('admin.operating-hours.index') }}" class="casa-button-secondary">{{ __('This month') }}</a>
                            <a href="{{ route('admin.operating-hours.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="casa-button-secondary" aria-label="{{ __('Next month') }}">&rarr;</a>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-7 gap-1 text-center text-xs font-black uppercase tracking-[0.1em] text-casa-muted" aria-hidden="true">
                        @foreach ([__('Sun'), __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')] as $weekday)
                            <span>{{ $weekday }}</span>
                        @endforeach
                    </div>
                    <ol class="mt-1 grid grid-cols-7 gap-1" data-operating-hours-calendar>
                        @foreach ($calendarDays as $day)
                            <li @class(['min-h-20 rounded-casa-control border border-casa-border p-1.5 text-left text-xs sm:min-h-24', $statusClasses[$day['status']], 'opacity-40' => ! $day['in_month'] || $day['past'], 'ring-2 ring-casa-primary' => $day['date'] === $selectedDate]) data-hours-date="{{ $day['date'] }}" data-hours-status="{{ $day['status'] }}">
                                @if ($day['past'])
                                    <span class="block font-black">{{ $day['day'] }}</span>
                                @else
                                    <a href="{{ route('admin.operating-hours.index', ['month' => $month->format('Y-m'), 'date' => $day['date']]) }}" class="block font-black hover:text-casa-primary" aria-label="{{ __('Change hours on :date', ['date' => \Illuminate\Support\Carbon::parse($day['date'])->format('F j')]) }}">{{ $day['day'] }}</a>
                                @endif
                                <span class="mt-1 block font-bold leading-4">{{ $day['label'] }}</span>
                                @if ($day['note'])
                                    <span class="mt-0.5 hidden leading-4 sm:block" title="{{ $day['note'] }}">{{ \Illuminate\Support\Str::limit($day['note'], 40) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                    <ul class="mt-3 flex flex-wrap gap-3 text-xs text-casa-muted">
                        @foreach (['open' => __('Default hours'), 'special' => __('Special hours'), 'partial' => __('Closed part of the day'), 'closed' => __('Closed')] as $status => $label)
                            <li class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded border border-casa-border {{ $statusClasses[$status] }}"></span>{{ $label }}</li>
                        @endforeach
                    </ul>
                </x-app-card>

                <x-app-card>
                    <h2 class="text-lg font-black text-casa-text">{{ __('Current and upcoming changes') }}</h2>
                    <div class="mt-4">
                        @if ($rules->isEmpty())
                            <x-empty-state title="{{ __('No changes') }}" description="{{ __('The spa keeps its default hours, :hours, every day.', ['hours' => $settings->hoursLabel()]) }}" />
                        @else
                            <x-table-shell label="{{ __('Scrollable operating hour changes') }}">
                                <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                                    <tr>
                                        <th class="px-4 py-3" scope="col">{{ __('Change') }}</th>
                                        <th class="px-4 py-3" scope="col">{{ __('Dates') }}</th>
                                        <th class="px-4 py-3" scope="col">{{ __('Reason') }}</th>
                                        <th class="px-4 py-3" scope="col">{{ __('Added by') }}</th>
                                        <th class="px-4 py-3" scope="col">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-casa-border text-sm">
                                    @foreach ($rules as $rule)
                                        @php
                                            $started = $rule->starts_on->lt($today);
                                            $dates = match (true) {
                                                $rule->ends_on === null => __(':from, until further notice', ['from' => $rule->starts_on->format('D, M j, Y')]),
                                                $rule->ends_on->isSameDay($rule->starts_on) => $rule->starts_on->format('D, M j, Y'),
                                                default => __(':from to :to', ['from' => $rule->starts_on->format('M j, Y'), 'to' => $rule->ends_on->format('M j, Y')]),
                                            };
                                        @endphp
                                        <tr class="casa-table-row" data-operating-hour-rule="{{ $rule->id }}">
                                            <td class="px-4 py-4">
                                                <span class="font-bold text-casa-text">{{ $rule->typeLabel() }}</span>
                                                @if ($rule->isTimed())
                                                    <span class="block text-casa-muted">{{ ApplicationSetting::hoursRangeLabel((string) $rule->start_time, (string) $rule->end_time) }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 font-bold text-casa-text">{{ $dates }}</td>
                                            <td class="px-4 py-4">{{ $rule->reason }}</td>
                                            <td class="px-4 py-4 text-casa-muted">{{ $rule->creator?->name ?? '—' }}</td>
                                            <td class="px-4 py-4">
                                                @if ($started)
                                                    <x-confirm-action :action="route('admin.operating-hours.rules.end', $rule)" method="PATCH" :label="__('End')" :confirm-title="__('End this change?')" :confirm-message="__('It stops from today, and the default hours apply again. Past days stay on record.')" :confirm-button="__('End')" />
                                                @else
                                                    <x-confirm-action :action="route('admin.operating-hours.rules.destroy', $rule)" method="DELETE" :label="__('Remove')" :confirm-title="__('Remove this change?')" :confirm-message="__('The default hours apply on these dates again.')" :confirm-button="__('Remove')" />
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-table-shell>
                            <div class="mt-5">{{ $rules->links() }}</div>
                        @endif
                    </div>
                </x-app-card>
            </div>
        </div>
    </div>
</x-app-layout>
