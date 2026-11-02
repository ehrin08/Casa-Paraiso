<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveRegionMarkupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 16:00:00', 'Asia/Manila'));
    }

    public function test_admin_queue_refreshes_live_and_its_saves_use_turbo(): void
    {
        $start = CarbonImmutable::parse('2026-10-01 14:00:00', 'Asia/Manila');
        Appointment::factory()->create(['status' => Appointment::STATUS_CONFIRMED, 'scheduled_start_at' => $start, 'scheduled_end_at' => $start->addHour()]);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.appointments.index'))->assertOk()
            ->assertSee('<meta name="casa-live-state-url" content="/live/state">', false)
            ->assertSee('<meta name="turbo-refresh-scroll" content="preserve">', false)
            ->assertDontSee('turbo-refresh-method', false)
            ->assertSee('data-live-region="appointments-summary" data-live-scope="appointments"', false)
            ->assertSee('data-live-region="service-queue" data-live-scope="appointments"', false)
            ->assertSee('x-bind:data-calendar-mode="mode"', false)
            ->assertSee('/complete" data-turbo="true"', false)
            ->assertSee('/outcome" data-turbo="true"', false);
    }

    public function test_reception_summary_and_notifications_refresh_live(): void
    {
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)->get(route('reception.appointments.index'))->assertOk()
            ->assertSee('data-live-region="appointments-summary" data-live-scope="appointments"', false);

        $this->actingAs($receptionist)->get(route('notifications.index'))->assertOk()
            ->assertSee('data-live-region="notifications-tabs" data-live-scope="notifications"', false)
            ->assertSee('data-live-region="notifications-list" data-live-scope="notifications"', false)
            ->assertSee('<meta name="turbo-refresh-scroll" content="preserve">', false);
    }

    public function test_attendance_pages_mark_their_live_regions(): void
    {
        $staff = StaffProfile::factory()->create();
        $record = StaffAttendance::create([
            'staff_profile_id' => $staff->id, 'attendance_date' => '2026-10-01', 'time_in_at' => '2026-10-01 13:00:00',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.attendance.index', ['selected' => $record->id]))->assertOk()
            ->assertSee('data-live-region="attendance-records" data-live-scope="attendance"', false)
            ->assertSee('/correct" data-turbo="true"', false);

        $this->actingAs($staff->user)->get(route('staff.attendance.show'))->assertOk()
            ->assertSee('data-live-region="attendance-status"', false)
            ->assertDontSee('data-live-scope', false);
    }

    public function test_guests_get_no_live_state_url(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('casa-live-state-url', false);
    }
}
