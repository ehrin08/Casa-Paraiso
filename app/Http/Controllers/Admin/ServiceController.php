<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');
        $sorts = [
            'name' => 'name',
            'duration' => 'duration_minutes',
            'price' => 'price',
            'status' => 'is_active',
            'staff' => 'staff_profiles_count',
            'appointments' => 'appointments_count',
        ];
        $sort = $this->indexSort($request, $sorts, 'status');
        $direction = $this->indexDirection($request, $sort === 'status' ? 'desc' : 'asc');

        $services = Service::query()
            ->withCount(['staffProfiles', 'appointments'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('name')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();
        $summary = Service::query()
            ->selectRaw('SUM(CASE WHEN is_active THEN 1 ELSE 0 END) AS active_count')
            ->selectRaw('SUM(CASE WHEN NOT is_active THEN 1 ELSE 0 END) AS inactive_count')
            ->first();

        return view('admin.services.index', [
            'services' => $services,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'activeCount' => (int) $summary?->active_count,
            'inactiveCount' => (int) $summary?->inactive_count,
        ]);
    }

    public function create(): View
    {
        return view('admin.services.create', [
            'service' => new Service(['is_active' => true]),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        $service = Service::query()->create($data);

        return redirect()
            ->route('admin.services.show', $service)
            ->with('status', 'service-created');
    }

    public function show(Service $service): View
    {
        $service->loadCount(['staffProfiles', 'appointments', 'transactions']);

        return view('admin.services.show', [
            'service' => $service,
        ]);
    }

    public function edit(Service $service): View
    {
        return view('admin.services.edit', [
            'service' => $service,
        ]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        if ($service->name !== $data['name']) {
            $data['slug'] = $this->uniqueSlug($data['name'], $service);
        }

        $service->update($data);

        return redirect()
            ->route('admin.services.show', $service)
            ->with('status', 'service-updated');
    }

    public function toggle(Service $service): RedirectResponse
    {
        $service->update([
            'is_active' => ! $service->is_active,
        ]);

        $redirect = request()->headers->has('referer')
            ? back()
            : redirect()->route('admin.services.index');

        return $redirect
            ->with('status', $service->is_active ? 'service-activated' : 'service-deactivated');
    }

    private function uniqueSlug(string $name, ?Service $service = null): string
    {
        $baseSlug = Str::slug($name) ?: 'service';
        $slug = $baseSlug;
        $suffix = 2;

        while (
            Service::withTrashed()
                ->where('slug', $slug)
                ->when($service, fn ($query) => $query->whereKeyNot($service->getKey()))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
