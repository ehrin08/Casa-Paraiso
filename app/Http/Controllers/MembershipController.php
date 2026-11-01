<?php

namespace App\Http\Controllers;

use App\Http\Requests\MembershipRequest;
use App\Models\CustomerMembership;
use App\Models\CustomerProfile;
use App\Models\MembershipReferralCredit;
use App\Models\MembershipTier;
use App\Services\MembershipRecommendations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Membership cards for admins and receptionists; only admins may cancel a card. */
class MembershipController extends Controller
{
    public function index(Request $request, MembershipRecommendations $recommendations): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tier' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'expiring', 'expired', 'cancelled'])],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $tierId = $filters['tier'] ?? null;
        $today = CustomerMembership::businessDate();
        $soon = Carbon::parse($today)->addDays((int) config('casa.memberships.expiring_soon_days', 30))->toDateString();

        $memberships = CustomerMembership::query()
            ->with(['customerProfile.user', 'tier'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('card_number', 'like', "%{$search}%")
                ->orWhereHas('customerProfile', fn (Builder $customers) => $customers->search($search))))
            ->when($tierId, fn (Builder $query) => $query->where('membership_tier_id', $tierId))
            ->when($status === 'active', fn (Builder $query) => $query->activeOn())
            ->when($status === 'expiring', fn (Builder $query) => $query->activeOn()->whereDate('valid_until', '<=', $soon))
            ->when($status === 'expired', fn (Builder $query) => $query->where('status', CustomerMembership::STATUS_ACTIVE)->whereDate('valid_until', '<', $today))
            ->when($status === 'cancelled', fn (Builder $query) => $query->where('status', CustomerMembership::STATUS_CANCELLED))
            ->latest('issued_at')->latest('id')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('memberships.index', [
            'memberships' => $memberships,
            'tiers' => MembershipTier::query()->ordered()->get(),
            'search' => $search,
            'status' => $status,
            'tierId' => $tierId,
            'summary' => [
                'active' => CustomerMembership::query()->activeOn()->count(),
                'expiring' => CustomerMembership::query()->activeOn()->whereDate('valid_until', '<=', $soon)->count(),
                'expired' => CustomerMembership::query()->where('status', CustomerMembership::STATUS_ACTIVE)->whereDate('valid_until', '<', $today)->count(),
                'credits' => MembershipReferralCredit::query()->whereNull('used_at')->count(),
            ],
            'recommendations' => $recommendations->list(10),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function create(Request $request): View
    {
        $tiers = MembershipTier::query()->where('is_active', true)->ordered()->get();
        $tier = $tiers->firstWhere('id', (int) $request->query('tier')) ?? $tiers->last();

        return view('memberships.create', [
            'tiers' => $tiers,
            'customers' => CustomerProfile::query()->with('user')->orderBy('id')->get(),
            'selectedCustomer' => (int) $request->query('customer'),
            'selectedTier' => $tier?->id,
            'cardNumber' => CustomerMembership::nextCardNumber(),
            'issuedAt' => CustomerMembership::businessDate(),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function store(MembershipRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $membership = DB::transaction(function () use ($data, $request): CustomerMembership {
            CustomerProfile::query()->lockForUpdate()->findOrFail($data['customer_profile_id']);

            if (CustomerMembership::query()->where('customer_profile_id', $data['customer_profile_id'])->where('status', CustomerMembership::STATUS_ACTIVE)->whereDate('valid_until', '>=', $data['issued_at'])->exists()) {
                throw ValidationException::withMessages(['customer_profile_id' => __('This customer already has an active membership card. Renew or cancel it instead.')]);
            }

            $tier = MembershipTier::query()->findOrFail($data['membership_tier_id']);
            $membership = CustomerMembership::query()->create([
                ...$data,
                'valid_until' => $data['valid_until'] ?? Carbon::parse($data['issued_at'])->addMonths($tier->validity_months)->subDay()->toDateString(),
                'status' => CustomerMembership::STATUS_ACTIVE,
                'issued_by' => $request->user()->id,
            ]);

            // The card's referral reward: the new member and the member who referred them each get credit.
            if (! empty($data['referred_by_customer_profile_id']) && (float) $tier->referral_reward_amount > 0) {
                foreach ([$membership->customer_profile_id, (int) $data['referred_by_customer_profile_id']] as $customerId) {
                    MembershipReferralCredit::query()->create([
                        'customer_profile_id' => $customerId,
                        'customer_membership_id' => $membership->id,
                        'amount' => $tier->referral_reward_amount,
                    ]);
                }
            }

            return $membership;
        }, 3);

        return to_route($this->routePrefix($request).'memberships.show', $membership)->with('status', 'membership-issued');
    }

    public function show(Request $request, CustomerMembership $membership): View
    {
        $membership->load(['customerProfile.user', 'tier', 'referrer', 'issuer', 'referralCredits.customerProfile', 'perkUsages']);

        return view('memberships.show', [
            'membership' => $membership,
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function renew(Request $request, CustomerMembership $membership): RedirectResponse
    {
        DB::transaction(function () use ($membership): void {
            $membership = CustomerMembership::query()->with('tier')->lockForUpdate()->findOrFail($membership->id);

            if ($membership->status !== CustomerMembership::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['membership' => __('A cancelled card cannot be renewed. Issue a new card instead.')]);
            }

            $start = Carbon::parse(max($membership->valid_until->toDateString(), CustomerMembership::businessDate()));
            $membership->forceFill(['valid_until' => $start->addMonths($membership->tier->validity_months)->toDateString()])->save();
        }, 3);

        return back()->with('status', 'membership-renewed');
    }

    public function cancel(Request $request, CustomerMembership $membership): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $membership->forceFill([
            'status' => CustomerMembership::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ])->save();

        return back()->with('status', 'membership-cancelled');
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isReceptionist() ? 'reception.' : 'admin.';
    }
}
