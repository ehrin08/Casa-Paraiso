<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ClosedDate;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use App\Services\ScheduleWindowResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClosedDatesTest extends TestCase
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

    public function test_admin_can_close_and_reopen_a_date(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk()
            ->assertSee(route('admin.closed-dates.index'), false);

        $this->post(route('admin.closed-dates.store'), ['closed_on' => '2026-11-01', 'reason' => 'All Saints Day'])
            ->assertRedirect(route('admin.closed-dates.index'))
            ->assertSessionHas('status', 'closed-date-created');

        $closed = ClosedDate::query()->sole();
        $this->assertSame(['2026-11-01', 'All Saints Day', $this->admin->id], [$closed->closed_on->toDateString(), $closed->reason, $closed->created_by]);

        $this->get(route('admin.closed-dates.index'))->assertOk()
            ->assertSee('Sun, Nov 1, 2026')->assertSee('All Saints Day')->assertSee($this->admin->name);

        $this->delete(route('admin.closed-dates.destroy', $closed))->assertSessionHas('status', 'closed-date-deleted');
        $this->assertModelMissing($closed);
    }

    public function test_only_admins_manage_closed_dates(): void
    {
        foreach ([User::factory()->receptionist()->create(), User::factory()->staff()->create(), User::factory()->customer()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.closed-dates.index'))->assertForbidden();
            $this->post(route('admin.closed-dates.store'), ['closed_on' => '2026-11-01', 'reason' => 'Holiday'])->assertForbidden();
        }

        $this->assertDatabaseCount('closed_dates', 0);
    }

    public function test_past_duplicate_and_blank_entries_are_rejected(): void
    {
        ClosedDate::query()->create(['closed_on' => '2026-11-01', 'reason' => 'All Saints Day']);

        $this->actingAs($this->admin)->from(route('admin.closed-dates.index'))
            ->post(route('admin.closed-dates.store'), ['closed_on' => '2026-10-03', 'reason' => 'Too late'])
            ->assertSessionHasErrors('closed_on');
        $this->post(route('admin.closed-dates.store'), ['closed_on' => '2026-11-01', 'reason' => 'Again'])
            ->assertSessionHasErrors('closed_on');
        $this->post(route('admin.closed-dates.store'), ['closed_on' => '2026-11-02', 'reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('closed_dates', 1);
    }

    public function test_a_closed_date_has_no_times_for_customers_or_operations(): void
    {
        ClosedDate::query()->create(['closed_on' => '2026-10-05', 'reason' => 'Staff retreat']);
        $params = ['service_id' => $this->service->id, 'month' => '2026-10'];

        $this->actingAs(User::factory()->customer()->create());
        $customer = $this->getJson(route('customer.appointments.availability', $params))->assertOk();
        $this->assertNull($customer->json('dates.2026-10-05'));
        $this->assertNull($customer->json('store_dates.2026-10-05'));
        $this->assertSame(['2026-10-05' => 'Staff retreat'], $customer->json('closed_dates'));
        $this->assertNotEmpty($customer->json('dates.2026-10-06'));

        $this->actingAs($this->admin);
        $operations = $this->getJson(route('admin.appointments.availability', $params))->assertOk();
        $this->assertNull($operations->json('store_dates.2026-10-05'));
        $this->assertNotNull($operations->json('store_dates.2026-10-06'));

        $resolver = app(ScheduleWindowResolver::class);
        $this->assertSame('Staff retreat', $resolver->closedReason(Carbon::parse('2026-10-05', 'Asia/Manila')));
        $this->assertTrue($resolver->effectiveWindows($this->staff, Carbon::parse('2026-10-05', 'Asia/Manila'))->isEmpty());
        $this->assertTrue($resolver->effectiveWindows($this->staff, Carbon::parse('2026-10-06', 'Asia/Manila'))->isNotEmpty());
    }

    public function test_bookings_cannot_land_on_a_closed_date(): void
    {
        ClosedDate::query()->create(['closed_on' => '2026-10-05', 'reason' => 'Staff retreat']);
        $customer = CustomerProfile::factory()->create();

        $this->actingAs($customer->user)
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $this->service->id,
                'requested_start_at' => '2026-10-05 15:00:00',
            ])
            ->assertSessionHasErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.appointments.store', absolute: false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $this->service->id,
                'staff_profile_id' => $this->staff->id,
                'scheduled_start_at' => '2026-10-05 15:00:00',
                'status' => Appointment::STATUS_CONFIRMED,
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('appointments', 0);

        $this->post(route('admin.appointments.store', absolute: false), [
            'customer_profile_id' => $customer->id,
            'service_id' => $this->service->id,
            'staff_profile_id' => $this->staff->id,
            'scheduled_start_at' => '2026-10-06 15:00:00',
            'status' => Appointment::STATUS_CONFIRMED,
        ])->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_a_date_with_confirmed_appointments_cannot_be_closed(): void
    {
        $customer = CustomerProfile::factory()->create();
        $start = Carbon::parse('2026-10-05 15:00', 'Asia/Manila');
        $appointment = Appointment::factory()->create([
            'customer_profile_id' => $customer->id, 'service_id' => $this->service->id, 'staff_profile_id' => $this->staff->id,
            'status' => Appointment::STATUS_CONFIRMED, 'requested_start_at' => $start, 'scheduled_start_at' => $start, 'scheduled_end_at' => $start->copy()->addHour(),
        ]);
        // Cancelled visits and other dates do not block closing.
        Appointment::factory()->create([
            'customer_profile_id' => $customer->id, 'service_id' => $this->service->id, 'staff_profile_id' => $this->staff->id,
            'status' => Appointment::STATUS_CANCELLED, 'requested_start_at' => $start->copy()->addDay(), 'scheduled_start_at' => $start->copy()->addDay(), 'scheduled_end_at' => $start->copy()->addDay()->addHour(),
        ]);

        $this->actingAs($this->admin)->from(route('admin.closed-dates.index'))
            ->post(route('admin.closed-dates.store'), ['closed_on' => '2026-10-05', 'reason' => 'Staff retreat'])
            ->assertRedirect(route('admin.closed-dates.index'))
            ->assertSessionHasErrors('closed_on');
        $this->assertDatabaseCount('closed_dates', 0);

        $this->get(route('admin.closed-dates.index'))->assertOk()
            ->assertSee('This date has 1 confirmed appointment. Move or cancel it first.')
            ->assertSee('3:00 PM · '.$customer->display_name)
            ->assertSee(route('admin.appointments.show', $appointment), false);

        $this->post(route('admin.closed-dates.store'), ['closed_on' => '2026-10-06', 'reason' => 'Holiday'])
            ->assertSessionHas('status', 'closed-date-created');
    }
}
