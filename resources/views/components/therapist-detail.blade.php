@props(['staffProfile', 'selectedTab', 'selectionQuery', 'schedule', 'weekStart', 'weekEnd', 'upcomingExceptions'])

@php
    $initials = \Illuminate\Support\Str::of($staffProfile->user->name)->squish()->explode(' ')
        ->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');
    $tabUrl = fn ($tab) => route('admin.staff.index', array_merge($selectionQuery, ['selected' => $staffProfile->id, 'tab' => $tab])).'#therapist-detail';
    $editUrl = route('admin.staff.edit', $staffProfile);
    $scheduleUrl = route('admin.staff.show', $staffProfile).'#therapist-roster';
    $availabilityUrl = route('admin.staff.show', $staffProfile).'#therapist-exceptions';
    $formatTime = fn ($time) => \Illuminate\Support\Carbon::parse($time)->format('g:i A');
@endphp

<aside id="therapist-detail" class="therapists-detail" aria-labelledby="therapist-detail-name" tabindex="-1" data-selected-therapist="{{ $staffProfile->id }}" data-selected-tab="{{ $selectedTab }}">
    <div class="therapists-identity">
        <span class="therapists-avatar therapists-avatar--large" aria-hidden="true">{{ mb_strtoupper($initials) }}</span>
        <div class="therapists-identity-copy">
            <h2 id="therapist-detail-name">{{ $staffProfile->user->name }}</h2>
            <p class="therapists-muted">{{ $staffProfile->position ?: __('Therapist') }}</p>
            <div class="therapists-identity-badges">
                <span @class(['therapists-pill', 'therapists-pill--active' => $staffProfile->user->is_active, 'therapists-pill--inactive' => ! $staffProfile->user->is_active])>
                    {{ $staffProfile->user->is_active ? __('Active account') : __('Inactive account') }}
                </span>
                <span @class(['therapists-pill', 'therapists-pill--active' => $staffProfile->is_bookable, 'therapists-pill--unavailable' => ! $staffProfile->is_bookable])>
                    {{ $staffProfile->is_bookable ? __('Bookable') : __('Unavailable') }}
                </span>
            </div>
        </div>
        <details class="therapists-menu" x-data @keydown.escape.prevent="$el.open = false; $refs.actions.focus()" @click.outside="$el.open = false">
            <summary aria-label="{{ __('Therapist actions') }}" x-ref="actions">
                <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="19" cy="12" r="1.5" /></svg>
            </summary>
            <div class="therapists-menu-links">
                <a href="{{ $editUrl }}">{{ __('Edit therapist') }}</a>
                <a href="{{ route('admin.staff.show', $staffProfile) }}">{{ __('Open full profile') }}</a>
            </div>
        </details>
    </div>

    <nav class="therapists-tabs" aria-label="{{ __('Therapist detail sections') }}">
        @foreach (['profile' => __('Profile'), 'services' => __('Services'), 'schedule' => __('Schedule'), 'availability' => __('Availability')] as $tab => $label)
            <a href="{{ $tabUrl($tab) }}" @class(['is-active' => $selectedTab === $tab]) @if ($selectedTab === $tab) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($selectedTab === 'profile')
        <section class="therapists-section">
            <h3>{{ __('Contact information') }}</h3>
            <div class="therapists-contact">
                <div class="therapists-contact-item">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 3 4 1-1 5 3 4 4 3 5-1 1 4c-1 3-6 3-10 0C6 16 2 10 3 6l2-3Z" /></svg>
                    <span><span class="sr-only">{{ __('Phone:') }} </span>{{ $staffProfile->user->phone ?: __('Phone not set') }}</span>
                </div>
                <div class="therapists-contact-item">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path stroke-linecap="round" stroke-linejoin="round" d="m3 6 9 7 9-7" /></svg>
                    <span><span class="sr-only">{{ __('Email:') }} </span>{{ $staffProfile->user->email ?: __('Email not set') }}</span>
                </div>
            </div>
        </section>
        <section class="therapists-section">
            <h3>{{ __('About') }}</h3>
            @if ($staffProfile->specialization)
                <p class="therapists-muted">{{ $staffProfile->specialization }}</p>
            @endif
            <p class="therapists-muted">{{ $staffProfile->bio ?: __('No bio has been added yet.') }}</p>
            @if ($staffProfile->hire_date)
                <p class="therapists-note">{{ __('Joined :date', ['date' => $staffProfile->hire_date->format('M d, Y')]) }}</p>
            @endif
        </section>
        <section class="therapists-section">
            <div class="therapists-section-heading">
                <h3>{{ __('Services offered (:count)', ['count' => $staffProfile->services_count]) }}</h3>
                <a href="{{ $tabUrl('services') }}" class="therapists-action-link therapists-action-link--plain">{{ __('View all services') }} <span aria-hidden="true">→</span></a>
            </div>
            <div class="therapists-service-tags">
                @forelse ($staffProfile->services->take(4) as $service)
                    <span class="therapists-service-tag">{{ $service->name }}@if (! $service->is_active) · {{ __('Inactive') }}@endif</span>
                @empty
                    <p class="therapists-muted">{{ __('No services assigned yet.') }}</p>
                @endforelse
            </div>
        </section>
    @elseif ($selectedTab === 'services')
        <section class="therapists-section">
            <div class="therapists-section-heading">
                <h3>{{ __('Services offered (:count)', ['count' => $staffProfile->services_count]) }}</h3>
                <a href="{{ $editUrl }}" class="therapists-action-link">{{ __('Edit services') }} <span aria-hidden="true">→</span></a>
            </div>
            <div class="therapists-service-list">
                @forelse ($staffProfile->services as $service)
                    <div class="therapists-service-item">
                        <div>
                            <a href="{{ route('admin.services.show', $service) }}" class="font-bold">{{ $service->name }}</a>
                            <p class="therapists-note">{{ __(':minutes min', ['minutes' => $service->duration_minutes]) }} · PHP {{ number_format((float) $service->price, 2) }}</p>
                        </div>
                        <span @class(['therapists-pill', 'therapists-pill--active' => $service->is_active, 'therapists-pill--inactive' => ! $service->is_active])>{{ $service->is_active ? __('Active') : __('Inactive') }}</span>
                    </div>
                @empty
                    <p class="therapists-muted">{{ __('No services assigned yet.') }}</p>
                @endforelse
            </div>
        </section>
    @endif

    @if (in_array($selectedTab, ['profile', 'schedule']))
        <section class="therapists-section">
            <div class="therapists-section-heading">
                <h3>{{ __('Schedule') }}</h3>
                @if ($selectedTab === 'schedule')
                    <a href="{{ $scheduleUrl }}" class="therapists-action-link"><x-nav-icon name="calendar" />{{ __('Manage schedule') }} <span aria-hidden="true">→</span></a>
                @endif
            </div>
            @if ($selectedTab === 'profile')
                <div class="therapists-summary-row">
                    <x-nav-icon name="calendar" class="size-6" />
                    <div class="therapists-summary-copy">
                        <p>{{ $schedule['daysLabel'] }}</p>
                        @if ($schedule['hoursLabel'] !== $schedule['daysLabel'])
                            <p class="therapists-muted">{{ $schedule['hoursLabel'] }}</p>
                        @endif
                        <p class="therapists-note">{{ __('This week · :start – :end', ['start' => $weekStart->format('M d'), 'end' => $weekEnd->format('M d')]) }}</p>
                    </div>
                    <a href="{{ $scheduleUrl }}" class="therapists-action-link">{{ __('Manage schedule') }} <span aria-hidden="true">→</span></a>
                </div>
            @else
                <p class="therapists-note">{{ __(':start – :end · Asia/Manila', ['start' => $weekStart->format('M d, Y'), 'end' => $weekEnd->format('M d, Y')]) }}</p>
                <p class="therapists-note">{{ __('Effective working hours include published schedules and date-specific exceptions.') }}</p>
                <dl class="therapists-week-list">
                    @foreach ($schedule['days'] as $day)
                        <div class="therapists-day">
                            <dt><strong>{{ $day['day'] }}</strong> <span class="therapists-muted">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('M d') }}</span></dt>
                            <dd>
                                @forelse ($day['windows'] as $window)
                                    <p>{{ $window['start'] }} – {{ $window['end'] }}</p>
                                @empty
                                    <span class="therapists-muted">{{ __('No scheduled hours') }}</span>
                                @endforelse
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>
    @endif

    @if (in_array($selectedTab, ['profile', 'availability']))
        <section class="therapists-section">
            <h3>{{ __('Availability') }}</h3>
            <div class="therapists-summary-row">
                <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v5l3 2" /></svg>
                <div class="therapists-summary-copy">
                    <p>{{ $staffProfile->is_bookable ? __('Booking enabled') : __('Booking disabled') }}</p>
                    <p class="therapists-note">{{ __('Appointment availability also depends on account status, assigned services, schedules, and existing bookings.') }}</p>
                </div>
                <a href="{{ $selectedTab === 'availability' ? $editUrl : $tabUrl('availability') }}" class="therapists-action-link">
                    {{ $selectedTab === 'availability' ? __('Edit availability') : __('Manage availability') }} <span aria-hidden="true">→</span>
                </a>
            </div>
            @if ($selectedTab === 'availability')
                <p class="therapists-muted mt-3">{{ $staffProfile->user->is_active ? __('This account is active.') : __('This account is inactive and cannot receive new bookings.') }}</p>
            @endif
        </section>
    @endif

    @if ($selectedTab === 'availability')
        <section class="therapists-section">
            <div class="therapists-section-heading">
                <h3>{{ __('Upcoming exceptions') }}</h3>
                <a href="{{ route('admin.staff.schedule-exceptions.create', $staffProfile) }}" class="therapists-action-link">{{ __('Add exception') }} <span aria-hidden="true">+</span></a>
            </div>
            <div class="therapists-exception-list">
                @forelse ($upcomingExceptions as $exception)
                    <div class="therapists-exception">
                        <div class="therapists-section-heading">
                            <strong>{{ $exception->exception_date->format('M d, Y') }}</strong>
                            <a href="{{ route('admin.staff.schedule-exceptions.edit', [$staffProfile, $exception]) }}" class="therapists-action-link therapists-action-link--plain">{{ __('Edit') }}</a>
                        </div>
                        <p>
                            <span @class(['therapists-pill', 'therapists-pill--active' => $exception->exception_type === \App\Models\StaffScheduleException::TYPE_AVAILABLE, 'therapists-pill--unavailable' => $exception->exception_type !== \App\Models\StaffScheduleException::TYPE_AVAILABLE])>{{ ucfirst($exception->exception_type) }}</span>
                            @if ($exception->start_time && $exception->end_time)
                                {{ $formatTime($exception->start_time) }} – {{ $exception->ends_next_day && substr($exception->end_time, 0, 5) === '00:00' ? __('12:00 midnight') : $formatTime($exception->end_time) }}
                            @else
                                {{ __('Full day') }}
                            @endif
                        </p>
                        @if ($exception->reason)
                            <p class="therapists-muted">{{ $exception->reason }}</p>
                        @endif
                    </div>
                @empty
                    <p class="therapists-muted">{{ __('No upcoming exceptions.') }}</p>
                @endforelse
            </div>
            <a href="{{ $availabilityUrl }}" class="therapists-action-link mt-3">{{ __('Manage exceptions') }} <span aria-hidden="true">→</span></a>
        </section>
    @endif
</aside>
