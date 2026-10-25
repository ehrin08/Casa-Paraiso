@php
    $chosenStaff = (array) old('staff_ids', isset($filters['staff_profile_id']) ? [$filters['staff_profile_id']] : []);
    $chosenDates = (array) old('dates', []);
    $startTime = old('start_time', '13:00');
    $endTime = old('end_time', '18:00');
@endphp
<form method="POST" action="{{ route('admin.staff-roster.bulk.review', $roster) }}" data-turbo="false" data-schedule-submit data-schedule-bulk>
    @csrf
    @include('admin.staff-schedules.partials.context')
    <p class="schedules-form-intro">{{ __('Replace roster hours on the selected days. Date exceptions remain in effect and appear in the review.') }}</p>
    <label><span class="casa-label">{{ __('Apply') }}</span><select name="mode" class="casa-input" data-schedule-bulk-mode><option value="hours" @selected(old('mode', 'hours') === 'hours')>{{ __('Set working hours') }}</option><option value="clear" @selected(old('mode') === 'clear')>{{ __('Clear roster hours') }}</option></select></label>
    <div class="schedules-form-grid">
        <fieldset class="schedules-choices"><legend>{{ __('Therapists on this page') }}</legend><button type="button" data-schedule-select-all="staff_ids[]" hidden>{{ __('Select all') }}</button>@foreach($staffProfiles as $person)<label><input type="checkbox" name="staff_ids[]" value="{{ $person->id }}" @checked(in_array((string) $person->id, array_map('strval', $chosenStaff), true))><span>{{ $person->user->name }}</span></label>@endforeach</fieldset>
        <fieldset class="schedules-choices"><legend>{{ __('Days') }}</legend><button type="button" data-schedule-select-all="dates[]" hidden>{{ __('Select all') }}</button>@foreach($days as $day)<label><input type="checkbox" name="dates[]" value="{{ $day['date'] }}" @checked(in_array($day['date'], $chosenDates, true)) @disabled(! $day['editable'])><span>{{ $day['heading'] }}</span></label>@endforeach</fieldset>
    </div>
    @include('admin.staff-schedules.partials.time-fields')
    <div class="schedules-form-actions"><a href="{{ $indexUrl }}" class="casa-button-secondary" data-schedule-dialog-cancel>{{ __('Cancel') }}</a><button type="submit" class="casa-button-primary">{{ __('Review selected days') }}</button></div>
</form>
