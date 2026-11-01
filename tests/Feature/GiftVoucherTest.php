<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\CustomerMembership;
use App\Models\CustomerProfile;
use App\Models\GiftVoucher;
use App\Models\MembershipTier;
use App\Models\PromotionSuggestion;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GiftVoucherTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 15:00:00');
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_staff_record_a_sold_voucher_with_the_printed_code_or_a_generated_one(): void
    {
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $this->actingAs($receptionist)->get(route('reception.gift-vouchers.create'))->assertOk()->assertSee('₱300')->assertSee('₱1,000');
        $this->post(route('reception.gift-vouchers.store'), ['value' => 500, 'code' => ' cp-500-0001 ', 'expires_at' => '2027-03-31', 'purchaser_name' => 'Walk-in buyer', 'sale_payment_method' => Transaction::METHOD_CASH])
            ->assertRedirect();
        $this->post(route('reception.gift-vouchers.store'), ['value' => 300, 'sale_payment_method' => Transaction::METHOD_CASH])->assertRedirect();
        $this->from(route('reception.gift-vouchers.create'))
            ->post(route('reception.gift-vouchers.store'), ['value' => 500, 'code' => 'CP-500-0001', 'sale_payment_method' => Transaction::METHOD_CASH])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('gift_vouchers', ['code' => 'CP-500-0001', 'value' => 500, 'status' => 'active', 'sold_by' => $receptionist->id]);
        $this->assertMatchesRegularExpression('/^CPGV-[A-Z0-9]{6}$/', GiftVoucher::query()->where('value', 300)->value('code'));
        $this->get(route('reception.gift-vouchers.index'))->assertOk()->assertSee('CP-500-0001')->assertDontSee('Voucher rules');
    }

    public function test_voucher_pays_part_of_a_larger_bill_and_the_rest_is_due(): void
    {
        $voucher = $this->voucher(500);
        $appointment = $this->appointment(CustomerProfile::factory()->create(), 800);

        $this->pay($appointment, 800, 'cp-500')->assertRedirect();

        $transaction = Transaction::query()->latest('id')->firstOrFail();
        $this->assertSame(['800.00', '500.00', '300.00'], [$transaction->amount, $transaction->gift_voucher_applied, $transaction->amount_due]);
        $voucher->refresh();
        $this->assertSame(['redeemed', '500.00', '0.00'], [$voucher->status, $voucher->applied_amount, $voucher->forfeited_amount]);
        $this->assertSame($transaction->id, $voucher->redeemed_transaction_id);
        $this->get(route('admin.transactions.show', $transaction))->assertOk()->assertSee('Gift voucher CP-500')->assertSee('PHP 300.00');
    }

    public function test_unused_balance_is_forfeited_and_a_voucher_cannot_be_used_twice(): void
    {
        $voucher = $this->voucher(500);

        $this->pay($this->appointment(CustomerProfile::factory()->create(), 300), 300, 'CP-500')->assertRedirect();
        $this->assertSame(['300.00', '200.00'], [$voucher->fresh()->applied_amount, $voucher->fresh()->forfeited_amount]);
        $this->assertSame('0.00', Transaction::query()->latest('id')->value('amount_due'));

        $before = Transaction::query()->count();
        $this->pay($this->appointment(CustomerProfile::factory()->create(), 300), 300, 'CP-500')->assertSessionHasErrors('gift_voucher_code');
        $this->assertSame($before, Transaction::query()->count());
    }

    public function test_expired_and_void_vouchers_are_refused(): void
    {
        $this->voucher(300, 'EXPIRED-1', ['expires_at' => '2026-11-09']);
        $this->voucher(300, 'VOID-1', ['status' => GiftVoucher::STATUS_VOID]);

        foreach (['EXPIRED-1', 'VOID-1', 'MISSING'] as $code) {
            $this->pay($this->appointment(CustomerProfile::factory()->create(), 300), 300, $code)->assertSessionHasErrors('gift_voucher_code');
        }

        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_vouchers_do_not_combine_with_member_discounts_or_rewards_unless_allowed(): void
    {
        $member = CustomerProfile::factory()->create();
        CustomerMembership::query()->create([
            'customer_profile_id' => $member->id, 'membership_tier_id' => MembershipTier::query()->where('code', 'gold')->value('id'),
            'card_number' => 'CPBWS-0001', 'issued_at' => '2026-01-01', 'valid_until' => '2026-12-31', 'status' => 'active',
        ]);

        $this->voucher(300, 'NOCOMBO');
        $this->pay($this->appointment($member, 1000), 1000, 'NOCOMBO')->assertRedirect();
        $plain = Transaction::query()->latest('id')->firstOrFail();
        $this->assertSame(['1000.00', '0.00', '700.00'], [$plain->amount, $plain->discount_amount, $plain->amount_due]);

        $rewarded = $this->appointment($member, 1000);
        $rewarded->update(['promotion_suggestion_id' => PromotionSuggestion::factory()->create(['customer_profile_id' => $member->id])->id]);
        $this->voucher(300, 'REWARDED');
        $this->pay($rewarded, 1000, 'REWARDED')->assertSessionHasErrors('gift_voucher_code');

        ApplicationSetting::updateCurrent([...ApplicationSetting::defaults(), 'gift_voucher_combinable' => true]);
        $this->voucher(300, 'COMBO');
        $this->pay($this->appointment($member, 1000), 1000, 'COMBO')->assertRedirect();
        $combined = Transaction::query()->latest('id')->firstOrFail();
        $this->assertSame(['900.00', '100.00', '600.00'], [$combined->amount, $combined->discount_amount, $combined->amount_due]);
    }

    public function test_only_admins_void_vouchers_and_change_voucher_rules(): void
    {
        $voucher = $this->voucher(1000);
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $this->actingAs($receptionist)->patch(route('admin.gift-vouchers.void', $voucher), ['void_reason' => 'Lost'])->assertForbidden();
        $this->actingAs($receptionist)->patch(route('admin.gift-vouchers.settings.update'), ['gift_voucher_denominations' => '100'])->assertForbidden();

        $this->actingAs($this->admin)->patch(route('admin.gift-vouchers.void', $voucher), ['void_reason' => 'Reported lost'])->assertSessionHas('status', 'gift-voucher-voided');
        $this->assertSame('void', $voucher->fresh()->lifecycle());

        $this->patch(route('admin.gift-vouchers.settings.update'), ['gift_voucher_denominations' => '1000, 300,500,300', 'gift_voucher_default_expiry_months' => 4, 'gift_voucher_combinable' => 1])
            ->assertSessionHas('status', 'gift-voucher-settings-updated');
        $settings = ApplicationSetting::current();
        $this->assertSame([300, 500, 1000], $settings->giftVoucherDenominations());
        $this->assertSame(4, $settings->gift_voucher_default_expiry_months);
        $this->assertTrue($settings->gift_voucher_combinable);
        $this->actingAs(User::factory()->customer()->create())->get(route('reception.gift-vouchers.index'))->assertForbidden();
    }

    public function test_quote_preview_reports_voucher_split_and_errors(): void
    {
        $this->voucher(500, 'PREVIEW');
        $appointment = $this->appointment(CustomerProfile::factory()->create(), 300);

        $this->actingAs($this->admin)->getJson(route('admin.transactions.quote', ['appointment_id' => $appointment->id, 'amount' => 300, 'gift_voucher_code' => 'preview']))
            ->assertOk()->assertJsonPath('voucher.applied', 300)->assertJsonPath('voucher.forfeited', 200)->assertJsonPath('amount_due', 0);
        $this->getJson(route('admin.transactions.quote', ['appointment_id' => $appointment->id, 'amount' => 300, 'gift_voucher_code' => 'nope']))
            ->assertOk()->assertJsonPath('voucher_error', 'No gift voucher has this code.');
    }

    private function voucher(float $value, string $code = 'CP-500', array $attributes = []): GiftVoucher
    {
        return GiftVoucher::query()->create([
            'code' => $code, 'value' => $value, 'status' => GiftVoucher::STATUS_ACTIVE, 'issued_at' => now()->subWeek(),
            'expires_at' => '2027-03-31', 'sale_payment_method' => Transaction::METHOD_CASH, ...$attributes,
        ]);
    }

    private function appointment(CustomerProfile $customer, float $price): Appointment
    {
        return Appointment::factory()->for($customer)->for(Service::factory()->create(['price' => $price]))->create([
            'scheduled_start_at' => now()->subHour(), 'scheduled_end_at' => now(),
        ]);
    }

    private function pay(Appointment $appointment, float $amount, string $code)
    {
        return $this->actingAs($this->admin)->from(route('admin.transactions.create'))->post(route('admin.transactions.store'), [
            'customer_profile_id' => $appointment->customer_profile_id,
            'appointment_id' => $appointment->id,
            'service_id' => $appointment->service_id,
            'amount' => $amount,
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'gift_voucher_code' => $code,
        ]);
    }
}
