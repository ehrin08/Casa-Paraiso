<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MembershipTierRequest;
use App\Models\Addon;
use App\Models\MembershipTier;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MembershipTierController extends Controller
{
    public function index(): View
    {
        return view('admin.membership-tiers.index', [
            'tiers' => MembershipTier::query()->withCount(['memberships' => fn ($query) => $query->activeOn()])->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new MembershipTier(['is_active' => true, 'validity_months' => 12, 'stars' => 3, 'addon_discount_codes' => []]));
    }

    public function store(MembershipTierRequest $request): RedirectResponse
    {
        $data = $this->attributes($request);
        $base = Str::slug($data['name']) ?: 'tier';
        $code = $base;
        for ($suffix = 2; MembershipTier::query()->where('code', $code)->exists(); $suffix++) {
            $code = "{$base}-{$suffix}";
        }

        MembershipTier::query()->create([...$data, 'code' => $code]);

        return to_route('admin.membership-tiers.index')->with('status', 'membership-tier-created');
    }

    public function edit(MembershipTier $membershipTier): View
    {
        return $this->form($membershipTier);
    }

    public function update(MembershipTierRequest $request, MembershipTier $membershipTier): RedirectResponse
    {
        $membershipTier->update($this->attributes($request));

        return to_route('admin.membership-tiers.index')->with('status', 'membership-tier-updated');
    }

    private function form(MembershipTier $tier): View
    {
        return view('admin.membership-tiers.form', [
            'tier' => $tier,
            'addons' => Addon::query()->orderBy('name')->get(),
            'services' => Service::query()->orderBy('name')->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function attributes(MembershipTierRequest $request): array
    {
        $data = $request->validated();
        $data['addon_discount_codes'] = array_values($data['addon_discount_codes'] ?? []);
        $data['is_active'] = $request->boolean('is_active');
        $data['addon_discount_percent'] ??= 0;
        $data['service_discount_percent'] ??= 0;
        $data['referral_reward_amount'] ??= 0;

        if (! empty($data['birthday_treat_addon_code'])) {
            $data['birthday_treat_service_id'] = null;
        }

        return $data;
    }
}
