<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\StaffScheduleWeek;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_copy_a_legacy_schedule_into_a_draft_and_publish_it(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = StaffProfile::factory()->create();
        $week = now()->startOfWeek(CarbonInterface::SUNDAY);

        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $week->dayOfWeek,
            'start_time' => '13:00',
            'end_time' => '18:00',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.staff-roster.copy', absolute: false), ['week' => $week->toDateString()])
            ->assertOk()
            ->assertJsonPath('has_draft', true)
            ->assertJsonCount(1, 'draft_shifts');

        $roster = StaffScheduleWeek::query()->sole();

        $this->actingAs($admin)
            ->postJson(route('admin.staff-roster.publish', $roster, false))
            ->assertOk()
            ->assertJsonPath('has_draft', true)
            ->assertJsonPath('published_at', fn ($value) => $value !== null);

        $this->assertNotNull($roster->fresh()->published_at);
    }
}
