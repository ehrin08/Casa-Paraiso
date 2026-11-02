<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\OperatingHourRule;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Models\StaffScheduleShift;
use App\Models\StaffScheduleWeek;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ScheduleWindowResolver
{
    /** @var array<string, StaffScheduleWeek|null> */
    private array $publishedWeeks = [];

    /** @var array<int, Collection<string, Collection<int, StaffScheduleShift>>> */
    private array $publishedRosterShifts = [];

    /** @var array<string, array{closed: string|null, window: array{start: Carbon, end: Carbon}|null, blocked: list<array{start: Carbon, end: Carbon, reason: string}>, special: string|null}> */
    private array $days = [];

    /** @var array{opens_at: string, closes_at: string}|null */
    private ?array $defaultHours = null;

    private bool $lockingReads = false;

    public function clearCache(): void
    {
        $this->publishedWeeks = [];
        $this->publishedRosterShifts = [];
        $this->days = [];
        $this->defaultHours = null;
    }

    /** The reason the spa is closed on this business date, or null when it is open. */
    public function closedReason(CarbonInterface $day): ?string
    {
        return $this->dayHours($day)['closed'];
    }

    /**
     * How one business date resolves: a closure wins, special hours replace the default window,
     * and partial closures block intervals inside whichever window applies.
     *
     * @return array{closed: string|null, window: array{start: Carbon, end: Carbon}|null, blocked: list<array{start: Carbon, end: Carbon, reason: string}>, special: string|null}
     */
    public function dayHours(CarbonInterface $day): array
    {
        $date = $this->businessDate($day);

        if (! array_key_exists($date, $this->days)) {
            $rules = OperatingHourRule::query()
                ->covering($date, $date)
                ->orderBy('id')
                ->when($this->lockingReads, fn ($query) => $query->sharedLock())
                ->get();

            $this->days[$date] = $this->resolveDay($date, $rules);
        }

        return $this->days[$date];
    }

    /**
     * Resolve every date in a range with one query and prime the cache.
     *
     * @return array<string, array{closed: string|null, window: array{start: Carbon, end: Carbon}|null, blocked: list<array{start: Carbon, end: Carbon, reason: string}>, special: string|null}>
     */
    public function hoursBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $rules = OperatingHourRule::query()->covering($from, $to)->orderBy('id')->get();
        $days = [];

        for ($day = Carbon::parse($from->toDateString()); $day->lte($to); $day->addDay()) {
            $date = $day->toDateString();
            $days[$date] = $this->days[$date] = $this->resolveDay($date, $rules->filter(fn (OperatingHourRule $rule) => $rule->covers($date)));
        }

        return $days;
    }

    /**
     * Closing reasons for every closed date in a range, keyed by date, with the cache primed.
     *
     * @return array<string, string>
     */
    public function closedReasonsBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        return collect($this->hoursBetween($from, $to))
            ->filter(fn (array $hours) => $hours['closed'] !== null)
            ->map(fn (array $hours) => $hours['closed'])
            ->all();
    }

    /** Use a fresh, current database view after the caller acquires therapist locks. */
    public function freshForTransaction(): self
    {
        $resolver = clone $this;
        $resolver->clearCache();
        $resolver->lockingReads = true;

        return $resolver;
    }

    /**
     * The opening window that applies on this date, or null when the spa is closed.
     *
     * @return array{start: Carbon, end: Carbon}|null
     */
    public function businessWindow(CarbonInterface $day): ?array
    {
        $window = $this->dayHours($day)['window'];

        return $window ? ['start' => $window['start']->copy(), 'end' => $window['end']->copy()] : null;
    }

    /**
     * The default hours on this date, ignoring dated rules. Schedule templates are built against it.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function defaultBusinessWindow(CarbonInterface $day): array
    {
        $hours = $this->defaultHours();

        return $this->openWindow($this->businessDate($day), $hours['opens_at'], $hours['closes_at']);
    }

    /**
     * Resolve the bookable intervals for one therapist and business date.
     *
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    public function effectiveWindows(StaffProfile $staffProfile, CarbonInterface $day, ?Collection $rosterShifts = null): Collection
    {
        $date = Carbon::parse($day)->startOfDay();

        $hours = $this->dayHours($date);
        $business = $hours['window'];

        // A closed date has no bookable time for anyone, whatever the therapist schedules say.
        if ($business === null) {
            return collect();
        }

        $exceptions = $staffProfile->relationLoaded('scheduleExceptions')
            ? $staffProfile->scheduleExceptions->filter(fn (StaffScheduleException $exception) => $exception->exception_date?->isSameDay($date))
            : $staffProfile->scheduleExceptions()->whereDate('exception_date', $date->toDateString())->get();

        $windows = collect();
        $rosterShifts ??= $this->rosterShiftsForDate($staffProfile, $date);

        if ($rosterShifts !== null) {
            foreach ($rosterShifts as $schedule) {
                $interval = $this->intervalForDate($date, (string) $schedule->start_time, (string) $schedule->end_time, (bool) $schedule->ends_next_day);

                if ($clamped = $this->clamp($interval, $business)) {
                    $windows->push($clamped);
                }
            }
        } else {
            $weeklySchedules = $staffProfile->relationLoaded('weeklySchedules')
                ? $staffProfile->weeklySchedules
                : $staffProfile->weeklySchedules()->get();

            foreach ($weeklySchedules->where('day_of_week', $date->dayOfWeek)->where('is_available', true) as $schedule) {
                $interval = $this->intervalForDate(
                    $date,
                    (string) $schedule->start_time,
                    (string) $schedule->end_time,
                    (bool) $schedule->ends_next_day,
                );

                if ($clamped = $this->clamp($interval, $business)) {
                    $windows->push($clamped);
                }
            }
        }

        foreach ($exceptions->where('exception_type', StaffScheduleException::TYPE_AVAILABLE) as $exception) {
            if (! $exception->start_time || ! $exception->end_time) {
                continue;
            }

            $interval = $this->intervalForDate(
                $date,
                (string) $exception->start_time,
                (string) $exception->end_time,
                (bool) $exception->ends_next_day,
            );

            if ($clamped = $this->clamp($interval, $business)) {
                $windows->push($clamped);
            }
        }

        $windows = $this->merge($windows);

        foreach ($exceptions->where('exception_type', StaffScheduleException::TYPE_UNAVAILABLE) as $exception) {
            if (! $exception->start_time || ! $exception->end_time) {
                return collect();
            }

            $blocked = $this->intervalForDate(
                $date,
                (string) $exception->start_time,
                (string) $exception->end_time,
                (bool) $exception->ends_next_day,
            );

            $windows = $this->subtract($windows, $blocked);
        }

        foreach ($hours['blocked'] as $blocked) {
            $windows = $this->subtract($windows, $blocked);
        }

        return $this->merge($windows)->values();
    }

    public function covers(StaffProfile $staffProfile, CarbonInterface $start, CarbonInterface $end, ?Collection $rosterShifts = null): bool
    {
        if ($end->lte($start) || ! $this->withinBusinessHours($start, $end)) {
            return false;
        }

        return $this->effectiveWindows($staffProfile, $start, $rosterShifts)
            ->contains(fn (array $window) => $window['start']->lte($start) && $window['end']->gte($end));
    }

    public function withinBusinessHours(CarbonInterface $start, CarbonInterface $end): bool
    {
        $hours = $this->dayHours($start);
        $business = $hours['window'];

        if ($business === null || $start->lt($business['start']) || $end->gt($business['end'])) {
            return false;
        }

        foreach ($hours['blocked'] as $blocked) {
            if ($start->lt($blocked['end']) && $end->gt($blocked['start'])) {
                return false;
            }
        }

        return true;
    }

    /** A short description of the hours on a date, for validation messages. */
    public function hoursSummary(CarbonInterface $day): string
    {
        $hours = $this->dayHours($day);

        if ($hours['closed'] !== null) {
            return __('closed: :reason', ['reason' => $hours['closed']]);
        }

        $summary = ApplicationSetting::hoursRangeLabel($hours['window']['start']->format('H:i'), $hours['window']['end']->format('H:i'));

        foreach ($hours['blocked'] as $blocked) {
            $summary .= ', '.__('closed :range', ['range' => ApplicationSetting::hoursRangeLabel($blocked['start']->format('H:i'), $blocked['end']->format('H:i'))]);
        }

        return $summary;
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function intervalForDate(CarbonInterface $date, string $startTime, string $endTime, bool $endsNextDay = false): array
    {
        $timezone = (string) config('casa.business_hours.timezone', config('app.timezone'));
        $day = Carbon::parse($date, $timezone)->toDateString();
        $start = Carbon::parse($day.' '.substr($startTime, 0, 8), $timezone);
        $end = Carbon::parse($day.' '.substr($endTime, 0, 8), $timezone);

        if ($endsNextDay) {
            $end->addDay();
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @param  Collection<int, OperatingHourRule>  $rules  Rules covering this date.
     * @return array{closed: string|null, window: array{start: Carbon, end: Carbon}|null, blocked: list<array{start: Carbon, end: Carbon, reason: string}>, special: string|null}
     */
    private function resolveDay(string $date, Collection $rules): array
    {
        $closed = $rules->firstWhere('type', OperatingHourRule::TYPE_CLOSED);

        if ($closed) {
            return ['closed' => $closed->reason, 'window' => null, 'blocked' => [], 'special' => null];
        }

        $special = $rules->where('type', OperatingHourRule::TYPE_SPECIAL_HOURS)->last();
        $defaults = $this->defaultHours();
        $window = $special
            ? $this->openWindow($date, (string) $special->start_time, (string) $special->end_time)
            : $this->openWindow($date, $defaults['opens_at'], $defaults['closes_at']);

        $blocked = $rules->where('type', OperatingHourRule::TYPE_PARTIAL_CLOSURE)
            ->map(fn (OperatingHourRule $rule) => [...$this->openWindow($date, (string) $rule->start_time, (string) $rule->end_time), 'reason' => $rule->reason])
            ->sortBy(fn (array $interval) => $interval['start']->getTimestamp())
            ->values()
            ->all();

        return ['closed' => null, 'window' => $window, 'blocked' => $blocked, 'special' => $special?->reason];
    }

    /**
     * An interval on one business date; a closing time at or before the opening time (such as 00:00) ends the next day.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    private function openWindow(string $date, string $opensAt, string $closesAt): array
    {
        $timezone = (string) config('casa.business_hours.timezone', config('app.timezone'));
        $start = Carbon::createFromFormat('!Y-m-d H:i', $date.' '.substr($opensAt, 0, 5), $timezone);
        $end = Carbon::createFromFormat('!Y-m-d H:i', $date.' '.substr($closesAt, 0, 5), $timezone);

        if ($end->lte($start)) {
            $end->addDay();
        }

        return ['start' => $start, 'end' => $end];
    }

    /** @return array{opens_at: string, closes_at: string} */
    private function defaultHours(): array
    {
        if ($this->defaultHours === null) {
            $settings = ApplicationSetting::current();
            $this->defaultHours = ['opens_at' => $settings->opensAt(), 'closes_at' => $settings->closesAt()];
        }

        return $this->defaultHours;
    }

    private function businessDate(CarbonInterface $day): string
    {
        return Carbon::parse($day, (string) config('casa.business_hours.timezone', config('app.timezone')))->toDateString();
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $interval
     * @param  array{start: Carbon, end: Carbon}  $boundary
     * @return array{start: Carbon, end: Carbon}|null
     */
    private function clamp(array $interval, array $boundary): ?array
    {
        $start = $interval['start']->greaterThan($boundary['start']) ? $interval['start'] : $boundary['start'];
        $end = $interval['end']->lessThan($boundary['end']) ? $interval['end'] : $boundary['end'];

        return $start->lt($end) ? ['start' => $start->copy(), 'end' => $end->copy()] : null;
    }

    /**
     * @param  Collection<int, array{start: Carbon, end: Carbon}>  $windows
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function merge(Collection $windows): Collection
    {
        $merged = collect();

        foreach ($windows->sortBy(fn (array $window) => $window['start']->getTimestamp()) as $window) {
            $lastIndex = $merged->keys()->last();

            if ($lastIndex === null || $merged[$lastIndex]['end']->lt($window['start'])) {
                $merged->push(['start' => $window['start']->copy(), 'end' => $window['end']->copy()]);

                continue;
            }

            if ($window['end']->gt($merged[$lastIndex]['end'])) {
                $merged[$lastIndex] = [
                    'start' => $merged[$lastIndex]['start'],
                    'end' => $window['end']->copy(),
                ];
            }
        }

        return $merged;
    }

    /**
     * @param  Collection<int, array{start: Carbon, end: Carbon}>  $windows
     * @param  array{start: Carbon, end: Carbon}  $blocked
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function subtract(Collection $windows, array $blocked): Collection
    {
        $remaining = collect();

        foreach ($windows as $window) {
            if ($blocked['start']->gte($window['end']) || $blocked['end']->lte($window['start'])) {
                $remaining->push($window);

                continue;
            }

            if ($blocked['start']->gt($window['start'])) {
                $remaining->push(['start' => $window['start']->copy(), 'end' => $blocked['start']->copy()]);
            }

            if ($blocked['end']->lt($window['end'])) {
                $remaining->push(['start' => $blocked['end']->copy(), 'end' => $window['end']->copy()]);
            }
        }

        return $remaining;
    }

    /**
     * @return Collection<int, StaffScheduleShift>|null Null means that no published roster exists yet.
     */
    private function rosterShiftsForDate(StaffProfile $staffProfile, Carbon $date): ?Collection
    {
        $weekStart = $date->copy()->startOfWeek(Carbon::SUNDAY)->toDateString();

        if (! array_key_exists($weekStart, $this->publishedWeeks)) {
            $this->publishedWeeks[$weekStart] = StaffScheduleWeek::query()
                ->whereNotNull('published_at')
                ->whereDate('week_start_date', '<=', $weekStart)
                ->orderByDesc('week_start_date')
                ->when($this->lockingReads, fn ($query) => $query->sharedLock())
                ->first();
        }

        $week = $this->publishedWeeks[$weekStart];

        if (! $week) {
            return null;
        }

        $sourceDate = Carbon::parse($week->week_start_date)->addDays($date->dayOfWeek)->toDateString();

        if (! array_key_exists($week->id, $this->publishedRosterShifts)) {
            $this->publishedRosterShifts[$week->id] = StaffScheduleShift::query()
                ->where('staff_schedule_week_id', $week->id)
                ->where('version', StaffScheduleShift::VERSION_PUBLISHED)
                ->orderBy('start_time')
                ->when($this->lockingReads, fn ($query) => $query->sharedLock())
                ->get()
                ->groupBy(fn (StaffScheduleShift $shift) => $shift->staff_profile_id.':'.$shift->schedule_date?->toDateString());
        }

        return $this->publishedRosterShifts[$week->id]
            ->get($staffProfile->id.':'.$sourceDate, collect());
    }
}
