<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddonRequest;
use App\Models\Addon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AddonController extends Controller
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
        ];
        $sort = $this->indexSort($request, $sorts, 'status');
        $direction = $this->indexDirection($request, $sort === 'status' ? 'desc' : 'asc');

        $addons = Addon::query()
            ->withCount('bookings')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy($sorts[$sort], $direction)
            ->orderBy('name')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();
        $summary = Addon::query()
            ->selectRaw('SUM(CASE WHEN is_active THEN 1 ELSE 0 END) AS active_count')
            ->selectRaw('SUM(CASE WHEN NOT is_active THEN 1 ELSE 0 END) AS inactive_count')
            ->first();

        return view('admin.addons.index', [
            'addons' => $addons,
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
        return view('admin.addons.create', [
            'addon' => new Addon(['is_active' => true]),
        ]);
    }

    public function store(AddonRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] = $this->uniqueCode($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        $addon = Addon::query()->create($data);

        return redirect()
            ->route('admin.addons.show', $addon)
            ->with('status', 'addon-created');
    }

    public function show(Addon $addon): View
    {
        $addon->loadCount('bookings');

        return view('admin.addons.show', [
            'addon' => $addon,
        ]);
    }

    public function edit(Addon $addon): View
    {
        return view('admin.addons.edit', [
            'addon' => $addon,
        ]);
    }

    public function update(AddonRequest $request, Addon $addon): RedirectResponse
    {
        // The code stays fixed: booked appointment lines and add-on vouchers reference it.
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $addon->update($data);

        return redirect()
            ->route('admin.addons.show', $addon)
            ->with('status', 'addon-updated');
    }

    public function toggle(Addon $addon): RedirectResponse
    {
        $addon->update([
            'is_active' => ! $addon->is_active,
        ]);

        $redirect = request()->headers->has('referer')
            ? back()
            : redirect()->route('admin.addons.index');

        return $redirect
            ->with('status', $addon->is_active ? 'addon-activated' : 'addon-deactivated');
    }

    private function uniqueCode(string $name): string
    {
        $baseCode = Str::slug($name) ?: 'addon';
        $code = $baseCode;
        $suffix = 2;

        while (Addon::query()->where('code', $code)->exists()) {
            $code = "{$baseCode}-{$suffix}";
            $suffix++;
        }

        return $code;
    }
}
