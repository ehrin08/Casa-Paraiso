<?php

namespace App\Services;

use App\Models\CustomerProfile;
use App\Models\RfmSegment;
use Illuminate\Support\Collection;

/**
 * Priority-ordered decision table: each active segment is a rule of R/F/M score ranges,
 * evaluated in priority order, and the first rule a customer satisfies wins.
 */
class RfmRuleEngine
{
    public function __construct(private readonly RfmScorer $scorer) {}

    /**
     * The customer's current metrics, scores and matching segment, or null without paid visits.
     *
     * @return array{metrics: array{recency_days: int, frequency_count: int, monetary_total: float}, scores: array{r: int, f: int, m: int}, segment: ?RfmSegment}|null
     */
    public function profileFor(CustomerProfile $customer): ?array
    {
        $population = $this->scorer->population();
        $metrics = $population->get($customer->id);

        if ($metrics === null) {
            return null;
        }

        $scores = $this->scorer->score($metrics, $population);

        return ['metrics' => $metrics, 'scores' => $scores, 'segment' => $this->match($scores)];
    }

    /** @return Collection<int, RfmSegment> */
    public function rules(): Collection
    {
        return RfmSegment::query()->where('is_active', true)->ordered()->get();
    }

    /**
     * @param  array{r: int, f: int, m: int}  $scores
     * @param  Collection<int, RfmSegment>|null  $rules
     */
    public function match(array $scores, ?Collection $rules = null): ?RfmSegment
    {
        return ($rules ?? $this->rules())->first(fn (RfmSegment $segment) => $segment->is_active && $segment->matchesScores($scores));
    }

    /**
     * How many customers an unsaved or edited rule fits, and how many it would actually take,
     * since customers already matched by a rule above it stay there. Inactive rules take nobody.
     *
     * @param  Collection<int, array{r: int, f: int, m: int}>  $scores
     * @param  Collection<int, RfmSegment>  $rulesAbove
     * @return array{fits: int, lands: int}
     */
    public function preview(RfmSegment $candidate, Collection $scores, Collection $rulesAbove): array
    {
        $fitting = $scores->filter(fn (array $customerScores) => $candidate->matchesScores($customerScores));

        return [
            'fits' => $fitting->count(),
            'lands' => $candidate->is_active ? $fitting->reject(fn (array $customerScores) => $this->match($customerScores, $rulesAbove) !== null)->count() : 0,
        ];
    }

    /**
     * Active rules a customer is checked against before the given one: all of them for a new rule,
     * which is saved last, otherwise those ordered before it.
     *
     * @return Collection<int, RfmSegment>
     */
    public function rulesAbove(RfmSegment $segment): Collection
    {
        $rules = $this->rules()->reject(fn (RfmSegment $rule) => $rule->is($segment));

        if (! $segment->exists) {
            return $rules->values();
        }

        return $rules->filter(fn (RfmSegment $rule) => $rule->priority < $segment->priority
            || ($rule->priority === $segment->priority && $rule->id < $segment->id))->values();
    }
}
