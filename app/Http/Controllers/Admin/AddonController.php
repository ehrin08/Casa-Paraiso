<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddonRequest;
use App\Models\Addon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AddonController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');
        $addons = Addon::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate((int) config('casa.pagination.per_page', 15))->withQueryString();
        return view('admin.addons.index', compact('addons', 'search', 'status'));
    }

    public function create(): View { return view('admin.addons.create', ['addon' => new Addon(['is_active' => true])]); }

    public function store(AddonRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] = $this->uniqueCode($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $addon = Addon::query()->create($data);
        return redirect()->route('admin.addons.edit', $addon)->with('status', 'addon-created');
    }

    public function edit(Addon $addon): View { return view('admin.addons.edit', compact('addon')); }

    public function update(AddonRequest $request, Addon $addon): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $addon->update($data);
        return redirect()->route('admin.addons.edit', $addon)->with('status', 'addon-updated');
    }

    public function toggle(Addon $addon): RedirectResponse
    {
        $addon->update(['is_active' => ! $addon->is_active]);
        return back()->with('status', $addon->is_active ? 'addon-activated' : 'addon-deactivated');
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'addon'; $code = $base; $suffix = 2;
        while (Addon::query()->where('code', $code)->exists()) { $code = "{$base}-{$suffix}"; $suffix++; }
        return $code;
    }
}
