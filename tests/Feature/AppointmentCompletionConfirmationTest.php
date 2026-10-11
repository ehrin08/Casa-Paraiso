<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\PromotionSuggestion;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AppointmentCompletionConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentCompletionConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Manila')->setDate(2026, 10, 11)->setTime(16, 0));
    }

    public static function completionCases(): array
    {
        $cases = [];
        foreach (['admin' => 'admin', 'super_admin' => 'admin', 'receptionist' => 'reception'] as $role => $workspace) {
            foreach (['paid', 'partial', 'unpaid'] as $status) {
                $cases[$role.' '.$status] = [$role, $workspace, $status];
            }
        }

        return $cases;
    }

    #[DataProvider('completionCases')]
    public function test_success_returns_to_appointments_with_saved_details_once(string $role, string $workspace, string $status): void
    {
        $actor = User::factory()->create(['role' => $role, 'name' => 'Front desk recorder']);
        $visit = $this->visit();
        $this->actingAs($actor)->post(route($workspace.'.appointments.complete', $visit), [
            'amount' => 749.25,
            'payment_status' => $status,
            'payment_method' => Transaction::METHOD_GCASH,
            'paid_at' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
            'notes' => '<script>alert("test")</script> Payment recorded.',
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route($workspace.'.appointments.index'))
            ->assertSessionHas(AppointmentCompletionConfirmation::FLASH_KEY);

        $transaction = $visit->transactions()->sole();
        $response = $this->get(route($workspace.'.appointments.index'))->assertOk()
            ->assertViewHas('completedTransaction', fn ($saved) => $saved->id === $transaction->id)
            ->assertSee('Appointment completed')->assertSee($visit->appointment_number)
            ->assertSee($transaction->transaction_number)->assertSee('Walk-in customer')
            ->assertSee('Massage package')->assertSee('Assigned therapist')->assertSee('Front desk recorder')
            ->assertSee('PHP 749.25')->assertSee(ucfirst($status))->assertSee('Oct 11, 2026 1:00 PM')
            ->assertSee('data-transient-modal', false)->assertSee('<noscript>', false)
            ->assertSee(route($workspace.'.transactions.show', $transaction), false)
            ->assertSee('<script>alert("test")</script> Payment recorded.')
            ->assertDontSee('<script>alert("test")</script>', false);

        $this->assertSame(Appointment::STATUS_COMPLETED, $visit->fresh()->status);
        $this->assertSame('749.25', $transaction->amount);
        if ($status === 'unpaid') {
            $response->assertSee('Not received');
            $this->assertNull($transaction->payment_method);
            $this->assertNull($transaction->paid_at);
        } else {
            $response->assertSee('GCash')->assertSee('Oct 11, 2026 3:50 PM');
        }
        $this->assertFalse(session()->has(AppointmentCompletionConfirmation::FLASH_KEY));
        $this->get(route($workspace.'.appointments.index'))->assertOk()
            ->assertViewHas('completedTransaction', null)->assertDontSee('data-transient-modal', false);
    }

    public function test_addon_snapshots_voucher_and_empty_notes_are_shown(): void
    {
        $visit = $this->visit();
        $visit->addons()->create(['addon_code' => 'ventosa', 'addon_name' => 'Historical Ventosa', 'price' => 125, 'duration_minutes' => 15]);
        Addon::query()->create(['code' => 'voucher-compress', 'name' => 'Complimentary compress', 'price' => 100, 'duration_minutes' => 15, 'is_active' => false]);
        $voucher = PromotionSuggestion::factory()->create(['customer_profile_id' => $visit->customer_profile_id, 'addon_code' => 'voucher-compress']);
        $visit->update(['promotion_suggestion_id' => $voucher->id]);
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.appointments.complete', $visit), ['amount' => 1125, 'payment_status' => 'unpaid'])
            ->assertSessionHasNoErrors();
        $this->get(route('admin.appointments.index'))->assertOk()
            ->assertSee('Historical Ventosa')->assertSee('PHP 125.00')->assertSee('Complimentary compress')
            ->assertSee('PHP 1,125.00')->assertSee('None');
    }

    public function test_confirmation_rejects_missing_invalid_unlinked_unfinished_or_other_recorders_data(): void
    {
        $actor = User::factory()->admin()->create();
        $visit = $this->visit();
        $otherRecorder = Transaction::factory()->create(['appointment_id' => $visit->id]);
        $unfinished = Transaction::factory()->create(['appointment_id' => $visit->id, 'recorded_by' => $actor->id]);
        $unlinked = Transaction::factory()->create(['appointment_id' => null, 'recorded_by' => $actor->id]);
        foreach ([null, 'invalid', ['invalid'], 2147483647, $otherRecorder->id, $unfinished->id, $unlinked->id] as $id) {
            $this->actingAs($actor)->withSession([AppointmentCompletionConfirmation::FLASH_KEY => $id])
                ->get(route('admin.appointments.index'))->assertOk()
                ->assertViewHas('completedTransaction', null)->assertDontSee('data-transient-modal', false);
            $this->assertFalse(session()->has(AppointmentCompletionConfirmation::FLASH_KEY));
        }
        $visit->update(['status' => Appointment::STATUS_COMPLETED]);
        $this->actingAs($actor)->withSession([AppointmentCompletionConfirmation::FLASH_KEY => $otherRecorder->id])
            ->get(route('admin.appointments.index'))->assertOk()->assertViewHas('completedTransaction', null);
    }

    public function test_rejected_finishes_never_flash_success_or_create_duplicate_transactions(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $visit = $this->visit();
        $this->post(route('admin.appointments.complete', $visit), ['amount' => -1, 'payment_status' => 'paid'])
            ->assertSessionHasErrors('amount')->assertSessionMissing(AppointmentCompletionConfirmation::FLASH_KEY);
        $future = $this->visit(['scheduled_start_at' => now()->addDay()]);
        $this->post(route('admin.appointments.complete', $future), ['amount' => 1000, 'payment_status' => 'unpaid'])
            ->assertSessionHasErrors('status')->assertSessionMissing(AppointmentCompletionConfirmation::FLASH_KEY);
        $this->post(route('admin.appointments.complete', $visit), ['amount' => 1000, 'payment_status' => 'unpaid'])
            ->assertSessionHasNoErrors();
        $this->get(route('admin.appointments.index'))->assertOk();
        $this->post(route('admin.appointments.complete', $visit), ['amount' => 1000, 'payment_status' => 'unpaid'])
            ->assertSessionHasErrors('status')->assertSessionMissing(AppointmentCompletionConfirmation::FLASH_KEY);
        $this->assertSame(1, $visit->transactions()->count());
        $this->get(route('admin.appointments.index'))->assertOk()->assertDontSee('data-transient-modal', false);
    }

    private function visit(array $attributes = []): Appointment
    {
        return Appointment::factory()->create([
            'customer_profile_id' => CustomerProfile::createGuest('Walk-in customer', null)->id,
            'service_id' => Service::factory()->create(['name' => 'Massage package', 'price' => 1000])->id,
            'staff_profile_id' => StaffProfile::factory()->create(['user_id' => User::factory()->staff()->create(['name' => 'Assigned therapist'])->id])->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_start_at' => now()->setTime(13, 0),
            'scheduled_end_at' => now()->setTime(14, 0),
            'completed_at' => null,
            ...$attributes,
        ]);
    }
}
