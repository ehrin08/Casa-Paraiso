<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\OperatingHourRule;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use App\Services\ScheduleWindowResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OperatingHoursTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Service $service;

    private StaffProfile $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00', 'Asia/Manila'));
        $this->admin = User::factory()->admin()->create();
        $this->service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $this->staff = StaffProfile::factory()->create(['is_bookable' => true]);
        $this->staff->services()->attach($this->service);

        foreach (range(0, 6) as $day) {
            StaffWeeklySchedule::factory()->for($this->staff)->create(['day_of_week' => $day, 'start_time' => '13:00:00', 'end_time' => '00:00:00', 'ends_next_day' => true]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_settings_link_to_the_operating_hours_page_and_the_old_address_redirects(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk()
            ->assertSee(route('admin.operating-hours.index'), false)
            ->assertSee('1:00 PM to 11:00 PM');

        $this->get('/admin/closed-dates')->assertRedirect('/admin/operating-hours')->assertStatus(301);

        $this->get(route('admin.operating-hours.index', ['month' => '2026-10', 'date' => '2026-10-07']))->assertOk()
            ->assertSee('October 2026')
            ->assertSee('value="2026-10-07"', false)
            ->assertSee('data-hours-date="2026-10-05" data-hours-status="open"', false);
    }

    public function test_only_admins_manage_operating_hours(): void
    {
        foreach ([User::factory()->receptionist()->create(), User::factory()->staff()->create(), User::factory()->customer()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.operating-hours.index'))->assertForbidden();
            $this->post(route('admin.operating-hours.rules.store'), $this->closure('2026-11-01'))->assertForbidden();
            $this->put(route('admin.operating-hours.defaults'), ['opens_at' => '10:00', 'closes_at' => '20:00'])->assertForbidden();
        }

        $this->assertDatabaseCount('operating_hour_rules', 0);
    }

    public function test_admin_can_close_a_date_and_remove_the_closure(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.operating-hours.rules.store'), $this->closure('2026-11-01', 'All Saints Day'))
            ->assertRedirect(route('admin.operating-hours.index', ['month' => '2026-11']))
            ->assertSessionHas('status', 'operating-hour-rule-created');

        $rule = OperatingHourRule::query()->sole();
        $this->assertSame(['closed', '2026-11-01', '2026-11-01', null, 'All Saints Day', $this->admin->id],
            [$rule->type, $rule->starts_on->toDateString(), $rule->ends_on->toDateString(), $rule->start_time, $rule->reason, $rule->created_by]);

        $this->get(route('admin.operating-hours.index', ['month' => '2026-11']))->assertOk()
            ->assertSee('Sun, Nov 1, 2026')->assertSee('All Saints Day')->assertSee($this->admin->name)
            ->assertSee('data-hours-date="2026-11-01" data-hours-status="closed"', false);

        $this->delete(route('admin.operating-hours.rules.destroy', $rule))->assertSessionHas('status', 'operating-hour-rule-deleted');
        $this->assertModelMissing($rule);
    }

    public function test_invalid_changes_are_rejected(): void
    {
        $this->actingAs($this->admin)->from(route('admin.operating-hours.index'));

        $this->post(route('admin.operating-hours.rules.store'), $this->closure('2026-10-03'))->assertSessionHasErrors('starts_on');
        $this->post(route('admin.operating-hours.rules.store'), [...$this->closure('2026-10-05'), 'reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('admin.operating-hours.rules.store'), [...$this->closure('2026-10-05'), 'end_mode' => 'until'])->assertSessionHasErrors('ends_on');
        $this->post(route('admin.operating-hours.rules.store'), [...$this->closure('2026-10-05'), 'end_mode' => 'until', 'ends_on' => '2026-10-04'])->assertSessionHasErrors('ends_on');
        $this->post(route('admin.operating-hours.rules.store'), [...$this->closure('2026-10-05'), 'type' => 'special_hours'])->assertSessionHasErrors(['start_time', 'end_time']);
        $this->post(route('admin.operating-hours.rules.store'), $this->timed('special_hours', '2026-10-05', '18:00', '15:00'))->assertSessionHasErrors('end_time');
        $this->post(route('admin.operating-hours.rules.store'), $this->timed('special_hours', '2026-10-05', '15:15', '18:00'))->assertSessionHasErrors('start_time');
        $this->put(route('admin.operating-hours.defaults'), ['opens_at' => '20:00', 'closes_at' => '13:00'])->assertSessionHasErrors('closes_at');

        // Special hours cannot overlap other special hours, including an open-ended rule.
        OperatingHourRule::query()->create(['type' => 'special_hours', 'starts_on' => '2026-10-10', 'ends_on' => null, 'start_time' => '15:00', 'end_time' => '20:00', 'reason' => 'Short staff']);
        $this->post(route('admin.operating-hours.rules.store'), $this->timed('special_hours', '2026-12-24', '13:00', '18:00'))->assertSessionHasErrors('starts_on');
        $this->post(route('admin.operating-hours.rules.store'), $this->timed('special_hours', '2026-10-05', '13:00', '18:00'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('operating_hour_rules', 2);
    }

    public function test_default_hours_drive_every_day_and_page(): void
    {
        $this->actingAs($this->admin)->put(route('admin.operating-hours.defaults'), ['opens_at' => '15:00', 'closes_at' => '20:00'])
            ->assertSessionHas('status', 'operating-hours-updated');

        $settings = ApplicationSetting::current();
        $this->assertSame(['15:00', '20:00', '3:00 PM to 8:00 PM'], [$settings->opensAt(), $settings->closesAt(), $settings->hoursLabel()]);
        $this->assertSame(['15:00', '19:00'], $this->slotTimes('2026-10-06', true));
        $this->get('/')->assertOk()->assertSee('3:00 PM to 8:00 PM');
    }

    public function test_a_closed_range_and_an_open_ended_closure_block_every_covered_date(): void
    {
        $this->actingAs($this->admin)->post(route('admin.operating-hours.rules.store'), [...$this->closure('2026-10-05', 'Renovation'), 'end_mode' => 'until', 'ends_on' => '2026-10-07'])
            ->assertSessionHasNoErrors();
        $this->post(route('admin.operating-hours.rules.store'), [...$this->closure('2026-10-20', 'Moving'), 'end_mode' => 'indefinite'])
            ->assertSessionHasNoErrors();
        $this->assertNull(OperatingHourRule::query()->where('reason', 'Moving')->value('ends_on'));

        $response = $this->availability('2026-10');
        foreach (['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-20', '2026-10-31'] as $date) {
            $this->assertNull($response->json('store_dates.'.$date), $date);
            $this->assertNotNull($response->json('closed_dates.'.$date), $date);
        }
        $this->assertNotEmpty($response->json('dates.2026-10-08'));
        $this->assertNotEmpty($response->json('dates.2026-10-19'));
        $this->assertSame('Moving', app(ScheduleWindowResolver::class)->closedReason(Carbon::parse('2027-03-01', 'Asia/Manila')));

        $customer = CustomerProfile::factory()->create();
        $this->post(route('admin.appointments.store', absolute: false), [
            'customer_profile_id' => $customer->id, 'service_id' => $this->service->id, 'staff_profile_id' => $this->staff->id,
            'scheduled_start_at' => '2026-10-06 15:00:00', 'status' => Appointment::STATUS_CONFIRMED,
        ])->assertSessionHasErrors();
        $this->actingAs($customer->user)->post(route('customer.appointments.store', absolute: false), [
            'service_id' => $this->service->id, 'requested_start_at' => '2026-10-21 15:00:00',
        ])->assertSessionHasErrors();
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_ending_a_started_open_ended_rule_reopens_later_dates(): void
    {
        $rule = OperatingHourRule::query()->create(['type' => 'closed', 'starts_on' => '2026-10-01', 'ends_on' => null, 'reason' => 'Repairs']);

        $this->actingAs($this->admin)->get(route('admin.operating-hours.index'))->assertOk()
            ->assertSee('Thu, Oct 1, 2026, until further notice')
            ->assertSee(route('admin.operating-hours.rules.end', $rule), false);

        $this->patch(route('admin.operating-hours.rules.end', $rule))->assertSessionHas('status', 'operating-hour-rule-ended');

        $this->assertSame('2026-10-03', $rule->fresh()->ends_on->toDateString());
        $this->assertNotEmpty($this->availability('2026-10')->json('dates.2026-10-04'));
    }

    public function test_special_hours_replace_the_default_window(): void
    {
        $this->actingAs($this->admin)->post(route('admin.operating-hours.rules.store'), $this->timed('special_hours', '2026-10-06', '15:00', '20:00', 'Town fiesta'))
            ->assertSessionHasNoErrors();

        $this->assertSame(['15:00', '19:00'], $this->slotTimes('2026-10-06'));
        $this->assertSame(['13:00', '22:00'], $this->slotTimes('2026-10-07'));
        $this->assertSame('2026-10-06 20:00:00', $this->availability('2026-10')->json('store_dates.2026-10-06.closes_at'));

        $customer = CustomerProfile::factory()->create();
        $this->post(route('admin.appointments.store', absolute: false), [
            'customer_profile_id' => $customer->id, 'service_id' => $this->service->id, 'staff_profile_id' => $this->staff->id,
            'scheduled_start_at' => '2026-10-06 13:00:00', 'status' => Appointment::STATUS_CONFIRMED,
        ])->assertSessionHasErrors();
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_partial_closure_removes_only_the_blocked_times(): void
    {
        $this->actingAs($this->admin)->post(route('admin.operating-hours.rules.store'), $this->timed('partial_closure', '2026-10-06', '16:00', '18:00', 'Staff meeting'))
            ->assertSessionHasNoErrors();

        $times = collect($this->availability('2026-10')->json('dates.2026-10-06'))->pluck('time');
        $this->assertContains('15:00', $times);
        $this->assertContains('18:00', $times);
        foreach (['15:30', '16:00', '17:00', '17:30'] as $blocked) {
            $this->assertNotContains($blocked, $times);
        }

        $this->get(route('admin.operating-hours.index', ['month' => '2026-10']))
            ->assertSee('data-hours-date="2026-10-06" data-hours-status="partial"', false);
    }

    public function test_changes_that_strand_confirmed_appointments_are_refused(): void
    {
        $customer = CustomerProfile::factory()->create();
        $appointment = $this->confirmedAt($customer, '2026-10-05 15:00');
        // Cancelled visits never block a change.
        Appointment::factory()->create([
            'customer_profile_id' => $customer->id, 'service_id' => $this->service->id, 'staff_profile_id' => $this->staff->id,
            'status' => Appointment::STATUS_CANCELLED, 'requested_start_at' => '2026-10-06 15:00', 'scheduled_start_at' => Carbon::parse('2026-10-06 15:00', 'Asia/Manila'), 'scheduled_end_at' => Carbon::parse('2026-10-06 16:00', 'Asia/Manila'),
        ]);

        $this->actingAs($this->admin)->from(route('admin.operating-hours.index'));

        $this->post(route('admin.operating-hours.rules.store'), $this->closure('2026-10-05'))
            ->assertRedirect(route('admin.operating-hours.index'))
            ->assertSessionHasErrors('starts_on');
        $this->post(route('admin.operating-hours.rules.store'), [...$this->closure('2026-10-01'), 'starts_on' => '2026-10-04', 'end_mode' => 'indefinite'])
            ->assertSessionHasErrors('starts_on');
        $this->post(route('admin.operating-hours.rules.store'), $this->timed('special_hours', '2026-10-05', '17:00', '22:00'))->assertSessionHasErrors('starts_on');
        $this->post(route('admin.operating-hours.rules.store'), $this->timed('partial_closure', '2026-10-05', '15:30', '17:00'))->assertSessionHasErrors('starts_on');
        $this->put(route('admin.operating-hours.defaults'), ['opens_at' => '16:00', 'closes_at' => '23:00'])->assertSessionHasErrors('opens_at');
        $this->assertDatabaseCount('operating_hour_rules', 0);
        $this->assertSame('13:00', ApplicationSetting::current()->opensAt());

        $this->get(route('admin.operating-hours.index'))->assertOk()
            ->assertSee('This change leaves 1 confirmed appointment outside opening hours. Move or cancel it first.')
            ->assertSee('Oct 5, 3:00 PM · '.$customer->display_name)
            ->assertSee(route('admin.appointments.show', $appointment), false);

        // Changes that keep the appointment inside opening hours are saved.
        $this->post(route('admin.operating-hours.rules.store'), $this->timed('partial_closure', '2026-10-05', '17:00', '18:00'))->assertSessionHasNoErrors();
        $this->post(route('admin.operating-hours.rules.store'), $this->closure('2026-10-06'))->assertSessionHasNoErrors();

        // Removing special hours that a booking relies on is refused too.
        $special = OperatingHourRule::query()->create(['type' => 'special_hours', 'starts_on' => '2026-10-09', 'ends_on' => '2026-10-09', 'start_time' => '10:00', 'end_time' => '23:00', 'reason' => 'Early opening']);
        StaffWeeklySchedule::query()->update(['start_time' => '10:00:00']);
        $this->confirmedAt($customer, '2026-10-09 10:00');
        $this->delete(route('admin.operating-hours.rules.destroy', $special))->assertSessionHasErrors('rules');
        $this->assertModelExists($special);
    }

    /** @return array<string, string> */
    private function closure(string $date, string $reason = 'Holiday'): array
    {
        return ['type' => 'closed', 'starts_on' => $date, 'end_mode' => 'same_day', 'reason' => $reason];
    }

    /** @return array<string, string> */
    private function timed(string $type, string $date, string $start, string $end, string $reason = 'Event'): array
    {
        return ['type' => $type, 'starts_on' => $date, 'end_mode' => 'same_day', 'start_time' => $start, 'end_time' => $end, 'reason' => $reason];
    }

    private function availability(string $month)
    {
        return $this->getJson(route('admin.appointments.availability', ['service_id' => $this->service->id, 'month' => $month]))->assertOk();
    }

    /** @return array{0: string, 1: string} First and last bookable start on a date. */
    private function slotTimes(string $date, bool $asCustomer = false): array
    {
        if ($asCustomer) {
            $this->actingAs(User::factory()->customer()->create());
            $times = collect($this->getJson(route('customer.appointments.availability', ['service_id' => $this->service->id, 'month' => substr($date, 0, 7)]))->assertOk()->json('dates.'.$date))->pluck('time');
            $this->actingAs($this->admin);
        } else {
            $times = collect($this->availability(substr($date, 0, 7))->json('dates.'.$date))->pluck('time');
        }

        return [$times->first(), $times->last()];
    }

    private function confirmedAt(CustomerProfile $customer, string $start): Appointment
    {
        $start = Carbon::parse($start, 'Asia/Manila');

        return Appointment::factory()->create([
            'customer_profile_id' => $customer->id, 'service_id' => $this->service->id, 'staff_profile_id' => $this->staff->id,
            'status' => Appointment::STATUS_CONFIRMED, 'requested_start_at' => $start, 'scheduled_start_at' => $start, 'scheduled_end_at' => $start->copy()->addHour(),
        ]);
    }
}
