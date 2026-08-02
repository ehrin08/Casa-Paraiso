<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\StaffScheduleConflictException;
use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\StaffScheduleConflictGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');
        $bookable = (string) $request->query('bookable');
        $sorts = [
            'name' => 'users.name',
            'position' => 'staff_profiles.position',
            'services' => 'services_count',
            'appointments' => 'appointments_count',
            'status' => 'users.is_active',
            'bookable' => 'staff_profiles.is_bookable',
        ];
        $sort = $this->indexSort($request, $sorts, 'status');
        $direction = $this->indexDirection($request, $sort === 'status' ? 'desc' : 'asc');

        $staffProfiles = StaffProfile::query()
            ->with(['user', 'services'])
            ->withCount(['services', 'appointments'])
            ->join('users', 'users.id', '=', 'staff_profiles.user_id')
            ->select('staff_profiles.*')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.phone', 'like', "%{$search}%")
                    ->orWhere('staff_profiles.position', 'like', "%{$search}%")
                    ->orWhere('staff_profiles.specialization', 'like', "%{$search}%");
            }))
            ->when($status === 'active', fn ($query) => $query->where('users.is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('users.is_active', false))
            ->when($bookable === 'yes', fn ($query) => $query->where('staff_profiles.is_bookable', true))
            ->when($bookable === 'no', fn ($query) => $query->where('staff_profiles.is_bookable', false))
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('users.name')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();
        $accountSummary = User::query()
            ->where('role', User::ROLE_STAFF)
            ->selectRaw('SUM(CASE WHEN is_active THEN 1 ELSE 0 END) AS active_count')
            ->selectRaw('SUM(CASE WHEN NOT is_active THEN 1 ELSE 0 END) AS inactive_count')
            ->first();
        $serviceSummary = Service::query()
            ->selectRaw('SUM(CASE WHEN is_active THEN 1 ELSE 0 END) AS active_count')
            ->selectRaw('SUM(CASE WHEN NOT is_active THEN 1 ELSE 0 END) AS inactive_count')
            ->first();

        return view('admin.staff.index', [
            'staffProfiles' => $staffProfiles,
            'search' => $search,
            'status' => $status,
            'bookable' => $bookable,
            'sort' => $sort,
            'direction' => $direction,
            'activeAccountCount' => (int) $accountSummary?->active_count,
            'inactiveAccountCount' => (int) $accountSummary?->inactive_count,
            'bookableCount' => StaffProfile::query()->where('is_bookable', true)->count(),
            'serviceCatalog' => Service::query()
                ->withCount(['staffProfiles', 'appointments'])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate((int) config('casa.pagination.per_page', 15), ['*'], 'services_page')
                ->withQueryString()
                ->fragment('service-catalog'),
            'activeServiceCount' => (int) $serviceSummary?->active_count,
            'inactiveServiceCount' => (int) $serviceSummary?->inactive_count,
        ]);
    }

    public function create(): View
    {
        abort_unless(request()->user()->isSuperAdmin(), 403);

        return view('admin.staff.create', [
            'staffProfile' => new StaffProfile(['is_bookable' => true]),
            'staffUser' => new User(['is_active' => true]),
            'services' => $this->activeServices(),
            'assignedServiceIds' => [],
        ]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validated();

        $staffProfile = DB::transaction(function () use ($data, $request): StaffProfile {
            $staffUser = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'role' => User::ROLE_STAFF,
                'is_active' => $request->boolean('is_active'),
            ]);

            $staffProfile = $staffUser->staffProfile()->create([
                'staff_type' => $data['staff_type'] ?? StaffProfile::TYPE_THERAPIST,
                'position' => $data['position'] ?? null,
                'specialization' => $data['specialization'] ?? null,
                'bio' => $data['bio'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
                'is_bookable' => $request->boolean('is_bookable'),
            ]);

            $staffProfile->services()->sync($data['service_ids'] ?? []);

            return $staffProfile;
        });

        return redirect()
            ->route('admin.staff.show', $staffProfile)
            ->with('status', 'staff-created');
    }

    public function show(StaffProfile $staff): View
    {
        $staff->load([
            'user',
            'services' => fn ($query) => $query->orderBy('name'),
            'weeklySchedules' => fn ($query) => $query->orderBy('day_of_week')->orderBy('start_time'),
            'scheduleExceptions' => fn ($query) => $query
                ->with('creator')
                ->where('exception_date', '>=', today())
                ->orderBy('exception_date')
                ->orderBy('start_time'),
        ])
            ->loadCount(['services', 'appointments', 'weeklySchedules', 'scheduleExceptions']);

        return view('admin.staff.show', [
            'staffProfile' => $staff,
        ]);
    }

    public function edit(StaffProfile $staff): View
    {
        $staff->load(['user', 'services']);

        return view('admin.staff.edit', [
            'staffProfile' => $staff,
            'staffUser' => $staff->user,
            'services' => $this->activeServices($staff),
            'assignedServiceIds' => $staff->services->pluck('id')->all(),
        ]);
    }

    public function update(StaffRequest $request, StaffProfile $staff, StaffScheduleConflictGuard $guard): RedirectResponse
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($data, $guard, $request, $staff): void {
                $managedStaff = StaffProfile::query()->lockForUpdate()->findOrFail($staff->id);
                $staffUser = $managedStaff->user()->lockForUpdate()->firstOrFail();
                $newActive = $request->boolean('is_active');
                $newBookable = $request->boolean('is_bookable');
                $serviceIds = collect($data['service_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
                $removedServiceIds = $managedStaff->services()
                    ->pluck('services.id')
                    ->map(fn ($id) => (int) $id)
                    ->diff($serviceIds)
                    ->values()
                    ->all();

                if (($staffUser->is_active && ! $newActive) || ($managedStaff->is_bookable && ! $newBookable)) {
                    $guard->assertCanMakeUnavailable($managedStaff);
                } else {
                    $guard->assertCanRemoveServices($managedStaff, $removedServiceIds);
                }

                $staffUser->update([
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'is_active' => $newActive,
                ]);

                $managedStaff->update([
                    'staff_type' => $data['staff_type'] ?? $managedStaff->staff_type,
                    'position' => $data['position'] ?? null,
                    'specialization' => $data['specialization'] ?? null,
                    'bio' => $data['bio'] ?? null,
                    'hire_date' => $data['hire_date'] ?? null,
                    'is_bookable' => $newBookable,
                ]);

                $managedStaff->services()->sync($serviceIds->all());
            });
        } catch (StaffScheduleConflictException $exception) {
            return $this->eligibilityConflictRedirect($exception);
        }

        return redirect()
            ->route('admin.staff.show', $staff)
            ->with('status', 'staff-updated');
    }

    private function activeServices(?StaffProfile $staff = null)
    {
        $assignedServiceIds = $staff?->services()->pluck('services.id')->all() ?? [];

        return Service::query()
            ->where(function ($query) use ($assignedServiceIds): void {
                $query->where('is_active', true);

                if ($assignedServiceIds !== []) {
                    $query->orWhereIn('id', $assignedServiceIds);
                }
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    private function eligibilityConflictRedirect(StaffScheduleConflictException $exception): RedirectResponse
    {
        return back()->withErrors([
            'staff_eligibility' => __('Change blocked because it would make a therapist ineligible for a future confirmed appointment. Reschedule or cancel the affected visit first.'),
        ])->with('eligibility_conflicts', collect($exception->conflicts)->map(fn (array $conflict) => [
            ...$conflict,
            'url' => route('admin.appointments.show', $conflict['id']),
        ])->all())->withInput();
    }
}
