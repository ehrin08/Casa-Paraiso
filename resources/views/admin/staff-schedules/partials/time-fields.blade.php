<div class="schedules-form-grid" data-schedule-times>
    <label><span class="casa-label">{{ __('Start') }}</span><select name="start_time" class="casa-input" data-schedule-start required>@foreach($times as $time => $label)<option value="{{ $time }}" @selected($startTime === $time)>{{ $label }}</option>@endforeach</select></label>
    <label><span class="casa-label">{{ __('End') }}</span><select name="end_time" class="casa-input" data-schedule-end required>@foreach($endTimes as $time => $label)<option value="{{ $time }}" @selected($endTime === $time)>{{ $label }}</option>@endforeach</select></label>
    <p class="schedules-note" data-schedule-duration aria-live="polite">{{ __('Use 30-minute boundaries from 1:00 PM through midnight.') }}</p>
</div>
