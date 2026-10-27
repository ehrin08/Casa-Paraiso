<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeamProfileRequest;
use App\Models\User;
use App\Services\UserPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public team cards are part of each staff account; this screen publishes them on the homepage chart. */
class TeamProfileController extends Controller
{
    public function __construct(private readonly UserPhotoService $photos) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $role = (string) $request->query('role');
        $visibility = (string) $request->query('visibility');
        $roles = array_values(array_diff(User::ROLES, [User::ROLE_CUSTOMER]));

        $members = User::query()
            ->teamMembers()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('public_title', 'like', "%{$search}%");
            }))
            ->when(in_array($role, $roles, true), fn ($query) => $query->where('role', $role))
            ->when($visibility === 'shown', fn ($query) => $query->publiclyListed())
            ->when($visibility === 'hidden', fn ($query) => $query->whereNotIn('id', User::query()->publiclyListed()->select('id')))
            ->orderByDesc('show_on_org_chart')
            ->orderByRaw("CASE org_chart_tier WHEN 'owner' THEN 0 WHEN 'leadership' THEN 1 WHEN 'team' THEN 2 ELSE 3 END")
            ->orderBy('org_chart_order')
            ->orderBy('name')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        $total = User::query()->teamMembers()->count();
        $shown = User::query()->publiclyListed()->count();

        return view('admin.team.index', [
            'members' => $members,
            'search' => $search,
            'role' => $role,
            'visibility' => $visibility,
            'roles' => $roles,
            'shownCount' => $shown,
            'hiddenCount' => $total - $shown,
            'missingPhotoCount' => User::query()->publiclyListed()->whereNull('photo_path')->count(),
        ]);
    }

    public function edit(Request $request, User $member): View
    {
        $this->authorizeMember($request, $member);

        return view('admin.team.edit', [
            'member' => $member,
        ]);
    }

    public function update(TeamProfileRequest $request, User $member): RedirectResponse
    {
        $this->authorizeMember($request, $member);

        $data = $request->safe()->only(['public_title', 'public_bio', 'org_chart_tier']);
        $data['org_chart_order'] = (int) $request->input('org_chart_order', 0);
        $data['show_on_org_chart'] = $request->boolean('show_on_org_chart');

        $this->photos->save($member, $data, $request->file('photo'), $request->boolean('remove_photo'));

        return redirect()
            ->route('admin.team.index')
            ->with('status', 'team-card-updated');
    }

    private function authorizeMember(Request $request, User $member): void
    {
        abort_if($member->isCustomer(), 404);
        abort_if($member->role === User::ROLE_SUPER_ADMIN && ! $request->user()->isSuperAdmin(), 403);
    }
}
