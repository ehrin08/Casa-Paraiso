<?php

namespace App\Services;

use App\Models\CustomerMembership;
use App\Models\CustomerProfile;
use App\Models\MembershipTier;
use Illuminate\Support\Collection;

/**
 * Links RFM segments to membership tiers: a customer whose current segment recommends a tier
 * above their active card (or who has no card) is a candidate for that tier.
 */
class MembershipRecommendations
{
    public function __construct(
        private readonly RfmScorer $scorer,
        private readonly RfmRuleEngine $rules,
    ) {}

    /** @return Collection<int, array{customer: CustomerProfile, tier: MembershipTier, segment: string, scores: array{r: int, f: int, m: int}, current: ?MembershipTier}> */
    public function list(?int $limit = null): Collection
    {
        $segments = $this->rules->rules()->load('recommendedMembershipTier');

        if ($segments->every(fn ($segment) => ! $segment->recommendedMembershipTier?->is_active)) {
            return collect();
        }

        $population = $this->scorer->population();
        $candidates = $this->scorer->scoreAll($population)
            ->map(fn (array $scores, int $customerId) => ['customer_id' => $customerId, 'scores' => $scores, 'segment' => $this->rules->match($scores, $segments)])
            ->filter(fn (array $row) => $row['segment']?->recommendedMembershipTier?->is_active);
        $current = CustomerMembership::query()->activeOn()->with('tier')->whereIn('customer_profile_id', $candidates->keys())->get()
            ->groupBy('customer_profile_id')->map(fn ($memberships) => $memberships->sortByDesc(fn ($membership) => $membership->tier->rank)->first()->tier);
        $customers = CustomerProfile::query()->with('user')->whereIn('id', $candidates->keys())->get()->keyBy('id');

        $rows = $candidates
            ->filter(fn (array $row, int $customerId) => $customers->has($customerId)
                && (! $current->has($customerId) || $current[$customerId]->rank < $row['segment']->recommendedMembershipTier->rank))
            ->map(fn (array $row, int $customerId) => [
                'customer' => $customers[$customerId],
                'tier' => $row['segment']->recommendedMembershipTier,
                'segment' => $row['segment']->name,
                'scores' => $row['scores'],
                'current' => $current[$customerId] ?? null,
            ])
            ->sortByDesc(fn (array $row) => [$row['tier']->rank, array_sum($row['scores'])])
            ->values();

        return $limit ? $rows->take($limit) : $rows;
    }

    public function forCustomer(CustomerProfile $customer): ?MembershipTier
    {
        return $this->list()->first(fn (array $row) => $row['customer']->is($customer))['tier'] ?? null;
    }
}
