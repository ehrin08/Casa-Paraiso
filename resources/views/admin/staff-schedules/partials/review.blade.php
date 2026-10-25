@php
    $isPublish = $editor === 'publish';
    $action = $isPublish ? route('admin.staff-roster.publish', $roster) : ($editor === 'copy' ? route('admin.staff-roster.copy') : route('admin.staff-roster.bulk.store', $roster));
    $rangesLabel = fn ($ranges) => $ranges->map(fn ($range) => \Illuminate\Support\Carbon::parse($range['start_time'])->format('g:i A').' – '.($range['ends_next_day'] ? __('12:00 midnight') : \Illuminate\Support\Carbon::parse($range['end_time'])->format('g:i A')))->implode(', ') ?: __('No roster hours');
@endphp
@if ($editor === 'copy')
    <p class="schedules-form-intro">{{ $copy_source ? __('Source: published week of :week', ['week' => $copy_source->week_start_date->format('M j, Y')]) : __('Source: recurring therapist schedules') }}</p>
    <p class="schedules-note">{{ __('Confirming replaces this week’s draft. Elapsed days in the current week are preserved.') }}</p>
@endif
<p class="schedules-form-intro">{{ __(':shifts shifts for :therapists therapists · :days changed days across the entire team.', ['shifts' => $candidate->count(), 'therapists' => $candidate->pluck('staff_profile_id')->unique()->count(), 'days' => $changes->count()]) }}</p>
<p class="schedules-note">{{ $until ? __('Once published, these hours repeat through :date. The next published week starts :next.', ['date' => $until->copy()->subDay()->format('M j, Y'), 'next' => $until->format('M j, Y')]) : __('Once published, these hours repeat into later weeks until another week is published.') }}</p>
<div class="schedules-review-changes" tabindex="0" role="region" aria-label="{{ __('Changed hours for the entire team') }}">
    @forelse ($changes->groupBy('staff_profile_id') as $staffId => $personChanges)
        <details open><summary>{{ $reviewNames->get($staffId, __('Unavailable therapist')) }}</summary><dl>@foreach($personChanges as $change)<div><dt>{{ \Illuminate\Support\Carbon::parse($change['date'], 'Asia/Manila')->format('D, M j') }}</dt><dd><span>{{ __('Live:') }} {{ $rangesLabel($change['before']) }}</span><strong>{{ __('Proposed:') }} {{ $rangesLabel($change['after']) }}</strong></dd></div>@endforeach</dl></details>
    @empty
        <p>{{ __('The proposed roster hours match the live schedule.') }}</p>
    @endforelse
</div>
@if (count($conflicts))
    <p class="schedules-notice" role="alert">{{ $isPublish ? __('Publication is blocked by :count confirmed bookings.', ['count' => count($conflicts)]) : __(':count confirmed bookings conflict with these hours. You can save the draft, then resolve them before publishing.', ['count' => count($conflicts)]) }}</p>
    <div class="schedules-review-conflicts" tabindex="0" role="region" aria-label="{{ __('All affected confirmed bookings') }}">@include('admin.staff-schedules.partials.conflicts')</div>
@else
    <p class="schedules-notice schedules-notice--success" role="status">{{ __('Confirmed bookings remain covered by these hours and date exceptions.') }}</p>
@endif
<form method="POST" action="{{ $action }}" data-turbo="false" data-schedule-submit>
    @csrf
    @include('admin.staff-schedules.partials.context')
    @if ($editor === 'copy')<input type="hidden" name="source_signature" value="{{ $source_signature }}">@endif
    @if ($editor === 'bulk-review')
        @foreach ($bulkData as $key => $value)
            @if (is_array($value))
                @foreach ($value as $item)<input type="hidden" name="{{ $key }}[]" value="{{ $item }}">@endforeach
            @else
                <input type="hidden" name="{{ $key }}" value="{{ is_bool($value) ? (int) $value : $value }}">
            @endif
        @endforeach
    @endif
    <div class="schedules-form-actions">
        <a href="{{ $indexUrl }}" class="casa-button-secondary" data-schedule-dialog-cancel>{{ __('Back to schedule') }}</a>
        @if ($isPublish && count($conflicts))
            <span class="schedules-note">{{ __('Resolve the bookings above, then review again.') }}</span>
        @else
            <button type="submit" class="casa-button-primary">{{ $isPublish ? __('Publish entire team schedule') : ($editor === 'copy' ? __('Replace draft with source hours') : __('Save selected days to draft')) }}</button>
        @endif
    </div>
</form>
