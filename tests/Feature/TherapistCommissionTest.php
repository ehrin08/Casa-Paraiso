<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\TherapistCommission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TherapistCommissionSynchronizer;
use App\Services\TransactionWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TherapistCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_completed_transaction_creates_one_idempotent_pending_earning(): void
    {
        [$transaction, $staff] = $this->paidCompletedTransaction(1000);
        $synchronizer = app(TherapistCommissionSynchronizer::class);

        $earning = $synchronizer->synchronize($transaction);
        $synchronizer->synchronize($transaction);

        $this->assertDatabaseCount('therapist_commissions', 1);
        $this->assertSame($staff->id, $earning->staff_profile_id);
        $this->assertSame(TherapistCommission::TYPE_EARNING, $earning->commission_type);
        $this->assertSame(TherapistCommission::STATUS_PENDING, $earning->status);
        $this->assertSame('1000.00', $earning->basis_amount);
        $this->assertSame('0.2200', $earning->commission_rate);
        $this->assertSame('220.00', $earning->commission_amount);
        $this->assertTrue($earning->earned_at->equalTo($transaction->paid_at));
    }

    public function test_pending_earning_recalculates_and_loses_eligibility_at_zero(): void
    {
        [$transaction] = $this->paidCompletedTransaction(1000);
        $synchronizer = app(TherapistCommissionSynchronizer::class);
        $earning = $synchronizer->synchronize($transaction);

        $transaction->update(['amount' => 1200]);
        $synchronizer->synchronize($transaction);
        $this->assertSame('264.00', $earning->fresh()->commission_amount);

        $transaction->update(['payment_status' => Transaction::PAYMENT_REFUNDED]);
        $synchronizer->synchronize($transaction);
        $this->assertSame('0.00', $earning->fresh()->commission_amount);
    }

    public function test_paid_earning_is_immutable_and_source_corrections_create_signed_adjustments(): void
    {
        [$transaction] = $this->paidCompletedTransaction(1000);
        $synchronizer = app(TherapistCommissionSynchronizer::class);
        $earning = $synchronizer->synchronize($transaction);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.commissions.pay', $earning, false), [
            'paid_at' => today()->toDateString(),
            'notes' => 'Settled externally.',
        ])->assertRedirect(route('admin.commissions.show', $earning, false));

        $transaction->update(['amount' => 800]);
        $adjustment = $synchronizer->synchronize($transaction);
        $synchronizer->synchronize($transaction);

        $this->assertSame(TherapistCommission::STATUS_PAID, $earning->fresh()->status);
        $this->assertSame('220.00', $earning->fresh()->commission_amount);
        $this->assertSame(TherapistCommission::TYPE_ADJUSTMENT, $adjustment->commission_type);
        $this->assertSame('-44.00', $adjustment->commission_amount);
        $this->assertDatabaseCount('therapist_commissions', 2);

        $transaction->update(['amount' => 1200]);
        $this->assertSame('44.00', $synchronizer->synchronize($transaction)->commission_amount);
        $this->assertDatabaseCount('therapist_commissions', 2);
    }

    public function test_therapists_can_view_only_their_own_commissions(): void
    {
        [$transaction, $staff] = $this->paidCompletedTransaction(1000);
        $commission = app(TherapistCommissionSynchronizer::class)->synchronize($transaction);
        $otherTherapist = User::factory()->staff()->create();
        StaffProfile::factory()->for($otherTherapist)->create();

        $this->actingAs($staff->user)->get(route('staff.commissions.show', $commission, false))->assertOk();
        $this->actingAs($otherTherapist)->get(route('staff.commissions.show', $commission, false))->assertForbidden();
        $this->actingAs(User::factory()->receptionist()->create())->get(route('staff.commissions.show', $commission, false))->assertForbidden();
    }

    public function test_therapist_dashboard_and_history_reflect_only_own_commissions(): void
    {
        [$transaction, $staff] = $this->paidCompletedTransaction(1000);
        app(TherapistCommissionSynchronizer::class)->synchronize($transaction);
        TherapistCommission::factory()->for($staff)->create([
            'status' => TherapistCommission::STATUS_PAID,
            'commission_amount' => 50,
            'paid_at' => now(),
        ]);

        [$otherTransaction] = $this->paidCompletedTransaction(2000);
        app(TherapistCommissionSynchronizer::class)->synchronize($otherTransaction);

        $this->actingAs($staff->user)
            ->get(route('staff.dashboard', absolute: false))
            ->assertOk()
            ->assertSeeInOrder(['Pending commission', 'PHP 220.00'])
            ->assertSeeInOrder(['Paid commission', 'PHP 50.00'])
            ->assertSeeInOrder(['Net commission', 'PHP 270.00'])
            ->assertSee('View commission history')
            ->assertDontSee('My commissions');

        $this->actingAs($staff->user)
            ->get(route('staff.commissions.index', absolute: false))
            ->assertOk()
            ->assertSee($transaction->transaction_number)
            ->assertDontSee($otherTransaction->transaction_number);
    }

    public function test_primary_transaction_reference_is_unique_while_adjustments_can_repeat(): void
    {
        [$transaction, $staff] = $this->paidCompletedTransaction(1000);
        $earning = app(TherapistCommissionSynchronizer::class)->synchronize($transaction);

        TherapistCommission::query()->create([
            'staff_profile_id' => $staff->id,
            'appointment_id' => $transaction->appointment_id,
            'transaction_id' => $transaction->id,
            'adjusts_commission_id' => $earning->id,
            'commission_type' => TherapistCommission::TYPE_ADJUSTMENT,
            'status' => TherapistCommission::STATUS_PENDING,
            'basis_amount' => 1000,
            'commission_rate' => 0.22,
            'commission_amount' => 1,
            'earned_at' => now(),
        ]);
        TherapistCommission::query()->create([
            'staff_profile_id' => $staff->id,
            'appointment_id' => $transaction->appointment_id,
            'transaction_id' => $transaction->id,
            'adjusts_commission_id' => $earning->id,
            'commission_type' => TherapistCommission::TYPE_ADJUSTMENT,
            'status' => TherapistCommission::STATUS_PAID,
            'basis_amount' => 1000,
            'commission_rate' => 0.22,
            'commission_amount' => -1,
            'earned_at' => now(),
            'paid_at' => now(),
        ]);
        $this->assertDatabaseCount('therapist_commissions', 3);

        $this->expectException(QueryException::class);
        TherapistCommission::query()->create([
            'staff_profile_id' => $staff->id,
            'appointment_id' => $transaction->appointment_id,
            'transaction_id' => $transaction->id,
            'primary_transaction_id' => $transaction->id,
            'commission_type' => TherapistCommission::TYPE_EARNING,
            'status' => TherapistCommission::STATUS_PENDING,
            'basis_amount' => 1000,
            'commission_rate' => 0.22,
            'commission_amount' => 220,
            'earned_at' => now(),
        ]);
    }

    public function test_soft_deleted_therapist_history_and_decimal_date_casts_are_preserved(): void
    {
        [$transaction, $staff] = $this->paidCompletedTransaction(1000);
        $earning = app(TherapistCommissionSynchronizer::class)->synchronize($transaction);
        $staff->delete();
        $earning = $earning->fresh();

        $this->assertTrue($earning->staffProfile->trashed());
        $this->assertSame('1000.00', $earning->basis_amount);
        $this->assertSame('0.2200', $earning->commission_rate);
        $this->assertSame('220.00', $earning->commission_amount);
        $this->assertNotNull($earning->earned_at);
    }

    public function test_ineligible_payment_states_and_unlinked_manual_transactions_do_not_create_earnings(): void
    {
        [$transaction] = $this->paidCompletedTransaction(1000);
        $synchronizer = app(TherapistCommissionSynchronizer::class);

        foreach ([Transaction::PAYMENT_UNPAID, Transaction::PAYMENT_PARTIAL, Transaction::PAYMENT_REFUNDED, Transaction::PAYMENT_VOID] as $status) {
            $transaction->update(['payment_status' => $status]);
            $this->assertNull($synchronizer->synchronize($transaction));
        }

        $manual = Transaction::factory()->create([
            'appointment_id' => null,
            'payment_status' => Transaction::PAYMENT_PAID,
            'paid_at' => now(),
        ]);
        $this->assertNull($synchronizer->synchronize($manual));
        $this->assertDatabaseCount('therapist_commissions', 0);
    }

    public function test_later_full_payment_through_transaction_workflow_creates_the_earning(): void
    {
        [$transaction] = $this->paidCompletedTransaction(1000);
        $transaction->update([
            'payment_status' => Transaction::PAYMENT_UNPAID,
            'payment_method' => null,
            'paid_at' => null,
        ]);
        $admin = User::factory()->admin()->create();

        app(TransactionWorkflow::class)->persist($transaction, [
            'customer_profile_id' => $transaction->customer_profile_id,
            'appointment_id' => $transaction->appointment_id,
            'service_id' => $transaction->service_id,
            'amount' => 1000,
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => Transaction::METHOD_GCASH,
            'paid_at' => now(),
            'notes' => null,
        ], $admin->id);

        $earning = $transaction->therapistCommissions()->firstOrFail();
        $this->assertSame(TherapistCommission::TYPE_EARNING, $earning->commission_type);
        $this->assertSame('220.00', $earning->commission_amount);
    }

    public function test_pending_reconciliation_adjustment_is_removed_when_paid_total_becomes_correct_again(): void
    {
        [$transaction] = $this->paidCompletedTransaction(1000);
        $synchronizer = app(TherapistCommissionSynchronizer::class);
        $earning = $synchronizer->synchronize($transaction);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.commissions.pay', $earning, false), [
            'paid_at' => today()->toDateString(),
        ])->assertRedirect();

        $transaction->update(['amount' => 800]);
        $synchronizer->synchronize($transaction);
        $this->assertDatabaseCount('therapist_commissions', 2);

        $transaction->update(['amount' => 1000]);
        $this->assertSame($earning->id, $synchronizer->synchronize($transaction)->id);
        $this->assertDatabaseCount('therapist_commissions', 1);
    }

    public function test_payout_rejects_future_dates_and_paid_records_cannot_be_paid_again(): void
    {
        [$transaction] = $this->paidCompletedTransaction(1000);
        $earning = app(TherapistCommissionSynchronizer::class)->synchronize($transaction);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.commissions.pay', $earning, false), [
            'paid_at' => today()->addDay()->toDateString(),
        ])->assertSessionHasErrors('paid_at');

        $this->actingAs($admin)->patch(route('admin.commissions.pay', $earning, false), [
            'paid_at' => today()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.commissions.pay', $earning, false), [
            'paid_at' => today()->toDateString(),
        ])->assertSessionHasErrors('status');
    }

    private function paidCompletedTransaction(float $amount): array
    {
        $staff = StaffProfile::factory()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();
        $appointment = Appointment::factory()->for($customer)->for($service)->for($staff, 'staffProfile')->create([
            'status' => Appointment::STATUS_COMPLETED,
            'scheduled_start_at' => now()->subHours(2),
            'scheduled_end_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
        ]);
        $transaction = Transaction::factory()->for($customer)->for($service)->for($appointment)->create([
            'amount' => $amount,
            'payment_status' => Transaction::PAYMENT_PAID,
            'paid_at' => now()->subMinutes(30),
        ]);

        return [$transaction, $staff];
    }
}
