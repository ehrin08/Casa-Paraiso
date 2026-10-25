@php
    $selectedStaff = old('staff_profile_id', $shift?->staff_profile_id ?? ($filters['therapist'] ?? $filters['staff_profile_id'] ?? $staffProfiles->first()?->id));
    $selectedDate = old('schedule_date', $shift?->schedule_date?->toDateString() ?? ($filters['schedule_date'] ?? $days->firstWhere('editable', true)['date'] ?? $start->toDateString()));
    $startTime = old('start_time', $shift ? substr($shift->start_time, 0, 5) : '13:00');
    $endTime = old('end_time', $shift ? substr($shift->end_time, 0, 5) : '18:00');
@endphp
<form method="POST" action="{{ $shift ? route('admin.staff-roster.shifts.update', [$roster, $shift]) : route('admin.staff-roster.shifts.store', $roster) }}" data-turbo="false" data-schedule-submit>
    @csrf
    @if ($shift) @method('PUT') @endif
    @include('admin.staff-schedules.partials.context')
    @if ($shift)
        <p class="schedules-form-intro">{{ $shift->staffProfile->user->name }} · {{ $shift->schedule_date->format('D, M j') }}</p>
        <input type="hidden" name="staff_profile_id" value="{{ $shift->staff_profile_id }}">
        <input type="hidden" name="schedule_date" value="{{ $shift->schedule_date->toDateString() }}">
    @else
        <div class="schedules-form-grid">
            <label><span class="casa-label">{{ __('Therapist') }}</span><select name="staff_profile_id" class="casa-input" required>@foreach($staffProfiles as $person)<option value="{{ $person->id }}" @selected((string) $selectedStaff === (string) $person->id) @disabled(! $person->user->is_active || ! $person->user->isStaff())>{{ $person->user->name }}</option>@endforeach</select></label>
            <label><span class="casa-label">{{ __('Date') }}</span><select name="schedule_date" class="casa-input" required>@foreach($days as $day)<option value="{{ $day['date'] }}" @selected($selectedDate === $day['date']) @disabled(! $day['editable'])>{{ $day['heading'] }}</option>@endforeach</select></label>
        </div>
    @endif
    @include('admin.staff-schedules.partials.time-fields')
    <p class="schedules-note">{{ __('Shifts can be split into adjacent ranges. Overlapping ranges are rejected.') }}</p>
    <div class="schedules-form-actions"><a href="{{ $indexUrl }}" class="casa-button-secondary" data-schedule-dialog-cancel>{{ __('Cancel') }}</a><button type="submit" class="casa-button-primary">{{ __('Save draft shift') }}</button></div>
</form>
