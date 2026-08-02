<?php

namespace Tests\Feature;

use App\Models\{StaffProfile, User};
use App\Services\AttendanceQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_only_see_their_web_attendance_workspaces(): void
    {
        $staff = StaffProfile::factory()->create(); $admin = User::factory()->admin()->create(); $receptionist = User::factory()->receptionist()->create();
        $this->actingAs($staff->user)
            ->get(route('staff.attendance.show'))
            ->assertOk()
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
        $this->actingAs($staff->user)->get(route('admin.attendance.index'))->assertForbidden();
        $this->actingAs($receptionist)->get(route('reception.attendance.station'))->assertOk();
        $this->actingAs($receptionist)->getJson('/attendance-station/pending')->assertNotFound();
        $this->actingAs($receptionist)->get(route('admin.attendance.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.attendance.index'))->assertOk();
    }

    public function test_therapist_web_scan_is_verified_immediately(): void
    {
        $staff = StaffProfile::factory()->create();
        $payload = app(AttendanceQr::class)->current()['payload'];
        $this->actingAs($staff->user)
            ->postJson(route('staff.attendance.scan'), ['payload' => $payload])
            ->assertCreated()
            ->assertJsonPath('message', 'Attendance time in recorded automatically.');

        $this->assertDatabaseHas('staff_attendances', ['staff_profile_id' => $staff->id]);
        $this->assertDatabaseHas('staff_attendance_scan_requests', ['staff_profile_id' => $staff->id, 'status' => 'confirmed', 'resolution' => 'time_in']);
    }
}
