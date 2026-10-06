<x-app-layout>
    @php
        $allDates = isset($filters['all_dates']);
        $date = $filters['date'] ?? null;
        $subtitle = $date ? \Carbon\CarbonImmutable::parse($date, 'Asia/Manila')->format('l, F j, Y') : ($allDates ? __('All dates') : __('Attendance history'));
        $activeFilters = count($filters) - (int) $allDates;
        $selectionQuery = $filters + ['page' => $records->currentPage()];
        $clearUrl = route('admin.attendance.index', ['all_dates' => 1]);
        $initials = fn ($name) => collect(preg_split('/\s+/u', trim($name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
        $state = fn ($record) => $record->time_out_at ? ['completed', __('Completed')] : ($record->time_in_at ? ['duty', __('On duty')] : ['unrecorded', __('Not clocked in')]);
        $sourceLabel = fn ($source) => match ($source) {
            'auto_verified_qr' => __('Verified QR scan'),
            'admin_correction' => __('Administrator correction'),
            default => $source ? ucfirst(str_replace('_', ' ', $source)) : __('Unavailable'),
        };
    @endphp

    <x-slot name="header">
        <div class="attendance-heading">
            <h1 class="font-display text-3xl font-black text-casa-text">{{ __('Attendance') }}</h1>
            <p>{{ $subtitle }} · {{ __('Asia/Manila') }}</p>
        </div>
        <a href="{{ route('admin.attendance.station') }}" class="casa-button-primary attendance-station">
            <x-nav-icon name="add" />{{ __('Attendance station') }}
        </a>
    </x-slot>

    <div class="attendance-workspace" data-attendance-workspace>
        <x-list-toolbar :active-filters="$activeFilters" :collapsible="true" class="attendance-toolbar casa-list-toolbar--filters">
            <form method="GET" action="{{ route('admin.attendance.index') }}" class="attendance-filter-form" x-data="{ allDates: {{ $allDates ? 'true' : 'false' }} }">
                <div class="attendance-field attendance-date-field">
                    <label for="attendance-date">{{ __('Date') }}</label>
                    <div class="attendance-date-controls">
                        <input id="attendance-date" type="date" name="date" value="{{ $date ?? ($allDates ? now('Asia/Manila')->toDateString() : '') }}" class="casa-input" x-bind:disabled="allDates">
                        <label class="attendance-all-dates"><input type="checkbox" name="all_dates" value="1" @checked($allDates) x-model="allDates">{{ __('All dates') }}</label>
                    </div>
                    @foreach (['date_from', 'date_to'] as $rangeField)
                        @if (isset($filters[$rangeField]))<input type="hidden" name="{{ $rangeField }}" value="{{ $filters[$rangeField] }}">@endif
                    @endforeach
                    @if (isset($filters['date_from']) || isset($filters['date_to']))
                        <p class="attendance-help">{{ __('Existing range: :from through :to', ['from' => $filters['date_from'] ?? __('earliest'), 'to' => $filters['date_to'] ?? __('latest')]) }}</p>
                    @endif
                    <x-input-error :messages="$errors->get('date')" />
                </div>
                <div class="attendance-field">
                    <label for="attendance-therapist">{{ __('Therapist') }}</label>
                    <select id="attendance-therapist" name="staff_profile_id" class="casa-input">
                        <option value="">{{ __('All therapists') }}</option>
                        @foreach ($staffProfiles as $staff)
                            <option value="{{ $staff->id }}" @selected(($filters['staff_profile_id'] ?? null) == $staff->id)>{{ $staff->user?->name ?: __('Therapist unavailable') }}{{ $staff->trashed() ? ' ('.__('Archived').')' : '' }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('staff_profile_id')" />
                </div>
                <div class="attendance-field">
                    <label for="attendance-status">{{ __('Attendance status') }}</label>
                    <select id="attendance-status" name="status" class="casa-input">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="open" @selected(($filters['status'] ?? null) === 'open')>{{ __('On duty') }}</option>
                        <option value="closed" @selected(($filters['status'] ?? null) === 'closed')>{{ __('Completed') }}</option>
                    </select>
                </div>
                <div class="attendance-filter-actions">
                    <a href="{{ $clearUrl }}" class="casa-button-secondary">{{ __('Clear filters') }}</a>
                    <button type="submit" class="casa-button-primary">{{ __('Apply filters') }}</button>
                </div>
            </form>
        </x-list-toolbar>
        <noscript><style>.attendance-toolbar [data-filter-panel] { display: block; } .attendance-toolbar [data-filter-toggle] { display: none; }</style></noscript>

        <div @class(['attendance-grid', 'attendance-grid--single' => ! $selectedRecord])>
            <section class="attendance-records" aria-labelledby="attendance-records-heading">
                <div class="attendance-records-heading"><h2 id="attendance-records-heading">{{ __('Attendance records') }}</h2><p>{{ trans_choice(':count record|:count records', $records->total()) }}</p></div>
                @if ($records->isEmpty())
                    <x-empty-state title="{{ __('No attendance records') }}" description="{{ __('Try another date or therapist, or wait for the first verified scan.') }}">
                        <x-slot name="action"><a href="{{ $clearUrl }}" class="casa-button-secondary">{{ __('View all dates') }}</a></x-slot>
                    </x-empty-state>
                @else
                    <x-table-shell class="attendance-table" label="{{ __('Scrollable therapist attendance records') }}">
                        <thead><tr>
                            @foreach ([__('Therapist'), __('Date'), __('Time in'), __('Time out'), __('Status'), __('Actions')] as $label)<th scope="col">{{ $label }}</th>@endforeach
                        </tr></thead>
                        <tbody>
                            @foreach ($records as $record)
                                @php
                                    $name = $record->staffProfile?->user?->name ?: __('Therapist unavailable');
                                    $selectionUrl = route('admin.attendance.index', $selectionQuery + ['selected' => $record->id]);
                                    $isSelected = $selectedRecord?->id === $record->id;
                                    [$tone, $statusLabel] = $state($record);
                                @endphp
                                <tr @class(['attendance-row', 'is-selected' => $isSelected]) data-attendance-id="{{ $record->id }}">
                                    <td><a href="{{ $selectionUrl }}#attendance-detail" class="attendance-person" @if ($isSelected) aria-current="true" @endif>
                                        <span class="attendance-avatar" aria-hidden="true">{{ $initials($name) }}</span>
                                        <span class="attendance-person-copy"><span class="attendance-name">{{ $name }}</span><span class="attendance-email">{{ $record->staffProfile?->user?->email ?: '—' }}</span></span>
                                    </a></td>
                                    <td class="attendance-date"><time datetime="{{ $record->attendance_date?->toDateString() }}">{{ $record->attendance_date?->format('M j, Y') ?? '—' }}</time></td>
                                    <td class="attendance-time">{{ $record->time_in_at?->timezone('Asia/Manila')->format('g:i A') ?? '—' }}</td>
                                    <td class="attendance-time">{{ $record->time_out_at?->timezone('Asia/Manila')->format('g:i A') ?? '—' }}</td>
                                    <td><span class="attendance-status attendance-status--{{ $tone }}"><span aria-hidden="true"></span>{{ $statusLabel }}</span></td>
                                    <td><a href="{{ $selectionUrl }}#attendance-correction" class="attendance-correct" aria-label="{{ __('Correct attendance for :name on :date', ['name' => $name, 'date' => $record->attendance_date?->format('M j, Y')]) }}">{{ __('Correct record') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                    <div class="attendance-pagination">{{ $records->links() }}</div>
                @endif
            </section>

            @if ($selectedRecord)
                @php
                    $name = $selectedRecord->staffProfile?->user?->name ?: __('Therapist unavailable');
                    [$tone, $statusLabel] = $state($selectedRecord);
                    $latestEvent = $selectedRecord->events->first();
                    $hasOldInput = filter_var(old('_attendance_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === $selectedRecord->id;
                    $defaultAction = $selectedRecord->time_in_at && ! $selectedRecord->time_out_at ? 'time_out' : 'time_in';
                    $oldAction = $hasOldInput ? old('action', $defaultAction) : $defaultAction;
                    $action = in_array($oldAction, ['time_in', 'time_out'], true) ? $oldAction : $defaultAction;
                    $times = ['time_in' => $selectedRecord->time_in_at?->timezone('Asia/Manila')->format('Y-m-d\TH:i') ?? '', 'time_out' => $selectedRecord->time_out_at?->timezone('Asia/Manila')->format('Y-m-d\TH:i') ?? ''];
                    $occurredAt = $hasOldInput && is_string(old('occurred_at')) ? old('occurred_at') : $times[$action];
                    $reasonCode = $hasOldInput && is_string(old('reason_code')) ? old('reason_code') : '';
                    $details = $hasOldInput && is_string(old('details')) ? old('details') : '';
                    $formState = ['action' => $action, 'times' => $times, 'reasonCode' => $reasonCode, 'details' => $details];
                    $cancelUrl = route('admin.attendance.index', $selectionQuery + ['selected' => $selectedRecord->id, 'reset' => \Illuminate\Support\Str::uuid()->toString()]);
                @endphp
                <aside id="attendance-detail" class="attendance-detail" aria-labelledby="attendance-detail-heading" data-selected-attendance="{{ $selectedRecord->id }}">
                    <div class="attendance-detail-heading">
                        <h2 id="attendance-detail-heading">{{ __('Selected record') }}</h2>
                        <a href="{{ route('admin.attendance.index', $selectionQuery + ['selected' => 0]) }}#attendance-records-heading" class="attendance-close" aria-label="{{ __('Close selected attendance record') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></a>
                    </div>
                    <div class="attendance-detail-person">
                        <span class="attendance-avatar" aria-hidden="true">{{ $initials($name) }}</span>
                        <div><h3>{{ $name }}</h3><p>{{ $selectedRecord->staffProfile?->user?->email ?: '—' }}</p></div>
                    </div>
                    <dl class="attendance-summary">
                        <dt>{{ __('Date') }}</dt><dd>{{ $selectedRecord->attendance_date?->format('F j, Y') ?? '—' }}</dd>
                        <dt>{{ __('Time in') }}</dt><dd>{{ $selectedRecord->time_in_at?->timezone('Asia/Manila')->format('g:i A') ?? '—' }}</dd>
                        <dt>{{ __('Time out') }}</dt><dd>{{ $selectedRecord->time_out_at?->timezone('Asia/Manila')->format('g:i A') ?? '—' }}</dd>
                        <dt>{{ __('Status') }}</dt><dd><span class="attendance-status attendance-status--{{ $tone }}"><span aria-hidden="true"></span>{{ $statusLabel }}</span></dd>
                        <dt>{{ __('Recorded by') }}</dt><dd>{{ $latestEvent?->recorder?->name ?: __('Unavailable') }}@if ($latestEvent)<span class="attendance-source">{{ $sourceLabel($latestEvent->source) }}</span>@endif</dd>
                    </dl>
                    <form id="attendance-correction" method="POST" action="{{ route('admin.attendance.correct', $selectedRecord) }}" class="attendance-correction" x-data="{{ \Illuminate\Support\Js::from($formState) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="_attendance_id" value="{{ $selectedRecord->id }}">
                        <input type="hidden" name="return_to" value="index">
                        @foreach ($selectionQuery as $key => $value)<input type="hidden" name="return_filters[{{ $key }}]" value="{{ $value }}">@endforeach
                        <h3>{{ __('Correct attendance record') }}</h3>
                        @if ($hasOldInput && $errors->any())
                            <div class="attendance-errors" role="alert"><p>{{ __('The correction could not be saved. Review the fields below.') }}</p>@foreach ($errors->get('return_filters.*') as $messages)@foreach ($messages as $message)<p>{{ $message }}</p>@endforeach @endforeach</div>
                        @endif
                        <div class="attendance-field">
                            <label for="attendance-action">{{ __('Correct') }}</label>
                            <select id="attendance-action" name="action" class="casa-input" x-model="action" x-on:change="$refs.correctedTime.value = times[$event.target.value]">
                                <option value="time_in" @selected($action === 'time_in')>{{ __('Time in') }}</option>
                                <option value="time_out" @selected($action === 'time_out')>{{ __('Time out') }}</option>
                            </select>
                            @if ($hasOldInput)<x-input-error :messages="$errors->get('action')" />@endif
                        </div>
                        <div class="attendance-field">
                            <label for="attendance-occurred-at">{{ __('Corrected date and time') }}</label>
                            <input id="attendance-occurred-at" name="occurred_at" type="datetime-local" value="{{ $occurredAt }}" class="casa-input" required x-ref="correctedTime" aria-describedby="attendance-time-help" min="{{ $selectedRecord->attendance_date?->format('Y-m-d') }}T00:00" max="{{ $selectedRecord->attendance_date?->format('Y-m-d') }}T23:59">
                            <p id="attendance-time-help" class="attendance-help">{{ __('Use the attendance date and Asia/Manila time.') }}</p>
                            @if ($hasOldInput)<x-input-error :messages="$errors->get('occurred_at')" />@endif
                        </div>
                        <div class="attendance-field">
                            <label for="attendance-reason">{{ __('Correction reason') }}</label>
                            <select id="attendance-reason" name="reason_code" class="casa-input" required x-model="reasonCode">
                                <option value="">{{ __('Select reason') }}</option>
                                @foreach ($correctionReasons as $code => $label)<option value="{{ $code }}" @selected($reasonCode === $code)>{{ __($label) }}</option>@endforeach
                            </select>
                            @if ($hasOldInput)<x-input-error :messages="$errors->get('reason_code')" /><x-input-error :messages="$errors->get('reason')" />@endif
                        </div>
                        <div class="attendance-field">
                            <label for="attendance-details">{{ __('Additional details (required for Other)') }}</label>
                            <textarea id="attendance-details" name="details" class="casa-input" rows="3" maxlength="300" placeholder="{{ __('Add details about the correction.') }}" x-model="details" x-bind:required="reasonCode === 'other'" @required($reasonCode === 'other') aria-describedby="attendance-details-limit">{{ $details }}</textarea>
                            <p id="attendance-details-limit" class="attendance-character-count"><span x-text="details.length">{{ mb_strlen($details) }}</span>/300</p>
                            @if ($hasOldInput)<x-input-error :messages="$errors->get('details')" />@endif
                        </div>
                        <div class="attendance-saved-record">
                            <h4>{{ __('Current saved record') }}</h4>
                            <dl>
                                <dt>{{ __('Time in') }}</dt><dd>{{ $selectedRecord->time_in_at?->timezone('Asia/Manila')->format('M j, Y, g:i A') ?? '—' }}</dd>
                                <dt>{{ __('Time out') }}</dt><dd>{{ $selectedRecord->time_out_at?->timezone('Asia/Manila')->format('M j, Y, g:i A') ?? '—' }}</dd>
                                <dt>{{ __('Status') }}</dt><dd>{{ $statusLabel }}</dd>
                            </dl>
                        </div>
                        <div class="attendance-correction-actions">
                            <a href="{{ $cancelUrl }}#attendance-detail" class="casa-button-secondary">{{ __('Cancel') }}</a>
                            <button type="submit" class="casa-button-primary">{{ __('Submit correction') }}</button>
                        </div>
                    </form>
                    <details class="attendance-activity">
                        <summary>{{ __('Attendance activity (:count)', ['count' => $selectedRecord->events->count()]) }}</summary>
                        @if ($selectedRecord->events->isEmpty())
                            <p class="attendance-help">{{ __('No recorded events are available for this record.') }}</p>
                        @else
                            <ol>
                                @foreach ($selectedRecord->events as $event)
                                    <li><p class="attendance-event-title">{{ $event->event_type === 'time_in' ? __('Time in') : __('Time out') }} · {{ $event->occurred_at?->timezone('Asia/Manila')->format('M j, Y, g:i A') }}</p><p>{{ $event->recorder?->name ?: __('Unavailable') }} · {{ $sourceLabel($event->source) }}</p>@if ($event->reason)<p class="attendance-event-reason">{{ $event->reason }}</p>@endif</li>
                                @endforeach
                            </ol>
                        @endif
                    </details>
                </aside>
            @endif
        </div>
    </div>
</x-app-layout>
