<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerMembership;
use App\Models\CustomerProfile;
use App\Models\MembershipReferralCredit;
use App\Models\MembershipTier;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MembershipRecommendations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_issues_a_card_with_a_referral_and_both_customers_get_credit(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $referrer = CustomerProfile::factory()->create();
        $gold = MembershipTier::query()->where('code', 'gold')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.memberships.create', ['customer' => $customer->id]))->assertOk()->assertSee('CPBWS-0001');

        $this->post(route('admin.memberships.store'), [
            'customer_profile_id' => $customer->id,
            'membership_tier_id' => $gold->id,
            'card_number' => 'CPBWS-0001',
            'issued_at' => '2026-11-01',
            'referred_by_customer_profile_id' => $referrer->id,
        ])->assertRedirect()->assertSessionHas('status', 'membership-issued');

        $membership = CustomerMembership::query()->firstOrFail();
        $this->assertSame('2027-10-31', $membership->valid_until->toDateString());
        $this->assertEqualsCanonicalizing([$customer->id, $referrer->id], MembershipReferralCredit::query()->pluck('customer_profile_id')->all());
        $this->assertSame('CPBWS-0002', CustomerMembership::nextCardNumber());

        $this->from(route('admin.memberships.create'))->post(route('admin.memberships.store'), [
            'customer_profile_id' => $customer->id,
            'membership_tier_id' => $gold->id,
            'card_number' => 'CPBWS-0002',
            'issued_at' => '2026-11-02',
        ])->assertSessionHasErrors('customer_profile_id');

        $this->get(route('admin.memberships.show', $membership))->assertOk()->assertSee('10% off all services')->assertSee('Cancel card');
        $this->get(route('admin.memberships.index'))->assertOk()->assertSee('CPBWS-0001')->assertSee('Recommended for membership');
    }

    public function test_receptionist_can_issue_and_renew_but_not_cancel(): void
    {
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);
        $customer = CustomerProfile::factory()->create();

        $this->actingAs($receptionist)->post(route('reception.memberships.store'), [
            'customer_profile_id' => $customer->id,
            'membership_tier_id' => MembershipTier::query()->where('code', 'platinum')->value('id'),
            'card_number' => 'CPBWS-0100',
            'issued_at' => now()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
        ])->assertRedirect();
        $membership = CustomerMembership::query()->firstOrFail();
        $before = $membership->valid_until->copy();

        $this->get(route('reception.memberships.show', $membership))->assertOk()->assertDontSee('Cancel card');
        $this->patch(route('reception.memberships.renew', $membership))->assertRedirect();
        $this->assertTrue($membership->fresh()->valid_until->equalTo($before->addMonths(12)));

        $this->patch(route('admin.memberships.cancel', $membership))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.memberships.cancel', $membership))->assertRedirect();
        $this->assertSame('cancelled', $membership->fresh()->lifecycle());
        $this->actingAs(User::factory()->customer()->create())->get(route('reception.memberships.index'))->assertForbidden();
    }

    public function test_admin_edits_tier_benefits(): void
    {
        $admin = User::factory()->admin()->create();
        $gold = MembershipTier::query()->where('code', 'gold')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.membership-tiers.index'))->assertOk()->assertSee('Platinum')->assertSee('VIP Room for PHP 100.00');
        $this->patch(route('admin.membership-tiers.update', $gold), [
            'name' => 'Gold', 'rank' => 10, 'stars' => 3, 'validity_months' => 6,
            'service_discount_percent' => 12.5, 'addon_discount_percent' => 5,
            'addon_discount_codes' => ['ventosa'], 'referral_reward_amount' => 150, 'is_active' => 1,
        ])->assertRedirect(route('admin.membership-tiers.index'));

        $gold->refresh();
        $this->assertSame(['12.50', '5.00', ['ventosa'], 6], [$gold->service_discount_percent, $gold->addon_discount_percent, $gold->addon_discount_codes, $gold->validity_months]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_RECEPTIONIST]))->get(route('admin.membership-tiers.index'))->assertForbidden();
    }

    public function test_rfm_recommends_platinum_for_high_value_customers_without_a_card(): void
    {
        $service = Service::factory()->create();
        $customers = CustomerProfile::factory()->count(5)->create();

        foreach ($customers as $index => $customer) {
            foreach (range(0, $index) as $visit) {
                $appointment = Appointment::factory()->for($customer)->for($service)->create(['status' => Appointment::STATUS_COMPLETED]);
                Transaction::factory()->for($customer)->for($appointment)->for($service)->create([
                    'amount' => 500 * ($index + 1), 'payment_status' => Transaction::PAYMENT_PAID, 'paid_at' => now()->subDays(50 - $index * 10),
                ]);
            }
        }

        $top = $customers->last();
        $rows = app(MembershipRecommendations::class)->list();

        $this->assertTrue($rows->first()['customer']->is($top));
        $this->assertSame('platinum', $rows->first()['tier']->code);

        CustomerMembership::query()->create([
            'customer_profile_id' => $top->id, 'membership_tier_id' => $rows->first()['tier']->id, 'card_number' => 'CPBWS-0009',
            'issued_at' => now()->subDay()->toDateString(), 'valid_until' => now()->addYear()->toDateString(), 'status' => 'active',
        ]);
        $this->assertFalse(app(MembershipRecommendations::class)->list()->contains(fn ($row) => $row['customer']->is($top)));
    }
}
