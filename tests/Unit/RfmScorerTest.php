<?php

namespace Tests\Unit;

use App\Services\RfmScorer;
use Tests\TestCase;

class RfmScorerTest extends TestCase
{
    public function test_percent_rank_splits_ten_customers_into_fifths(): void
    {
        $population = collect(range(1, 10))->mapWithKeys(fn (int $value) => [$value => [
            'recency_days' => $value,
            'frequency_count' => $value,
            'monetary_total' => $value * 1000.0,
        ]]);

        $scores = app(RfmScorer::class)->scoreAll($population);

        $this->assertSame([1, 1, 2, 2, 3, 3, 4, 4, 5, 5], $scores->pluck('f')->values()->all());
        $this->assertSame([1, 1, 2, 2, 3, 3, 4, 4, 5, 5], $scores->pluck('m')->values()->all());
        // Fewer days since the last visit is better, so recency runs the other way.
        $this->assertSame([5, 5, 4, 4, 3, 3, 2, 2, 1, 1], $scores->pluck('r')->values()->all());
    }

    public function test_tied_customers_share_the_lower_score_and_a_lone_customer_is_neutral(): void
    {
        $scorer = app(RfmScorer::class);
        $tied = collect(range(1, 6))->mapWithKeys(fn (int $id) => [$id => ['recency_days' => 120, 'frequency_count' => 1, 'monetary_total' => 500.0]]);
        $tied[7] = ['recency_days' => 2, 'frequency_count' => 4, 'monetary_total' => 4000.0];

        $scores = $scorer->scoreAll($tied);

        $this->assertSame(['r' => 1, 'f' => 1, 'm' => 1], $scores[1]);
        $this->assertSame(['r' => 5, 'f' => 5, 'm' => 5], $scores[7]);

        $lone = ['recency_days' => 3, 'frequency_count' => 2, 'monetary_total' => 800.0];
        $this->assertSame(['r' => 3, 'f' => 3, 'm' => 3], $scorer->score($lone, collect([1 => $lone])));
    }

    public function test_cutoffs_report_the_observed_range_for_each_score(): void
    {
        $population = collect(range(1, 10))->mapWithKeys(fn (int $value) => [$value => [
            'recency_days' => $value * 10,
            'frequency_count' => $value,
            'monetary_total' => $value * 100.0,
        ]]);

        $cutoffs = app(RfmScorer::class)->cutoffs($population);

        $this->assertSame(['min' => 9, 'max' => 10], $cutoffs['f'][5]);
        $this->assertSame(['min' => 1, 'max' => 2], $cutoffs['f'][1]);
        $this->assertSame(['min' => 10, 'max' => 20], $cutoffs['r'][5]);
        $this->assertSame(['min' => 900.0, 'max' => 1000.0], $cutoffs['m'][5]);
    }

    public function test_a_lone_first_time_customer_scores_as_new(): void
    {
        $first = ['recency_days' => 0, 'frequency_count' => 1, 'monetary_total' => 1500.0];

        $this->assertSame(['r' => 5, 'f' => 1, 'm' => 3], app(RfmScorer::class)->score($first, collect([1 => $first])));
    }

    public function test_first_time_customers_who_paid_on_the_same_day_all_score_as_new(): void
    {
        $population = collect(range(1, 6))->mapWithKeys(fn (int $id) => [$id => ['recency_days' => 0, 'frequency_count' => 1, 'monetary_total' => 1000.0]]);
        $population[7] = ['recency_days' => 40, 'frequency_count' => 3, 'monetary_total' => 5000.0];

        $scores = app(RfmScorer::class)->scoreAll($population);

        foreach (range(1, 6) as $id) {
            $this->assertSame(5, $scores[$id]['r']);
            $this->assertSame(1, $scores[$id]['f']);
        }
    }

    public function test_one_visit_outside_the_new_customer_window_is_ranked_normally(): void
    {
        $population = collect([
            1 => ['recency_days' => 31, 'frequency_count' => 1, 'monetary_total' => 500.0],
            2 => ['recency_days' => 2, 'frequency_count' => 3, 'monetary_total' => 3000.0],
        ]);

        $this->assertSame(['r' => 1, 'f' => 1, 'm' => 1], app(RfmScorer::class)->scoreAll($population)[1]);
    }
}
