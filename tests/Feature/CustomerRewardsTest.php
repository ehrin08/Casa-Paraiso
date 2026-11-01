<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\RfmSegment;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\RfmAddonVoucher;
use App\Services\RfmPromotionGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerRewardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-14 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_top_scoring_customer_matches_the_high_value_rule_and_stores_scores(): void
    {
        $this->background(visits: 1, amount: 500, days: 100);
        $customer = CustomerProfile::factory()->create();

        $reward = app(RfmPromotionGenerator::class)->generateForTransaction($this->paidVisit($customer, 4, 2000, 1));

        $this->assertSame('high_value', $reward?->rfmSegment?->preset_key);
        $this->assertSame([5, 5, 5], [$reward->r_score, $reward->f_score, $reward->m_score]);
        $this->assertSame(4, $reward->frequency_count);
    }

    public function test_recent_single_visit_customer_matches_the_new_customer_rule(): void
    {
        $this->background(visits: 3, amount: 1500, days: 100);
        $customer = CustomerProfile::factory()->create();

        $reward = app(RfmPromotionGenerator::class)->generateForTransaction($this->paidVisit($customer, 1, 500, 1));

        $this->assertSame('new_customer', $reward?->rfmSegment?->preset_key);
        $this->assertSame([5, 1, 1], [$reward->r_score, $reward->f_score, $reward->m_score]);
    }

    public function test_frequent_customer_who_stopped_visiting_matches_the_at_risk_rule(): void
    {
        $this->background(visits: 1, amount: 500, days: 2);
        $customer = CustomerProfile::factory()->create();

        $reward = app(RfmPromotionGenerator::class)->generateForTransaction($this->paidVisit($customer, 3, 500, 200));

        $this->assertSame('at_risk', $reward?->rfmSegment?->preset_key);
        $this->assertSame(1, $reward->r_score);
        $this->assertSame(5, $reward->f_score);
    }

    public function test_admin_rule_priority_decides_between_overlapping_rules(): void
    {
        $this->background(visits: 1, amount: 500, days: 100);
        $vip = RfmSegment::factory()->create([
            'name' => 'Big spenders', 'addon_code' => 'hot-stone', 'priority' => 5,
            'm_score_min' => 5, 'm_score_max' => null,
        ]);
        $customer = CustomerProfile::factory()->create();

        $reward = app(RfmPromotionGenerator::class)->generateForTransaction($this->paidVisit($customer, 4, 2000, 1));
        $this->assertTrue($reward?->rfmSegment?->is($vip));

        $vip->update(['is_active' => false]);
        $other = CustomerProfile::factory()->create();
        $reward = app(RfmPromotionGenerator::class)->generateForTransaction($this->paidVisit($other, 5, 2500, 1));
        $this->assertSame('high_value', $reward?->rfmSegment?->preset_key);
    }

    public function test_no_matching_rule_issues_no_reward(): void
    {
        $customer = CustomerProfile::factory()->create();

        // A lone customer scores the neutral 3/3/3, which no default rule covers.
        $this->assertNull(app(RfmPromotionGenerator::class)->generateForTransaction($this->paidVisit($customer, 1, 500, 1)));
    }

    public function test_expired_reward_does_not_block_the_next_qualifying_reward_and_is_not_bookable(): void
    {
        $this->background(visits: 1, amount: 500, days: 50);
        $customer = CustomerProfile::factory()->create();
        $firstReward = app(RfmPromotionGenerator::class)->generateForTransaction($this->paidVisit($customer, 1, 500, 1));
        $this->assertSame('new_customer', $firstReward?->rfmSegment?->preset_key);

        $firstReward->update(['expires_at' => now()->subMinute()]);
        $this->assertTrue(app(RfmAddonVoucher::class)->availableFor($customer)->isEmpty());

        $secondReward = app(RfmPromotionGenerator::class)->generateForTransaction($this->paidVisit($customer, 3, 2000, 1));

        $this->assertSame('high_value', $secondReward?->rfmSegment?->preset_key);
        $this->assertSame('expired', $firstReward->fresh()->lifecycle());
    }

    private function background(int $visits, int $amount, int $days, int $customers = 4): void
    {
        for ($index = 0; $index < $customers; $index++) {
            $this->paidVisit(CustomerProfile::factory()->create(), $visits, $amount, $days);
        }
    }

    private function paidVisit(CustomerProfile $customer, int $count, int $amount, int $days): Transaction
    {
        $service = Service::factory()->create();

        for ($visit = 1; $visit <= $count; $visit++) {
            $appointment = Appointment::factory()
                ->for($customer)
                ->for($service)
                ->create([
                    'status' => Appointment::STATUS_COMPLETED,
                    'completed_at' => now()->subDays($days),
                ]);

            $transaction = Transaction::factory()
                ->for($customer)
                ->for($appointment)
                ->for($service)
                ->create([
                    'amount' => $amount,
                    'payment_status' => Transaction::PAYMENT_PAID,
                    'paid_at' => now()->subDays($days),
                ]);
        }

        return $transaction;
    }
}
