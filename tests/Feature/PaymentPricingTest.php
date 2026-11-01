<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentAddon;
use App\Models\CustomerMembership;
use App\Models\CustomerProfile;
use App\Models\MembershipPerkUsage;
use App\Models\MembershipReferralCredit;
use App\Models\MembershipTier;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PaymentPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_gold_member_gets_ten_percent_off_the_service_only(): void
    {
        $customer = $this->member('gold');
        $appointment = $this->appointment($customer, 1000, ['back-massage-30' => 299]);

        $quote = app(PaymentPricing::class)->quote($appointment, 1299);

        $this->assertSame(100.0, $quote['discount']);
        $this->assertSame(1199.0, $quote['net']);
        $this->assertSame(['service_discount'], array_column($quote['adjustments'], 'type'));
        $this->assertStringContainsString('Gold member', $quote['adjustments'][0]['label']);
    }

    public function test_platinum_member_gets_vip_room_price_listed_addon_discount_and_service_discount(): void
    {
        $customer = $this->member('platinum');
        $appointment = $this->appointment($customer, 1000, ['vip-room' => 200, 'ventosa' => 200, 'hot-stone' => 200, 'back-massage-30' => 299]);

        $quote = app(PaymentPricing::class)->quote($appointment, 1899);

        // VIP Room 200 → 100, 20% off Ventosa and Hot Stone (not the back massage), 15% off the service.
        $this->assertSame([100.0, 40.0, 40.0, 150.0], array_column($quote['adjustments'], 'amount'));
        $this->assertSame(330.0, $quote['discount']);
        $this->assertSame(1569.0, $quote['net']);
    }

    public function test_birthday_treat_is_free_once_a_year_in_the_birth_month(): void
    {
        $customer = $this->member('gold', ['birth_date' => '1990-11-23']);
        $first = $this->appointment($customer, 1000, ['back-massage-30' => 299]);

        $transaction = $this->recordPayment($first, 1299);

        $this->assertSame('900.00', $transaction->amount);
        $this->assertSame(['birthday_treat', 'service_discount'], $transaction->adjustments->pluck('type')->all());
        $this->assertSame(1, MembershipPerkUsage::query()->count());

        $second = $this->appointment($customer, 1000, ['back-massage-30' => 299]);
        $this->assertSame(['service_discount'], array_column(app(PaymentPricing::class)->quote($second, 1299)['adjustments'], 'type'));

        $outsideMonth = $this->member('gold', ['birth_date' => '1990-05-23']);
        $this->assertSame(['service_discount'], array_column(app(PaymentPricing::class)->quote($this->appointment($outsideMonth, 1000, ['back-massage-30' => 299]), 1299)['adjustments'], 'type'));
    }

    public function test_referral_credit_is_used_once_and_capped_at_the_remaining_amount(): void
    {
        $customer = CustomerProfile::factory()->create();
        $membership = $this->member('gold')->memberships()->first();
        $credit = MembershipReferralCredit::query()->create(['customer_profile_id' => $customer->id, 'customer_membership_id' => $membership->id, 'amount' => 100]);
        $appointment = $this->appointment($customer, 60);

        $transaction = $this->recordPayment($appointment, 60);

        $this->assertSame('0.00', $transaction->amount);
        $this->assertSame('60.00', $transaction->discount_amount);
        $this->assertTrue($credit->fresh()->used_transaction_id === $transaction->id);
        $this->assertSame([], app(PaymentPricing::class)->quote($this->appointment($customer, 500), 500)['adjustments']);
    }

    public function test_expired_or_cancelled_cards_give_no_benefits(): void
    {
        $expired = $this->member('platinum', [], ['valid_until' => '2026-11-09']);
        $cancelled = $this->member('platinum', [], ['status' => CustomerMembership::STATUS_CANCELLED]);

        foreach ([$expired, $cancelled] as $customer) {
            $quote = app(PaymentPricing::class)->quote($this->appointment($customer, 1000, ['vip-room' => 200]), 1200);
            $this->assertNull($quote['membership']);
            $this->assertSame(1200.0, $quote['net']);
        }
    }

    public function test_admin_payment_stores_breakdown_net_amount_and_later_edits_keep_benefits(): void
    {
        $admin = User::factory()->admin()->create();
        $appointment = $this->appointment($this->member('gold'), 1000);
        $payload = [
            'customer_profile_id' => $appointment->customer_profile_id,
            'appointment_id' => $appointment->id,
            'service_id' => $appointment->service_id,
            'amount' => 1000,
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => now()->format('Y-m-d H:i:s'),
        ];

        $this->actingAs($admin)->post(route('admin.transactions.store'), $payload)->assertRedirect();
        $transaction = Transaction::query()->latest('id')->firstOrFail();
        $this->assertSame(['900.00', '1000.00', '100.00'], [$transaction->amount, $transaction->gross_amount, $transaction->discount_amount]);
        $this->assertNotNull($transaction->customer_membership_id);

        $this->get(route('admin.transactions.show', $transaction))->assertOk()->assertSee('data-transaction-breakdown', false)->assertSee('Gold member');

        $this->patch(route('admin.transactions.update', $transaction), [...$payload, 'amount' => 1100])->assertRedirect();
        $transaction->refresh();
        $this->assertSame(['1000.00', '1100.00'], [$transaction->amount, $transaction->gross_amount]);
        $this->assertSame(1, $transaction->adjustments()->count());
    }

    public function test_quote_endpoint_previews_benefits_for_admins_and_receptionists_only(): void
    {
        $appointment = $this->appointment($this->member('gold'), 1000);
        $query = ['appointment_id' => $appointment->id, 'amount' => 1000];

        $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.transactions.quote', $query))
            ->assertOk()->assertJsonPath('net', 900)->assertJsonPath('adjustments.0.amount', 100);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_RECEPTIONIST]))->getJson(route('reception.transactions.quote', $query))
            ->assertOk()->assertJsonPath('discount', 100);
        $this->actingAs(User::factory()->customer()->create())->getJson(route('admin.transactions.quote', $query))->assertForbidden();
    }

    private function member(string $tier, array $customer = [], array $membership = []): CustomerProfile
    {
        $profile = CustomerProfile::factory()->create($customer);
        CustomerMembership::query()->create([
            'customer_profile_id' => $profile->id,
            'membership_tier_id' => MembershipTier::query()->where('code', $tier)->value('id'),
            'card_number' => CustomerMembership::nextCardNumber(),
            'issued_at' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'status' => CustomerMembership::STATUS_ACTIVE,
            ...$membership,
        ]);

        return $profile;
    }

    /** @param array<string, float|int> $addons */
    private function appointment(CustomerProfile $customer, float $servicePrice, array $addons = []): Appointment
    {
        $appointment = Appointment::factory()->for($customer)->for(Service::factory()->create(['price' => $servicePrice]))->create([
            'scheduled_start_at' => now()->subHour(),
            'scheduled_end_at' => now(),
        ]);

        foreach ($addons as $code => $price) {
            AppointmentAddon::query()->create(['appointment_id' => $appointment->id, 'addon_code' => $code, 'addon_name' => $code, 'price' => $price, 'duration_minutes' => 0]);
        }

        return $appointment;
    }

    private function recordPayment(Appointment $appointment, float $amount): Transaction
    {
        $transaction = Transaction::factory()->for($appointment->customerProfile)->for($appointment)->create([
            'service_id' => $appointment->service_id,
            'amount' => $amount,
            'payment_status' => Transaction::PAYMENT_PAID,
            'paid_at' => now(),
        ]);

        return app(PaymentPricing::class)->settle($transaction, $amount, true)->load('adjustments');
    }
}
