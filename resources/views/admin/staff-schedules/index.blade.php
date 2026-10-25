<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Manage') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Therapist schedules') }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ __('Prepare the team’s hours, review booking conflicts, then publish.') }}</p>
        </div>
        <a href="{{ route('admin.staff.index') }}" class="casa-button-secondary">{{ __('Therapists') }}</a>
    </x-slot>

    <div class="schedules-workspace" data-schedule-workspace>
        @if (session('roster_status'))
            <p class="schedules-notice schedules-notice--success" role="status">{{ session('roster_status') }}</p>
        @endif
        <x-input-error :messages="$errors->all()" />
        <section class="schedules-week casa-card" aria-labelledby="schedule-week-title">
            <div>
                <p class="casa-section-label">{{ __('Sunday – Saturday · Asia/Manila') }}</p>
                <h2 id="schedule-week-title">{{ $start->format('M j') }} – {{ $start->copy()->addDays(6)->format('M j, Y') }}</h2>
                <p class="schedules-source">
                    @if ($source && $source->week_start_date->isSameDay($start))
                        {{ __('Published for this week') }}
                    @elseif ($source)
                        {{ __('Repeating from :week', ['week' => $source->week_start_date->format('M j, Y')]) }}
                    @else
                        {{ __('Live hours come from recurring schedules') }}
                    @endif
                </p>
            </div>
            <nav class="schedules-week-nav" aria-label="{{ __('Choose schedule week') }}">
                <a class="casa-icon-button" href="{{ route('admin.staff-roster.index', [...\Illuminate\Support\Arr::except($filters, ['page', 'conflicts_page', 'schedule_date']), 'week' => $start->copy()->subWeek()->toDateString()]) }}" aria-label="{{ __('Previous week') }}">‹</a>
                <form method="GET" action="{{ route('admin.staff-roster.index') }}">
                    @foreach (\Illuminate\Support\Arr::only($filters, ['q', 'staff_profile_id', 'view']) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <label class="sr-only" for="roster-week">{{ __('Week containing date') }}</label>
                    <input id="roster-week" name="week" type="date" class="casa-input" value="{{ $start->toDateString() }}" required>
                    <button class="casa-button-secondary">{{ __('Go') }}</button>
                </form>
                <a class="casa-icon-button" href="{{ route('admin.staff-roster.index', [...\Illuminate\Support\Arr::except($filters, ['page', 'conflicts_page', 'schedule_date']), 'week' => $start->copy()->addWeek()->toDateString()]) }}" aria-label="{{ __('Next week') }}">›</a>
            </nav>
        </section>

        <x-stat-strip class="schedules-stats" :items="[
            ['label' => $has_draft ? __('Draft shifts') : __('Live shifts'), 'value' => ($has_draft ? $draft_shifts : $live_shifts)->count(), 'meta' => __('Entire team'), 'tone' => 'green'],
            ['label' => __('Scheduled therapists'), 'value' => ($has_draft ? $draft_shifts : $live_shifts)->pluck('staff_profile_id')->unique()->count(), 'meta' => __('Entire team'), 'tone' => 'brown'],
            ['label' => __('Booking conflicts'), 'value' => $conflictCount, 'meta' => $has_draft ? __('Checked against the draft') : __('Start a draft to review'), 'tone' => 'gold'],
        ]" />

        <section class="casa-card schedules-roster" aria-labelledby="roster-heading">
            <div class="schedules-roster-heading">
                <div>
                    <h2 id="roster-heading">{{ $filters['view'] === 'draft' ? __('Draft changes') : __('Live hours') }}</h2>
                    <p class="text-sm text-casa-muted">{{ $has_draft ? ($has_changes ? __('Draft changes await publication.') : __('The draft matches live roster hours.')) : __('Start a draft to edit the whole team safely.') }}</p>
                    @if (! $editable)
                        <p class="schedules-note">{{ __('Past weeks are view-only.') }}</p>
                    @endif
                </div>
                <div class="schedules-actions">
                    @if ($has_draft)
                        <nav class="schedules-view-toggle" aria-label="{{ __('Schedule version') }}">
                            @foreach (['draft' => __('Draft changes'), 'live' => __('Live hours')] as $version => $label)
                                <a href="{{ route('admin.staff-roster.index', [...$filters, 'view' => $version]) }}" @if($filters['view'] === $version) aria-current="page" @endif>{{ $label }}</a>
                            @endforeach
                        </nav>
                    @endif
                    @if ($editable)
                        @if (! $has_draft)
                            <form method="POST" action="{{ route('admin.staff-roster.draft.store') }}" data-turbo="false" data-schedule-submit>
                                @csrf
                                @include('admin.staff-schedules.partials.context')
                                <button class="casa-button-primary">{{ __('Start editing this week') }}</button>
                            </form>
                        @else
                            <a href="{{ route('admin.staff-roster.bulk.edit', [$roster, ...$filters]) }}" class="casa-button-secondary" data-schedule-editor data-no-loading>{{ __('Apply hours') }}</a>
                            <a href="{{ route('admin.staff-roster.publish.review', [$roster, ...$filters]) }}" class="casa-button-primary" data-schedule-editor data-no-loading>{{ __('Review and publish') }}</a>
                        @endif
                        <a href="{{ route('admin.staff-roster.copy.review', $filters) }}" class="schedules-text-link" data-schedule-editor data-no-loading>{{ __('Copy published week') }}</a>
                    @endif
                </div>
            </div>

            <x-list-toolbar :count="$staffProfiles->total()" :reset-url="route('admin.staff-roster.index', ['week' => $start->toDateString(), 'view' => $filters['view']])" :active-filters="(filled($filters['q'] ?? null) ? 1 : 0) + (filled($filters['staff_profile_id'] ?? null) ? 1 : 0)" class="casa-list-toolbar--filters schedules-toolbar">
                <form method="GET" action="{{ route('admin.staff-roster.index') }}" class="casa-filter-form">
                    <input type="hidden" name="week" value="{{ $start->toDateString() }}">
                    <input type="hidden" name="view" value="{{ $filters['view'] }}">
                    @if (filled($filters['staff_profile_id'] ?? null))
                        <input type="hidden" name="staff_profile_id" value="{{ $filters['staff_profile_id'] }}">
                    @endif
                    <label class="casa-filter-search">
                        <span class="sr-only">{{ __('Search therapist names') }}</span>
                        <input name="q" class="casa-input" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search therapist names') }}" maxlength="100">
                    </label>
                    <button type="submit" class="casa-button-secondary">{{ __('Search') }}</button>
                </form>
            </x-list-toolbar>

            @if ($staffProfiles->isEmpty())
                <div class="p-5">
                    <x-empty-state title="{{ __('No therapists found') }}" description="{{ __('Clear the filters or manage therapist accounts to set working hours.') }}" />
                </div>
            @else
                <div class="schedules-desktop">
                    <x-table-shell class="schedules-grid" :label="__('Weekly therapist hours. Scroll to see all seven days.')">
                        <thead><tr><th scope="col">{{ __('Therapist') }}</th>@foreach ($days as $day)<th scope="col">{{ $day['heading'] }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($staffProfiles as $person)
                                <tr>
                                    <th scope="row"><strong>{{ $person->user->name }}</strong><span class="schedules-person-meta">{{ $person->is_bookable ? __('Booking enabled') : __('Booking disabled') }}</span>@if(! $person->user->is_active || ! $person->user->isStaff())<span class="schedules-person-meta">{{ __('Account unavailable') }}</span>@endif</th>
                                    @foreach ($days as $day)
                                        <td>@include('admin.staff-schedules.partials.cell', ['cell' => $cells[$person->id.':'.$day['date']]])</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                </div>
                <div class="schedules-mobile">
                    @foreach ($staffProfiles as $person)
                        <details class="schedules-mobile-person" @if($loop->first) open @endif>
                            <summary>{{ $person->user->name }} <span>{{ $person->is_bookable ? __('Booking enabled') : __('Booking disabled') }}</span></summary>
                            @foreach ($days as $day)
                                <div class="schedules-mobile-day"><h3>{{ $day['heading'] }}</h3>@include('admin.staff-schedules.partials.cell', ['cell' => $cells[$person->id.':'.$day['date']]])</div>
                            @endforeach
                        </details>
                    @endforeach
                </div>
                <div class="schedules-pagination">{{ $staffProfiles->links('pagination.compact') }}</div>
            @endif
        </section>

        @if ($has_draft)
            <section class="casa-card schedules-conflict-section" aria-labelledby="draft-conflicts-title">
                <h2 id="draft-conflicts-title">{{ __('Draft conflicts · entire team') }}</h2>
                @if ($conflictCount)
                    <p class="schedules-note">{{ __(':count confirmed bookings need resolution before publishing. These include therapists outside the current filters.', ['count' => $conflictCount]) }}</p>
                    @include('admin.staff-schedules.partials.conflicts', ['conflicts' => $conflictPaginator])
                    {{ $conflictPaginator->links('pagination.compact') }}
                @else
                    <p class="schedules-notice schedules-notice--success" role="status">{{ __('Confirmed bookings remain covered. Publishing checks again before making the hours live.') }}</p>
                @endif
            </section>
        @endif
        <dialog id="therapist-schedule-dialog" class="schedules-dialog" data-turbo="false" aria-labelledby="schedule-editor-title">
            <div data-schedule-dialog-content></div>
        </dialog>
    </div>
</x-app-layout>
