<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_payment_states_require_metadata_and_clear_it_when_no_payment_is_held(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.transactions.create', absolute: false))
            ->post(route('admin.transactions.store', absolute: false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'amount' => 1200,
                'payment_status' => Transaction::PAYMENT_PAID,
            ])
            ->assertRedirect(route('admin.transactions.create', absolute: false))
            ->assertSessionHasErrors(['payment_method', 'paid_at']);

        $this->assertDatabaseCount('transactions', 0);

        $transaction = Transaction::factory()
            ->for($customer)
            ->for($service)
            ->for($admin, 'recorder')
            ->create([
                'appointment_id' => null,
                'payment_status' => Transaction::PAYMENT_PAID,
                'payment_method' => Transaction::METHOD_GCASH,
                'paid_at' => now()->subDay(),
            ]);

        $this->actingAs($admin)
            ->patch(route('admin.transactions.update', $transaction, false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'amount' => 1200,
                'payment_status' => Transaction::PAYMENT_UNPAID,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('admin.transactions.show', $transaction, false));

        $transaction->refresh();

        $this->assertSame(Transaction::PAYMENT_UNPAID, $transaction->payment_status);
        $this->assertNull($transaction->payment_method);
        $this->assertNull($transaction->paid_at);
    }

    public function test_refunds_retain_original_payment_metadata_and_reject_missing_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();
        $paidAt = now()->subDays(2)->startOfMinute();
        $transaction = Transaction::factory()
            ->for($customer)
            ->for($service)
            ->for($admin, 'recorder')
            ->create([
                'appointment_id' => null,
                'payment_method' => Transaction::METHOD_BANK_TRANSFER,
                'paid_at' => $paidAt,
            ]);

        $this->actingAs($admin)
            ->from(route('admin.transactions.edit', $transaction, false))
            ->patch(route('admin.transactions.update', $transaction, false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'amount' => $transaction->amount,
                'payment_status' => Transaction::PAYMENT_REFUNDED,
            ])
            ->assertRedirect(route('admin.transactions.edit', $transaction, false))
            ->assertSessionHasErrors(['payment_method', 'paid_at']);

        $this->assertSame(Transaction::PAYMENT_PAID, $transaction->fresh()->payment_status);

        $this->actingAs($admin)
            ->patch(route('admin.transactions.update', $transaction, false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'amount' => $transaction->amount,
                'payment_status' => Transaction::PAYMENT_REFUNDED,
                'payment_method' => Transaction::METHOD_BANK_TRANSFER,
                'paid_at' => $paidAt->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('admin.transactions.show', $transaction, false));

        $transaction->refresh();
        $this->assertSame(Transaction::PAYMENT_REFUNDED, $transaction->payment_status);
        $this->assertSame(Transaction::METHOD_BANK_TRANSFER, $transaction->payment_method);
        $this->assertTrue($transaction->paid_at->equalTo($paidAt));
    }

    public function legacy_staff_transaction_updates_enforce_assignment_and_payment_state_rules(): void
    {
        $staffUser = User::factory()->staff()->create();
        $staff = StaffProfile::factory()->for($staffUser)->create();
        $otherStaff = StaffProfile::factory()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();
        $appointment = Appointment::factory()
            ->for($customer)
            ->for($service)
            ->for($staff, 'staffProfile')
            ->create([
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => now()->addDay()->setTime(14, 0),
                'scheduled_end_at' => now()->addDay()->setTime(15, 0),
                'confirmed_at' => now(),
            ]);
        $otherAppointment = Appointment::factory()
            ->for($customer)
            ->for($service)
            ->for($otherStaff, 'staffProfile')
            ->create([
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => now()->addDays(2)->setTime(14, 0),
                'scheduled_end_at' => now()->addDays(2)->setTime(15, 0),
                'confirmed_at' => now(),
            ]);

        $this->actingAs($staffUser)
            ->from(route('staff.transactions.create', absolute: false))
            ->post(route('staff.transactions.store', absolute: false), [
                'appointment_id' => $otherAppointment->id,
                'amount' => 900,
                'payment_status' => Transaction::PAYMENT_PAID,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('staff.transactions.create', absolute: false))
            ->assertSessionHasErrors('appointment_id');

        $this->actingAs($staffUser)
            ->post(route('staff.transactions.store', absolute: false), [
                'appointment_id' => $appointment->id,
                'amount' => 900,
                'payment_status' => Transaction::PAYMENT_PARTIAL,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $transaction = Transaction::query()->sole();

        $this->actingAs($staffUser)
            ->patch(route('staff.transactions.update', $transaction, false), [
                'appointment_id' => $appointment->id,
                'amount' => 900,
                'payment_status' => Transaction::PAYMENT_VOID,
            ])
            ->assertRedirect(route('staff.transactions.show', $transaction, false));

        $transaction->refresh();
        $this->assertSame(Transaction::PAYMENT_VOID, $transaction->payment_status);
        $this->assertNull($transaction->payment_method);
        $this->assertNull($transaction->paid_at);
    }

    public function test_transaction_number_collision_is_retried_during_admin_creation(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();

        Transaction::factory()
            ->for($customer)
            ->for($service)
            ->for($admin, 'recorder')
            ->create([
                'appointment_id' => null,
                'transaction_number' => 'TRX-COLLISION',
            ]);

        $numbers = new class extends TransactionNumber
        {
            private int $calls = 0;

            public function next(): string
            {
                return ++$this->calls === 1 ? 'TRX-COLLISION' : 'TRX-RETRIED';
            }
        };

        $this->app->instance(TransactionNumber::class, $numbers);

        $this->actingAs($admin)
            ->post(route('admin.transactions.store', absolute: false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'amount' => 1000,
                'payment_status' => Transaction::PAYMENT_PAID,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', ['transaction_number' => 'TRX-RETRIED']);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_transaction_numbers_use_a_compact_global_sequence(): void
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();

        Transaction::factory()
            ->for($customer)
            ->for($service)
            ->create(['transaction_number' => 'TRX-0001']);

        $this->assertSame('TRX-0002', app(TransactionNumber::class)->next());
    }
}
