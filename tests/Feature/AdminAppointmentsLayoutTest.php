<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\StaffProfile;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAppointmentsLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 16:00:00', 'Asia/Manila'));
    }

    public function test_daily_counts_and_queue_respect_manila_boundaries(): void
    {
        $yesterday = $this->visit('2026-09-30 23:59:59');
        $today = $this->visit('2026-10-01 00:00:00');
        $late = $this->visit('2026-10-01 23:59:59');
        $tomorrow = $this->visit('2026-10-02 00:00:00');
        $this->visit('2026-10-01 13:00:00', Appointment::STATUS_COMPLETED);
        $this->visit('2026-10-01 14:00:00', Appointment::STATUS_CANCELLED);
        $this->visit('2026-09-30 13:00:00', Appointment::STATUS_COMPLETED);
        $this->visit('2026-10-02 13:00:00', Appointment::STATUS_CANCELLED);
        $this->visit('2026-10-01 15:00:00', Appointment::STATUS_NO_SHOW);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.appointments.index'))
            ->assertOk()->assertSee('Thursday, October 1, 2026')
            ->assertViewHas('summary', ['confirmed' => 2, 'completed' => 1, 'cancelled' => 1])
            ->assertViewHas('initialWeek', '2026-09-28')
            ->assertViewHas('serviceQueue', fn ($queue) => $queue->pluck('id')->all() === [$yesterday->id, $today->id, $late->id])
            ->assertSee('Sep 30, 2026')->assertSee('Overdue')
            ->assertDontSee($tomorrow->appointment_number);
    }

    public function test_empty_state_and_both_admin_roles_use_weekly_presentation(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.appointments.index'))->assertOk()
                ->assertSee('The service queue is clear')->assertSee('data-week-schedule', false)
                ->assertSee('Weekly therapist schedule')->assertSee('This week')
                ->assertSee(route('admin.appointments.records'), false);
            $this->get(route('admin.appointments.records'))->assertOk()->assertSee('No matching appointments');
        }
    }

    public function test_non_admin_roles_cannot_access_admin_pages(): void
    {
        foreach (['customer', 'staff', 'receptionist'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('admin.appointments.index'))->assertForbidden();
            $this->get(route('admin.appointments.records'))->assertForbidden();
        }
    }

    public function test_queue_ties_use_id_and_future_actions_are_disabled(): void
    {
        $first = $this->visit('2026-10-01 17:00:00');
        $second = $this->visit('2026-10-01 17:00:00');
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.appointments.index'))->assertOk()
            ->assertViewHas('serviceQueue', fn ($queue) => $queue->pluck('id')->all() === [$first->id, $second->id])
            ->assertSee('disabled aria-describedby="visit-start-'.$first->id.'"', false)
            ->assertSee('Available once the visit starts.');
    }

    public function test_queue_view_links_request_the_panel_preview_and_forms_keep_context(): void
    {
        $visit = $this->visit('2026-10-01 13:00:00');
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.appointments.index', ['mode' => 'availability']))->assertOk()
            ->assertSee(route('admin.appointments.show', ['appointment' => $visit, 'preview' => 1]), false)
            ->assertSee('data-panel-link data-turbo="false" aria-label="View visit '.$visit->appointment_number.'"', false)
            ->assertSee('name="return_to" value="index"', false)
            ->assertSee('name="queue_page" value="1"', false)
            ->assertSee('name="mode" value="availability"', false);
    }

    public function test_both_admin_roles_can_load_read_only_details_with_no_embedded_action_dialogs(): void
    {
        $visit = $this->visit('2026-10-01 13:00:00', extra: ['internal_notes' => '<script>Preview note</script>']);
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.appointments.show', ['appointment' => $visit, 'preview' => 1]), ['X-Casa-Panel' => '1'])
                ->assertOk()->assertViewHas('isPreview', true)
                ->assertSee('data-appointment-preview', false)->assertSee($visit->appointment_number)
                ->assertSee('Booking details')->assertSee('Treatment & add-ons')->assertSee('Visit notes')
                ->assertSee('Booking activity')->assertSee('Visit status')->assertSee('Payment summary')
                ->assertSee('<script>Preview note</script>')->assertDontSee('<script>Preview note</script>', false)
                ->assertSee('Open full details')->assertSee('data-panel-close', false)
                ->assertDontSee('Edit appointment')->assertDontSee('Finish service')->assertDontSee('Mark as no-show')
                ->assertDontSee('Cancel appointment')->assertDontSee('Record payment')
                ->assertDontSee('admin-appointment-completion-'.$visit->id, false)
                ->assertDontSee('confirm-action-', false);
        }
    }

    public function test_preview_requires_the_panel_header_and_explicit_query_and_returns_current_data(): void
    {
        $visit = $this->visit('2026-10-01 13:00:00');
        $admin = User::factory()->admin()->create();
        $url = route('admin.appointments.show', ['appointment' => $visit, 'preview' => 1]);
        $this->actingAs($admin)->get($url)->assertOk()->assertViewHas('isPreview', false)
            ->assertSee('Edit appointment')->assertSee('Finish service')->assertDontSee('data-appointment-preview', false);
        $this->get(route('admin.appointments.show', $visit), ['X-Casa-Panel' => '1'])->assertOk()
            ->assertViewHas('isPreview', false)->assertSee('Edit appointment');
        $visit->update(['internal_notes' => 'Newly saved information']);
        $this->get($url, ['X-Casa-Panel' => '1'])->assertOk()->assertSee('Newly saved information');
        foreach (['customer', 'staff', 'receptionist'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get($url, ['X-Casa-Panel' => '1'])->assertForbidden();
        }
    }

    public function test_queue_completion_clamps_an_empty_last_page_and_records_one_transaction(): void
    {
        foreach (range(1, 15) as $unused) {
            $this->visit('2026-10-01 13:00:00');
        }
        $visit = $this->visit('2026-10-01 14:00:00');
        $this->visit('2026-10-02 13:00:00');
        $target = route('admin.appointments.index', ['queue_page' => 1, 'mode' => 'availability']).'#service-queue';
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.appointments.complete', $visit), [
            'amount' => $visit->expectedAmount(), 'payment_status' => 'unpaid',
            'return_to' => 'index', 'queue_page' => 2, 'mode' => 'availability',
        ])->assertRedirect($target)->assertSessionHas('status', 'appointment-completed');
        $this->assertSame(Appointment::STATUS_COMPLETED, $visit->fresh()->status);
        $this->assertSame($visit->id, Transaction::query()->sole()->appointment_id);
        $this->get($target)->assertOk()
            ->assertViewHas('serviceQueue', fn ($queue) => $queue->currentPage() === 1 && $queue->total() === 15)
            ->assertViewHas('summary', ['confirmed' => 15, 'completed' => 1, 'cancelled' => 0]);
    }

    public function test_queue_no_show_retains_a_remaining_page_and_creates_no_transaction(): void
    {
        foreach (range(1, 16) as $unused) {
            $this->visit('2026-10-01 13:00:00');
        }
        $visit = $this->visit('2026-10-01 14:00:00');
        $target = route('admin.appointments.index', ['queue_page' => 2, 'mode' => 'bookings']).'#service-queue';
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->patch(route('admin.appointments.outcome', $visit), [
                'status' => 'no_show', 'return_to' => 'index', 'queue_page' => 2, 'mode' => 'bookings',
            ])->assertRedirect($target)->assertSessionHas('status', 'appointment-updated');
        $this->assertSame(Appointment::STATUS_NO_SHOW, $visit->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
        $this->get($target)->assertOk()
            ->assertViewHas('serviceQueue', fn ($queue) => $queue->currentPage() === 2 && $queue->count() === 1);
    }

    public function test_queue_no_show_returns_to_page_one_when_the_queue_becomes_empty(): void
    {
        $visit = $this->visit('2026-10-01 13:00:00');
        $target = route('admin.appointments.index', ['queue_page' => 1, 'mode' => 'bookings']).'#service-queue';
        $this->actingAs(User::factory()->admin()->create())->patch(route('admin.appointments.outcome', $visit), [
            'status' => 'no_show', 'return_to' => 'index', 'queue_page' => 2,
        ])->assertRedirect($target);
        $this->get($target)->assertOk()->assertSee('The service queue is clear');
    }

    public function test_completion_without_queue_context_returns_to_appointments(): void
    {
        $visit = $this->visit('2026-10-01 13:00:00');
        $response = $this->actingAs(User::factory()->admin()->create())->post(route('admin.appointments.complete', $visit), [
            'amount' => $visit->expectedAmount(), 'payment_status' => 'unpaid',
        ]);
        $response->assertRedirect(route('admin.appointments.index'))
            ->assertSessionHas('completed_transaction_id', Transaction::query()->sole()->id);
    }

    public function test_invalid_return_context_is_rejected_before_mutation(): void
    {
        $visit = $this->visit('2026-10-01 13:00:00');
        $context = ['return_to' => 'https://example.com', 'queue_page' => 0, 'mode' => 'invalid'];
        $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.appointments.complete', $visit), [
            'amount' => 0, 'payment_status' => 'unpaid', ...$context,
        ])->assertUnprocessable()->assertJsonValidationErrors(['return_to', 'queue_page', 'mode']);
        $this->patchJson(route('admin.appointments.outcome', $visit), ['status' => 'no_show', ...$context])
            ->assertUnprocessable()->assertJsonValidationErrors(['return_to', 'queue_page', 'mode']);
        $this->assertSame(Appointment::STATUS_CONFIRMED, $visit->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_completion_validation_reopens_only_its_modal_with_unique_fields(): void
    {
        $first = $this->visit('2026-10-01 13:00:00');
        $second = $this->visit('2026-10-01 14:00:00');
        $modal = 'admin-appointment-completion-'.$first->id;
        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.appointments.index'))
            ->post(route('admin.appointments.complete', $first), [
                '_modal' => $modal, 'amount' => -1, 'payment_status' => 'unpaid', 'notes' => 'Keep this note',
                'return_to' => 'index', 'queue_page' => 1, 'mode' => 'availability',
            ])->assertRedirect(route('admin.appointments.index'))->assertSessionHasErrors('amount')
            ->assertSessionHasInput('_modal', $modal);
        $response = $this->get(route('admin.appointments.index'))->assertOk()->assertViewHas('mode', 'availability');
        $html = $response->getContent();
        $this->assertStringContainsString('completion_'.$first->id.'_amount', $html);
        $this->assertStringContainsString('completion_'.$second->id.'_amount', $html);
        $this->assertSame(1, substr_count($html, 'Keep this note'));
        $this->assertSame(1, substr_count($html, 'initialShow: true'));
        $this->assertSame(Appointment::STATUS_CONFIRMED, $first->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_records_search_filters_date_boundaries_and_historical_therapists(): void
    {
        $staff = StaffProfile::factory()->create(['is_bookable' => false]);
        $customer = CustomerProfile::factory()->create();
        $customer->user->update(['name' => 'Isabel Records']);
        $included = $this->visit('2026-10-01 23:59:59', Appointment::STATUS_COMPLETED, [
            'customer_profile_id' => $customer->id, 'staff_profile_id' => $staff->id,
        ]);
        $this->visit('2026-10-02 00:00:00', Appointment::STATUS_COMPLETED, [
            'customer_profile_id' => $customer->id, 'staff_profile_id' => $staff->id,
        ]);
        $this->visit('2026-10-01 13:00:00', Appointment::STATUS_CANCELLED, [
            'customer_profile_id' => $customer->id, 'staff_profile_id' => $staff->id,
        ]);
        $filters = ['q' => 'Isabel Records', 'status' => 'completed', 'staff_profile_id' => $staff->id, 'from' => '2026-10-01', 'to' => '2026-10-01'];
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.appointments.records', $filters))
            ->assertOk()->assertViewHas('appointments', fn ($rows) => $rows->pluck('id')->all() === [$included->id]);
        $this->get(route('admin.appointments.records', ['q' => $included->appointment_number]))
            ->assertOk()->assertViewHas('appointments', fn ($rows) => $rows->total() === 1);
        $this->get(route('admin.appointments.records', ['to' => '2026-10-01']))
            ->assertOk()->assertViewHas('appointments', fn ($rows) => $rows->total() === 2);
    }

    public function test_completion_validation_survives_a_row_moving_off_the_queue_page(): void
    {
        $visit = $this->visit('2026-10-01 13:00:00');
        $modal = 'admin-appointment-completion-'.$visit->id;
        $this->actingAs(User::factory()->admin()->create())->from(route('admin.appointments.index'))
            ->post(route('admin.appointments.complete', $visit), [
                '_modal' => $modal, 'amount' => -1, 'payment_status' => 'unpaid',
            ])->assertSessionHasErrors('amount');
        $visit->update(['status' => Appointment::STATUS_CANCELLED]);
        $this->get(route('admin.appointments.index'))->assertOk()
            ->assertViewHas('completionAppointments', fn ($visits) => $visits->contains('id', $visit->id))
            ->assertSee('initialShow: true', false);
    }

    public function test_records_pagination_retains_filters_and_rejects_invalid_values(): void
    {
        foreach (range(1, 16) as $day) {
            $this->visit(sprintf('2026-09-%02d 13:00:00', $day), Appointment::STATUS_COMPLETED);
        }
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.appointments.records', ['status' => 'completed', 'per_page' => 100]))
            ->assertOk()->assertViewHas('appointments', fn ($rows) => $rows->count() === 15 && $rows->total() === 16)
            ->assertSee('status=completed', false);
        $this->get(route('admin.appointments.records', ['status' => 'completed', 'page' => 2]))
            ->assertOk()->assertViewHas('appointments', fn ($rows) => $rows->count() === 1);
        $this->getJson(route('admin.appointments.records', ['status' => 'invalid', 'from' => '2026-10-02', 'to' => '2026-10-01']))
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'to']);
    }

    public function test_calendar_feed_keeps_therapist_name_and_filters_bookings(): void
    {
        $first = $this->visit('2026-10-01 13:00:00');
        $this->visit('2026-10-01 14:00:00', Appointment::STATUS_CANCELLED);
        $response = $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.appointments.calendar', [
            'start' => '2026-09-28', 'end' => '2026-10-05', 'status' => 'confirmed', 'staff_profile_id' => $first->staff_profile_id,
        ]))->assertOk();
        $events = collect($response->json('events'))->where('kind', 'appointment');
        $this->assertSame([$first->id], $events->pluck('appointment_id')->all());
        $this->assertSame($first->staffProfile->user->name, $events->first()['staff_name']);
    }

    private function visit(string $time, string $status = Appointment::STATUS_CONFIRMED, array $extra = []): Appointment
    {
        $start = CarbonImmutable::parse($time, 'Asia/Manila');

        return Appointment::factory()->create([
            'status' => $status, 'scheduled_start_at' => $start, 'scheduled_end_at' => $start->addHour(), ...$extra,
        ]);
    }
}
