<?php

namespace App\Services;

use App\Models\StaffProfile;
use App\Models\StaffScheduleShift;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TherapistSchedulePage
{
    public function __construct(private readonly WeeklyRoster $roster, private readonly ScheduleWindowResolver $windows) {}

    public function build(array $filters, ?array $state = null): array
    {
        $state ??= $this->roster->snapshot($filters['week'] ?? now('Asia/Manila')->toDateString());
        $filters['week'] = $state['start']->toDateString();
        $filters['view'] = ($filters['view'] ?? 'draft') === 'draft' && $state['has_draft'] ? 'draft' : 'live';
        $staff = StaffProfile::query()->join('users', 'users.id', '=', 'staff_profiles.user_id')->select('staff_profiles.*')
            ->with(['user', 'scheduleExceptions' => fn ($query) => $query->whereBetween('exception_date', [
                $state['start']->toDateString(), $state['start']->copy()->addDays(6)->toDateString(),
            ])->orderBy('exception_date')->orderBy('id')])
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where('users.name', 'like', '%'.$search.'%'))
            ->when($filters['staff_profile_id'] ?? null, fn ($query, $id) => $query->where('staff_profiles.id', $id))
            ->orderBy('users.name')->orderBy('staff_profiles.id')
            ->paginate((int) config('casa.pagination.per_page', 15), ['*'], 'page', max(1, (int) ($filters['page'] ?? 1)))->appends($filters);
        $days = collect(range(0, 6))->map(function (int $offset) use ($state): array {
            $date = $state['start']->copy()->addDays($offset);

            return ['date' => $date->toDateString(), 'label' => $date->format('D'), 'heading' => $date->format('D, M j'),
                'editable' => $state['editable'] && $date->toDateString() >= now('Asia/Manila')->toDateString()];
        });
        $rows = $filters['view'] === 'draft' ? $state['draft_shifts'] : $state['live_shifts'];
        $byCell = $rows->groupBy(fn ($row) => $row['staff_profile_id'].':'.$row['schedule_date']);
        $cells = [];
        foreach ($staff as $person) {
            foreach ($days as $day) {
                $ranges = $byCell->get($person->id.':'.$day['date'], collect())->sortBy('start_time')->values();
                $effective = $this->windows->effectiveWindows($person, Carbon::parse($day['date'], 'Asia/Manila'),
                    $ranges->map(fn ($row) => new StaffScheduleShift($row)));
                $cells[$person->id.':'.$day['date']] = [
                    'ranges' => $ranges,
                    'effective' => $effective->map(fn ($window) => [
                        'start' => $window['start']->format('g:i A'), 'end' => $window['end']->format('H:i') === '00:00' ? __('12:00 midnight') : $window['end']->format('g:i A'),
                    ]),
                    'exceptions' => $person->scheduleExceptions->filter(fn ($exception) => $exception->exception_date->toDateString() === $day['date']),
                    'can_edit' => $day['editable'] && $filters['view'] === 'draft',
                    'can_add' => $day['editable'] && $filters['view'] === 'draft' && $person->user->is_active && $person->user->isStaff(),
                ];
            }
        }
        $conflicts = $state['has_draft'] ? $this->roster->review($state, $state['draft_shifts'])['conflicts'] : [];
        $perPage = (int) config('casa.pagination.per_page', 15);
        $conflictPage = max(1, min((int) ($filters['conflicts_page'] ?? 1), max(1, (int) ceil(count($conflicts) / $perPage))));
        $conflictPaginator = new LengthAwarePaginator(array_slice($conflicts, ($conflictPage - 1) * $perPage, $perPage),
            count($conflicts), $perPage, $conflictPage, ['path' => route('admin.staff-roster.index'), 'pageName' => 'conflicts_page']);
        $conflictPaginator->appends($filters);
        $times = collect(range(780, 1410, 30))->mapWithKeys(fn ($minutes) => [
            sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60) => date('g:i A', mktime(intdiv($minutes, 60), $minutes % 60, 0)),
        ]);

        return [...$state, 'filters' => $filters, 'staffProfiles' => $staff, 'days' => $days, 'cells' => $cells,
            'conflictPaginator' => $conflictPaginator, 'conflictCount' => count($conflicts), 'times' => $times,
            'endTimes' => $times->except('13:00')->put('00:00', __('12:00 midnight')),
            'indexUrl' => route('admin.staff-roster.index', $filters),
        ];
    }

    public function reviewNames(Collection $changes): Collection
    {
        return StaffProfile::withTrashed()->with('user')->whereKey($changes->pluck('staff_profile_id')->unique()->all())
            ->get()->mapWithKeys(fn ($staff) => [$staff->id => $staff->user?->name ?? __('Unavailable therapist')]);
    }
}
