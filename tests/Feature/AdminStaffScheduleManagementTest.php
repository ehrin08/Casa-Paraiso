<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_access_staff_schedule_management(): void
    {
        $admin = User::factory()->admin()->create();
        $staffUser = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        $staffProfile = StaffProfile::factory()->create();
        $weeklySchedule = StaffWeeklySchedule::factory()->for($staffProfile)->create();
        $scheduleException = StaffScheduleException::factory()->for($staffProfile)->create();

        foreach ([
            ['GET', route('admin.staff.weekly-schedules.create', $staffProfile, false)],
            ['GET', route('admin.staff.weekly-schedules.edit', [$staffProfile, $weeklySchedule], false)],
            ['POST', route('admin.staff.weekly-schedules.store', $staffProfile, false)],
            ['PATCH', route('admin.staff.weekly-schedules.update', [$staffProfile, $weeklySchedule], false)],
            ['GET', route('admin.staff.schedule-exceptions.create', $staffProfile, false)],
            ['GET', route('admin.staff.schedule-exceptions.edit', [$staffProfile, $scheduleException], false)],
            ['POST', route('admin.staff.schedule-exceptions.store', $staffProfile, false)],
            ['PATCH', route('admin.staff.schedule-exceptions.update', [$staffProfile, $scheduleException], false)],
        ] as [$method, $uri]) {
            $this->actingAs($admin)->call($method, $uri)->assertStatus($method === 'GET' ? 200 : 302);
            $this->actingAs($staffUser)->call($method, $uri)->assertForbidden();
            $this->actingAs($customer)->call($method, $uri)->assertForbidden();
        }

        $this->actingAs($staffUser)
            ->delete(route('admin.staff.weekly-schedules.destroy', [$staffProfile, $weeklySchedule], false))
            ->assertForbidden();

        $this->actingAs($customer)
            ->delete(route('admin.staff.schedule-exceptions.destroy', [$staffProfile, $scheduleException], false))
            ->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete_weekly_schedule(): void
    {
        $admin = User::factory()->admin()->create();
        $staffProfile = StaffProfile::factory()->create();

        $this->actingAs($admin)->post(route('admin.staff.weekly-schedules.store', $staffProfile, false), [
            'day_of_week' => StaffWeeklySchedule::MONDAY,
            'start_time' => '13:00',
            'end_time' => '00:00',
            'ends_next_day' => '1',
            'is_available' => '1',
        ])->assertRedirect(route('admin.staff.show', $staffProfile, false));

        $weeklySchedule = StaffWeeklySchedule::query()
            ->where('staff_profile_id', $staffProfile->id)
            ->where('day_of_week', StaffWeeklySchedule::MONDAY)
            ->firstOrFail();

        $this->assertDatabaseHas('staff_weekly_schedules', [
            'id' => $weeklySchedule->id,
            'start_time' => '13:00:00',
            'end_time' => '00:00:00',
            'ends_next_day' => true,
            'is_available' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.staff.weekly-schedules.update', [$staffProfile, $weeklySchedule], false), [
            'day_of_week' => StaffWeeklySchedule::TUESDAY,
            'start_time' => '14:00',
            'end_time' => '18:00',
            'ends_next_day' => '0',
            'is_available' => '0',
        ])->assertRedirect(route('admin.staff.show', $staffProfile, false));

        $this->assertDatabaseHas('staff_weekly_schedules', [
            'id' => $weeklySchedule->id,
            'day_of_week' => StaffWeeklySchedule::TUESDAY,
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
            'ends_next_day' => false,
            'is_available' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.staff.weekly-schedules.destroy', [$staffProfile, $weeklySchedule], false))
            ->assertRedirect(route('admin.staff.show', $staffProfile, false));

        $this->assertDatabaseMissing('staff_weekly_schedules', [
            'id' => $weeklySchedule->id,
        ]);
    }

    public function test_weekly_schedule_rejects_overlapping_shifts_for_same_staff_and_day(): void
    {
        $admin = User::factory()->admin()->create();
        $staffProfile = StaffProfile::factory()->create();

        StaffWeeklySchedule::factory()->for($staffProfile)->create([
            'day_of_week' => StaffWeeklySchedule::WEDNESDAY,
            'start_time' => '13:00',
            'end_time' => '16:00',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.staff.weekly-schedules.create', $staffProfile, false))
            ->post(route('admin.staff.weekly-schedules.store', $staffProfile, false), [
                'day_of_week' => StaffWeeklySchedule::WEDNESDAY,
                'start_time' => '15:00',
                'end_time' => '18:00',
                'is_available' => '1',
            ])
            ->assertRedirect(route('admin.staff.weekly-schedules.create', $staffProfile, false))
            ->assertSessionHasErrors('start_time');
    }

    public function test_admin_can_create_update_and_delete_schedule_exception(): void
    {
        $admin = User::factory()->admin()->create();
        $staffProfile = StaffProfile::factory()->create();

        $this->actingAs($admin)->post(route('admin.staff.schedule-exceptions.store', $staffProfile, false), [
            'exception_date' => now()->addWeek()->toDateString(),
            'exception_type' => StaffScheduleException::TYPE_UNAVAILABLE,
            'reason' => 'Staff leave',
        ])->assertRedirect(route('admin.staff.show', $staffProfile, false));

        $scheduleException = StaffScheduleException::query()
            ->where('staff_profile_id', $staffProfile->id)
            ->firstOrFail();

        $this->assertDatabaseHas('staff_schedule_exceptions', [
            'id' => $scheduleException->id,
            'exception_type' => StaffScheduleException::TYPE_UNAVAILABLE,
            'start_time' => null,
            'end_time' => null,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patch(route('admin.staff.schedule-exceptions.update', [$staffProfile, $scheduleException], false), [
            'exception_date' => now()->addWeeks(2)->toDateString(),
            'exception_type' => StaffScheduleException::TYPE_AVAILABLE,
            'start_time' => '13:00',
            'end_time' => '17:00',
            'reason' => 'Special opening',
        ])->assertRedirect(route('admin.staff.show', $staffProfile, false));

        $this->assertDatabaseHas('staff_schedule_exceptions', [
            'id' => $scheduleException->id,
            'exception_type' => StaffScheduleException::TYPE_AVAILABLE,
            'start_time' => '13:00:00',
            'end_time' => '17:00:00',
            'reason' => 'Special opening',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.staff.schedule-exceptions.destroy', [$staffProfile, $scheduleException], false))
            ->assertRedirect(route('admin.staff.show', $staffProfile, false));

        $this->assertDatabaseMissing('staff_schedule_exceptions', [
            'id' => $scheduleException->id,
        ]);
    }

    public function test_schedule_exception_time_rules_are_validated(): void
    {
        $admin = User::factory()->admin()->create();
        $staffProfile = StaffProfile::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.staff.schedule-exceptions.create', $staffProfile, false))
            ->post(route('admin.staff.schedule-exceptions.store', $staffProfile, false), [
                'exception_date' => now()->addWeek()->toDateString(),
                'exception_type' => StaffScheduleException::TYPE_AVAILABLE,
                'reason' => 'Needs times',
            ])
            ->assertRedirect(route('admin.staff.schedule-exceptions.create', $staffProfile, false))
            ->assertSessionHasErrors('start_time');

        $this->actingAs($admin)
            ->from(route('admin.staff.schedule-exceptions.create', $staffProfile, false))
            ->post(route('admin.staff.schedule-exceptions.store', $staffProfile, false), [
                'exception_date' => now()->addWeek()->toDateString(),
                'exception_type' => StaffScheduleException::TYPE_UNAVAILABLE,
                'start_time' => '16:00',
                'end_time' => '12:00',
                'reason' => 'Invalid partial block',
            ])
            ->assertRedirect(route('admin.staff.schedule-exceptions.create', $staffProfile, false))
            ->assertSessionHasErrors('end_time');
    }

    public function test_staff_detail_renders_weekly_schedule_and_exceptions(): void
    {
        $admin = User::factory()->admin()->create();
        $staffUser = User::factory()->staff()->create(['name' => 'Schedule Staff']);
        $staffProfile = StaffProfile::factory()->for($staffUser)->create();

        StaffWeeklySchedule::factory()->for($staffProfile)->create([
            'day_of_week' => StaffWeeklySchedule::FRIDAY,
            'start_time' => '13:00',
            'end_time' => '21:00',
        ]);

        StaffScheduleException::factory()->for($staffProfile)->for($admin, 'creator')->create([
            'exception_date' => now()->addWeek()->toDateString(),
            'exception_type' => StaffScheduleException::TYPE_UNAVAILABLE,
            'reason' => 'Workshop day',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.staff.show', $staffProfile, false))
            ->assertOk()
            ->assertSee('Schedule Staff')
            ->assertSee('Weekly staff schedule')
            ->assertDontSee('Recurring availability')
            ->assertSee('Workshop day')
            ->assertSee('Full day');
    }
}
