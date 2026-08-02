<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_fixed_presets_match_in_priority_order(): void
    {
        Carbon::setTestNow('2026-07-14 12:00:00');

        foreach ([
            ['expected' => 'high_value', 'visits' => 3, 'amount' => 2000, 'days' => 5],
            ['expected' => 'loyal', 'visits' => 4, 'amount' => 500, 'days' => 5],
            ['expected' => 'at_risk', 'visits' => 2, 'amount' => 500, 'days' => 100],
            ['expected' => 'inactive', 'visits' => 1, 'amount' => 500, 'days' => 200],
            ['expected' => 'new_customer', 'visits' => 1, 'amount' => 500, 'days' => 10],
        ] as $case) {
            $customer = CustomerProfile::factory()->create();
            $transaction = $this->paidVisit($customer, $case['visits'], $case['amount'], $case['days']);

            $reward = app(RfmPromotionGenerator::class)->generateForTransaction($transaction);

            $this->assertNotNull($reward);
            $this->assertSame($case['expected'], $reward->rfmSegment?->preset_key);
        }
    }

    public function test_expired_reward_does_not_block_the_next_qualifying_reward_and_is_not_bookable(): void
    {
        Carbon::setTestNow('2026-07-14 12:00:00');
        $customer = CustomerProfile::factory()->create();
        $firstTransaction = $this->paidVisit($customer, 1, 500, 5);
        $firstReward = app(RfmPromotionGenerator::class)->generateForTransaction($firstTransaction);
        $this->assertNotNull($firstReward);

        $firstReward->update(['expires_at' => now()->subMinute()]);
        $this->assertTrue(app(RfmAddonVoucher::class)->availableFor($customer)->isEmpty());

        $latestTransaction = $this->paidVisit($customer, 3, 2000, 1);
        $secondReward = app(RfmPromotionGenerator::class)->generateForTransaction($latestTransaction);

        $this->assertNotNull($secondReward);
        $this->assertSame('high_value', $secondReward->rfmSegment?->preset_key);
        $this->assertSame('expired', $firstReward->fresh()->lifecycle());
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
