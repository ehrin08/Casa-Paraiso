<div class="schedules-cell">
    @forelse ($cell['ranges'] as $range)
        @php $rangeLabel = \Illuminate\Support\Carbon::parse($range['start_time'])->format('g:i A').' – '.($range['ends_next_day'] ? __('12:00 midnight') : \Illuminate\Support\Carbon::parse($range['end_time'])->format('g:i A')); @endphp
        <div class="schedules-shift">
            @if ($cell['can_add'])
                <a href="{{ route('admin.staff-roster.shifts.edit', [$roster, $range['id'], ...$filters]) }}" data-schedule-editor data-no-loading aria-label="{{ __('Edit :hours for :name on :day', ['hours' => $rangeLabel, 'name' => $person->user->name, 'day' => $day['heading']]) }}">{{ $rangeLabel }} <span>{{ __('Edit') }}</span></a>
            @else
                <span>{{ $rangeLabel }}</span>
            @endif
            @if ($cell['can_edit'])
                <div class="schedules-remove">
                    <x-confirm-action :action="route('admin.staff-roster.shifts.destroy', [$roster, $range['id']])" method="DELETE" :label="__('Remove')" :confirm-title="__('Remove this shift?')" :confirm-message="__('Remove :hours for :name on :day from the draft. Nothing changes for customers until the draft is published.', ['hours' => $rangeLabel, 'name' => $person->user->name, 'day' => $day['heading']])" :confirm-button="__('Remove shift')" :dismiss-label="__('Keep shift')" button-class="schedules-remove-trigger" tone="danger">
                        @include('admin.staff-schedules.partials.context')
                    </x-confirm-action>
                </div>
            @endif
        </div>
    @empty
        <p class="schedules-empty-hours">{{ __('No roster hours') }}</p>
    @endforelse
    @if ($cell['can_add'])
        <a class="schedules-add" href="{{ route('admin.staff-roster.shifts.create', [$roster, ...$filters, 'therapist' => $person->id, 'schedule_date' => $day['date']]) }}" data-schedule-editor data-no-loading>{{ __('+ Add shift') }}</a>
    @endif
    @foreach ($cell['exceptions'] as $exception)
        <a class="schedules-exception" href="{{ route('admin.staff.schedule-exceptions.edit', [$person, $exception]) }}">
            {{ $exception->exception_type === 'unavailable' ? __('Blocked') : __('Extra hours') }}:
            @if ($exception->start_time && $exception->end_time)
                {{ \Illuminate\Support\Carbon::parse($exception->start_time)->format('g:i A') }} – {{ $exception->ends_next_day ? __('12:00 midnight') : \Illuminate\Support\Carbon::parse($exception->end_time)->format('g:i A') }}
            @else
                {{ __('Full day') }}
            @endif
        </a>
    @endforeach
    <div class="schedules-effective">
        <span>{{ __('Effective hours') }}</span>
        @forelse ($cell['effective'] as $window)
            <p>{{ $window['start'] }} – {{ $window['end'] }}</p>
        @empty
            <p>{{ __('Unavailable') }}</p>
        @endforelse
    </div>
</div>
