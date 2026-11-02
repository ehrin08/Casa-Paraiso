<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OperatingHourRuleRequest;
use App\Http\Requests\Admin\OperatingHoursRequest;
use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\OperatingHourRule;
use App\Services\OperatingHoursGuard;
use App\Services\ScheduleWindowResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class OperatingHoursController extends Controller
{
    public function __construct(private readonly OperatingHoursGuard $guard) {}

    public function index(Request $request, ScheduleWindowResolver $windows): View
    {
        $timezone = $this->timezone();
        $today = now($timezone)->startOfDay();
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) === 1
            ? Carbon::createFromFormat('!Y-m', (string) $request->query('month'), $timezone)
            : $today->copy()->startOfMonth();
        $selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date')) === 1 ? (string) $request->query('date') : null;

        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY)->startOfDay();
        $hours = $windows->hoursBetween($gridStart, $gridEnd);

        return view('admin.operating-hours.index', [
            'settings' => ApplicationSetting::current(),
            'month' => $month,
            'today' => $today,
            'selectedDate' => $selectedDate,
            'calendarDays' => collect($hours)->map(fn (array $day, string $date) => [
                'date' => $date,
                'day' => (int) substr($date, 8, 2),
                'in_month' => substr($date, 0, 7) === $month->format('Y-m'),
                'past' => $date < $today->toDateString(),
                ...$this->describeDay($day),
            ])->values(),
            'rules' => OperatingHourRule::query()
                ->with('creator')
                ->covering($today, '9999-12-31')
                ->orderBy('starts_on')
                ->orderBy('id')
                ->paginate(config('casa.pagination.per_page', 15))
                ->withQueryString(),
            'conflicts' => collect(session('operating_hours_conflicts', [])),
            'timeOptions' => $this->timeOptions(),
        ]);
    }

    public function updateDefaults(OperatingHoursRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $conflicts = $this->guard->attempt(
            fn () => ApplicationSetting::updateCurrent([...$data, 'updated_by' => $request->user()->id]),
            now($this->timezone())->toDateString(),
            null,
        );

        if ($conflicts->isNotEmpty()) {
            return $this->refuse($conflicts, 'opens_at');
        }

        return to_route('admin.operating-hours.index')->with('status', 'operating-hours-updated');
    }

    public function storeRule(OperatingHourRuleRequest $request): RedirectResponse
    {
        $conflicts = $this->guard->attempt(
            fn () => OperatingHourRule::query()->create([...$request->ruleAttributes(), 'created_by' => $request->user()->id]),
            $request->startsOn(),
            $request->endsOn(),
        );

        if ($conflicts->isNotEmpty()) {
            return $this->refuse($conflicts, 'starts_on');
        }

        return to_route('admin.operating-hours.index', ['month' => substr($request->startsOn(), 0, 7)])
            ->with('status', 'operating-hour-rule-created');
    }

    /** Stop a rule that has already started; it stays on record for the days it covered. */
    public function endRule(OperatingHourRule $rule): RedirectResponse
    {
        $today = now($this->timezone())->toDateString();

        if ($rule->starts_on->toDateString() >= $today) {
            return $this->destroyRule($rule);
        }

        $conflicts = $this->guard->attempt(
            fn () => $rule->update(['ends_on' => Carbon::parse($today)->subDay()->toDateString()]),
            $today,
            $rule->ends_on?->toDateString(),
        );

        if ($conflicts->isNotEmpty()) {
            return $this->refuse($conflicts, 'rules');
        }

        return to_route('admin.operating-hours.index')->with('status', 'operating-hour-rule-ended');
    }

    public function destroyRule(OperatingHourRule $rule): RedirectResponse
    {
        $today = now($this->timezone())->toDateString();
        $from = max($rule->starts_on->toDateString(), $today);

        $conflicts = $this->guard->attempt(fn () => $rule->delete(), $from, $rule->ends_on?->toDateString());

        if ($conflicts->isNotEmpty()) {
            return $this->refuse($conflicts, 'rules');
        }

        return to_route('admin.operating-hours.index')->with('status', 'operating-hour-rule-deleted');
    }

    /** @param  Collection<int, Appointment>  $conflicts */
    private function refuse(Collection $conflicts, string $field): RedirectResponse
    {
        $timezone = $this->timezone();

        return back()->withInput()
            ->withErrors([$field => trans_choice('This change leaves :count confirmed appointment outside opening hours. Move or cancel it first.|This change leaves :count confirmed appointments outside opening hours. Move or cancel them first.', $conflicts->count())])
            ->with('operating_hours_conflicts', $conflicts->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'time' => $appointment->scheduled_start_at?->copy()->timezone($timezone)->format('M j, g:i A'),
                'customer' => $appointment->customerProfile?->display_name ?? __('Customer'),
                'therapist' => $appointment->staffProfile?->user?->name ?? __('Unassigned'),
            ])->all());
    }

    /**
     * @param  array{closed: string|null, window: array{start: Carbon, end: Carbon}|null, blocked: list<array{start: Carbon, end: Carbon, reason: string}>, special: string|null}  $day
     * @return array{status: string, label: string, note: string|null}
     */
    private function describeDay(array $day): array
    {
        if ($day['closed'] !== null) {
            return ['status' => 'closed', 'label' => __('Closed'), 'note' => $day['closed']];
        }

        $blocked = collect($day['blocked'])->map(fn (array $interval) => __('Closed :range', [
            'range' => $this->shortRange($interval['start'], $interval['end']),
        ]))->implode('; ');

        return [
            'status' => $blocked !== '' ? 'partial' : ($day['special'] !== null ? 'special' : 'open'),
            // Default days just say Open so the cells stay readable; changed days show their times.
            'label' => $day['special'] === null && $blocked === '' ? __('Open') : $this->shortRange($day['window']['start'], $day['window']['end']),
            'note' => collect([$day['special'], $blocked ?: null])->filter()->implode(' · ') ?: null,
        ];
    }

    /** A compact range for calendar cells, such as 3–8 PM or 1:30 PM–12 AM. */
    private function shortRange(Carbon $start, Carbon $end): string
    {
        $time = fn (Carbon $at) => $at->format($at->minute === 0 ? 'g' : 'g:i');

        return $start->format('A') === $end->format('A') && $start->isSameDay($end)
            ? $time($start).'–'.$time($end).' '.$end->format('A')
            : $time($start).' '.$start->format('A').'–'.$time($end).' '.$end->format('A');
    }

    /** @return array<string, string> H:i => label, on 30-minute steps; 00:00 doubles as midnight. */
    private function timeOptions(): array
    {
        $options = [];

        for ($minutes = 0; $minutes < 1440; $minutes += 30) {
            $time = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            $options[$time] = ApplicationSetting::timeLabel($time);
        }

        return $options;
    }

    private function timezone(): string
    {
        return (string) config('casa.business_hours.timezone', 'Asia/Manila');
    }
}
