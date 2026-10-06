<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAppointmentDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 16:00:00', 'Asia/Manila'));
    }

    public function test_both_admin_roles_see_booking_cards_and_real_customer_data(): void
    {
        $visit = $this->visit();
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.appointments.show', $visit))->assertOk()
                ->assertSee('Appointment details')->assertSee('Booking details')
                ->assertSee('Visit notes')->assertSee('Booking activity')
                ->assertSee('Visit status')->assertSee('Payment summary')
                ->assertSee($visit->customerProfile->user->name)
                ->assertSee($visit->customerProfile->user->email)
                ->assertSee('Thursday, October 1, 2026')->assertSee('Asia/Manila')
                ->assertSee('60 minutes')
                ->assertSee(route('admin.customers.show', $visit->customerProfile), false)
                ->assertSee(route('admin.appointments.edit', $visit), false)
                ->assertDontSee('Sample data');
        }
    }

    public function test_detail_route_rejects_non_admin_roles(): void
    {
        $visit = $this->visit();
        foreach (['customer', 'staff', 'receptionist'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.appointments.show', $visit))->assertForbidden();
        }
    }

    public function test_future_actions_are_disabled_and_become_available_at_start(): void
    {
        $visit = $this->visit(['scheduled_start_at' => now()->addHour(), 'scheduled_end_at' => now()->addHours(2)]);
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.appointments.show', $visit))->assertOk()
            ->assertSee('disabled aria-describedby="detail-visit-start"', false)
            ->assertSee('Finish and no-show are available once the visit starts.')
            ->assertDontSee('name="status" value="no_show"', false)
            ->assertSee('name="status" value="cancelled"', false);
        $this->travelTo($visit->scheduled_start_at);
        $this->get(route('admin.appointments.show', $visit))->assertOk()
            ->assertDontSee('disabled aria-describedby="detail-visit-start"', false)
            ->assertSee('name="status" value="no_show"', false)
            ->assertSee(route('admin.appointments.outcome', $visit), false);
    }

    public function test_terminal_visits_have_no_transition_controls_and_keep_payment_link(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (['completed', 'cancelled', 'no_show'] as $status) {
            $visit = $this->visit(['status' => $status]);
            $this->get(route('admin.appointments.show', $visit))->assertOk()
                ->assertSee(ucfirst(str_replace('_', '-', $status)))
                ->assertDontSee('Mark as no-show')->assertDontSee('Cancel appointment')
                ->assertDontSee('appointment-detail-visit-actions', false)
                ->assertSee(route('admin.transactions.create', ['appointment_id' => $visit]), false);
        }
    }

    public function test_notes_are_escaped_and_missing_data_has_clear_fallbacks(): void
    {
        $visit = $this->visit([
            'staff_profile_id' => null, 'created_by' => null,
            'scheduled_start_at' => null, 'scheduled_end_at' => null,
            'customer_notes' => '<script>alert("customer")</script>',
            'internal_notes' => '<img src=x onerror=alert("staff")>',
        ]);
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.appointments.show', $visit))->assertOk()
            ->assertSee($visit->customer_notes)->assertSee($visit->internal_notes)
            ->assertDontSee($visit->customer_notes, false)->assertDontSee($visit->internal_notes, false)
            ->assertSee('Staff only')->assertSee('Unassigned')->assertSee('No preference')
            ->assertSee('Not scheduled');
        $appointment = $response->viewData('appointment');
        $appointment->setRelation('customerProfile', null)->setRelation('service', null);
        $html = view('admin.appointments.show', [
            'appointment' => $appointment, 'activity' => $response->viewData('activity'),
            'latestTransaction' => null, 'voucherName' => null, 'transaction' => new Transaction,
        ])->render();
        $this->assertStringContainsString('Customer unavailable', $html);
        $this->assertStringContainsString('Service unavailable', $html);
        $this->assertStringContainsString('No email recorded', $html);
        $this->assertStringNotContainsString('View customer profile', $html);
    }

    public function test_empty_notes_and_payment_do_not_invent_a_balance(): void
    {
        $visit = $this->visit(['customer_notes' => null, 'internal_notes' => null]);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.appointments.show', $visit))
            ->assertOk()->assertSee('No customer notes.')->assertSee('No internal notes.')
            ->assertSee('No payment recorded')->assertSee('0 transactions')
            ->assertSee('No feedback yet')->assertDontSee('Balance due')
            ->assertDontSee('Latest transaction amount')
            ->assertDontSee(route('admin.transactions.create', ['appointment_id' => $visit]), false);
    }

    public function test_addon_snapshots_and_complimentary_voucher_keep_correct_totals(): void
    {
        $service = Service::factory()->create(['name' => 'Signature massage', 'price' => 850, 'duration_minutes' => 60]);
        Addon::query()->updateOrCreate(['code' => 'hot-compress'], ['name' => 'Hot Compress', 'price' => 200, 'duration_minutes' => 0, 'is_active' => true]);
        $voucher = PromotionSuggestion::factory()->create();
        $visit = $this->visit([
            'service_id' => $service->id, 'promotion_suggestion_id' => $voucher->id,
            'scheduled_end_at' => now()->setTime(14, 30),
            'preferred_staff_profile_id' => StaffProfile::factory()->create()->id,
        ]);
        $visit->addons()->createMany([
            ['addon_code' => 'back-massage', 'addon_name' => '30-Minute Back Massage', 'price' => 299, 'duration_minutes' => 30],
            ['addon_code' => 'hot-compress', 'addon_name' => 'Stored Hot Compress', 'price' => 200, 'duration_minutes' => 0],
        ]);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.appointments.show', $visit))
            ->assertOk()->assertSee('90 minutes')->assertSee('+30 min')
            ->assertSee('No additional time')->assertSee('Stored Hot Compress')
            ->assertSee('₱1,349.00')->assertSee('Complimentary')->assertSee('Hot Compress')
            ->assertSee('The confirmed therapist differs from the customer preference.');
    }

    public function test_activity_is_newest_first_with_creation_and_actor_names(): void
    {
        $creator = User::factory()->admin()->create(['name' => 'Booking creator']);
        $visit = $this->visit(['created_by' => $creator->id, 'created_at' => now()->subDays(2)]);
        $visit->statusLogs()->create(['from_status' => 'confirmed', 'to_status' => 'completed', 'changed_by' => $creator->id]);
        $visit->statusLogs()->create(['from_status' => null, 'to_status' => 'confirmed', 'changed_by' => $creator->id])
            ->forceFill(['created_at' => now()->subDay()])->save();
        $this->actingAs($creator)->get(route('admin.appointments.show', $visit))->assertOk()
            ->assertViewHas('activity', fn ($events) => $events->pluck('label')->all() === ['Appointment completed', 'Appointment confirmed', 'Booking created'])
            ->assertViewHas('appointment', fn ($appointment) => $appointment->relationLoaded('creator'))
            ->assertSee('Booking creator')->assertSee('Oct 1, 2026, 4:00 PM');
    }

    public function test_latest_transaction_status_and_amount_are_shown_without_summing_payments(): void
    {
        $visit = $this->visit(['status' => 'completed']);
        $this->actingAs(User::factory()->admin()->create());
        foreach (Transaction::PAYMENT_STATUSES as $index => $status) {
            $transaction = Transaction::factory()->create([
                'appointment_id' => $visit->id, 'customer_profile_id' => $visit->customer_profile_id,
                'service_id' => $visit->service_id, 'payment_status' => $status, 'amount' => 321.25 + $index,
            ]);
            $response = $this->get(route('admin.appointments.show', $visit))->assertOk()
                ->assertViewHas('latestTransaction', fn ($record) => $record->id === $transaction->id)
                ->assertSee(ucfirst($status))->assertSee('₱'.number_format($transaction->amount, 2))
                ->assertSee('Latest transaction amount')->assertDontSee('Balance due');
        }
        $response->assertSee('5 transactions');
        foreach ($visit->transactions as $record) {
            $response->assertSee(route('admin.transactions.show', $record), false);
        }
    }

    public function test_completion_validation_reopens_detail_modal_and_preserves_values(): void
    {
        $visit = $this->visit();
        $modal = 'admin-appointment-completion-'.$visit->id;
        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.appointments.show', $visit))
            ->post(route('admin.appointments.complete', $visit), [
                '_modal' => $modal, 'amount' => -1, 'payment_status' => 'unpaid', 'notes' => 'Keep this payment note',
            ])->assertRedirect(route('admin.appointments.show', $visit))->assertSessionHasErrors('amount');
        $this->get(route('admin.appointments.show', $visit))->assertOk()
            ->assertSee('initialShow: true', false)->assertSee('Keep this payment note')
            ->assertSee('value="-1"', false);
        $this->assertSame(Appointment::STATUS_CONFIRMED, $visit->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_submitted_feedback_links_to_its_existing_record(): void
    {
        $visit = $this->visit(['status' => 'completed']);
        $feedback = Feedback::factory()->create([
            'appointment_id' => $visit->id,
            'customer_profile_id' => $visit->customer_profile_id,
            'service_id' => $visit->service_id,
        ]);
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.appointments.show', $visit))->assertOk()
            ->assertSee('Feedback submitted')->assertDontSee('No feedback yet')
            ->assertSee(route('admin.feedback.show', $feedback), false);
    }

    private function visit(array $attributes = []): Appointment
    {
        return Appointment::factory()->create([
            'scheduled_start_at' => now()->setTime(13, 0),
            'scheduled_end_at' => now()->setTime(14, 0),
            ...$attributes,
        ]);
    }
}
