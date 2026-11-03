<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * RFM analysis with percentile-rank quintile scoring.
 *
 * Each customer gets a Recency, Frequency and Monetary score from 1 (worst) to 5 (best) from
 * their percent rank, (customers strictly worse) / (population - 1), split into fifths. The top
 * fifth scores 5, the bottom fifth scores 1, and tied customers share the lower score. A lone
 * customer has nobody to rank against and scores the neutral 3.
 */
class RfmScorer
{
    /**
     * Paid, completed visit totals per customer as of a moment in time.
     *
     * @return Collection<int, array{recency_days: int, frequency_count: int, monetary_total: float}>
     */
    public function population(?CarbonInterface $asOf = null): Collection
    {
        $asOf ??= now();

        return Transaction::query()
            ->excludingImportWalkIn()
            ->where('payment_status', Transaction::PAYMENT_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', $asOf)
            ->whereHas('appointment', fn ($query) => $query->where('status', Appointment::STATUS_COMPLETED))
            ->selectRaw('customer_profile_id, MAX(paid_at) as last_paid, COUNT(*) as frequency, SUM(amount) as monetary')
            ->groupBy('customer_profile_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->customer_profile_id => $this->metrics($row->last_paid, (int) $row->frequency, (float) $row->monetary, $asOf)]);
    }

    /** @return array{recency_days: int, frequency_count: int, monetary_total: float} */
    public function metrics(mixed $lastPaidAt, int $frequency, float $monetary, ?CarbonInterface $asOf = null): array
    {
        $asOf ??= now();

        return [
            'recency_days' => $lastPaidAt ? (int) max(0, Carbon::parse($lastPaidAt)->diffInDays($asOf)) : PHP_INT_MAX,
            'frequency_count' => $frequency,
            'monetary_total' => $monetary,
        ];
    }

    /**
     * @param  array{recency_days: int, frequency_count: int, monetary_total: float}  $metrics
     * @param  Collection<int, array{recency_days: int, frequency_count: int, monetary_total: float}>  $population
     * @return array{r: int, f: int, m: int}
     */
    public function score(array $metrics, Collection $population): array
    {
        return $this->scorer($population->isEmpty() ? collect([$metrics]) : $population)($metrics);
    }

    /**
     * Scores every member of the population, keyed like the population.
     *
     * @param  Collection<int, array{recency_days: int, frequency_count: int, monetary_total: float}>  $population
     * @return Collection<int, array{r: int, f: int, m: int}>
     */
    public function scoreAll(Collection $population): Collection
    {
        $scorer = $this->scorer($population);

        return $population->map(fn (array $metrics) => $scorer($metrics));
    }

    /**
     * The observed value range that earns each score, for explaining the current quintiles.
     *
     * @param  Collection<int, array{recency_days: int, frequency_count: int, monetary_total: float}>  $population
     * @return array<string, array<int, array{min: float|int, max: float|int}|null>>
     */
    public function cutoffs(Collection $population): array
    {
        $scores = $this->scoreAll($population);
        $cutoffs = [];

        foreach (['r' => 'recency_days', 'f' => 'frequency_count', 'm' => 'monetary_total'] as $metric => $field) {
            $cutoffs[$metric] = array_fill(1, 5, null);

            foreach ($population as $key => $metrics) {
                $score = $scores[$key][$metric];
                $value = $metrics[$field];
                $range = $cutoffs[$metric][$score];
                $cutoffs[$metric][$score] = [
                    'min' => $range === null ? $value : min($range['min'], $value),
                    'max' => $range === null ? $value : max($range['max'], $value),
                ];
            }
        }

        return $cutoffs;
    }

    /** @return callable(array{recency_days: int, frequency_count: int, monetary_total: float}): array{r: int, f: int, m: int} */
    private function scorer(Collection $population): callable
    {
        $size = max(1, $population->count());
        $recency = $population->pluck('recency_days')->sort()->values()->all();
        $frequency = $population->pluck('frequency_count')->sort()->values()->all();
        $monetary = $population->pluck('monetary_total')->sort()->values()->all();

        return fn (array $metrics): array => [
            // Recency is better when fewer days have passed, so "worse" customers waited longer.
            'r' => $this->quintile($size - $this->countAtMost($recency, $metrics['recency_days']), $size),
            'f' => $this->quintile($this->countBelow($frequency, $metrics['frequency_count']), $size),
            'm' => $this->quintile($this->countBelow($monetary, $metrics['monetary_total']), $size),
        ];
    }

    private function quintile(int $worse, int $size): int
    {
        if ($size <= 1) {
            return 3;
        }

        return max(1, min(5, 1 + (int) floor(5 * $worse / ($size - 1))));
    }

    /** @param list<int|float> $sorted */
    private function countBelow(array $sorted, int|float $value): int
    {
        $low = 0;
        $high = count($sorted);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $sorted[$middle] < $value ? $low = $middle + 1 : $high = $middle;
        }

        return $low;
    }

    /** @param list<int|float> $sorted */
    private function countAtMost(array $sorted, int|float $value): int
    {
        $low = 0;
        $high = count($sorted);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $sorted[$middle] <= $value ? $low = $middle + 1 : $high = $middle;
        }

        return $low;
    }
}
