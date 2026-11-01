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
        $tied = collect(range(1, 6))->mapWithKeys(fn (int $id) => [$id => ['recency_days' => 30, 'frequency_count' => 1, 'monetary_total' => 500.0]]);
        $tied[7] = ['recency_days' => 2, 'frequency_count' => 4, 'monetary_total' => 4000.0];

        $scores = $scorer->scoreAll($tied);

        $this->assertSame(['r' => 1, 'f' => 1, 'm' => 1], $scores[1]);
        $this->assertSame(['r' => 5, 'f' => 5, 'm' => 5], $scores[7]);

        $lone = ['recency_days' => 3, 'frequency_count' => 1, 'monetary_total' => 800.0];
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
}
