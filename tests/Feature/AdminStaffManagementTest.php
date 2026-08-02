<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_admin_can_view_and_edit_operational_staff_details_but_cannot_create_access(): void
    {
        $admin = User::factory()->admin()->create();
        $staffProfile = StaffProfile::factory()->create();

        $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.staff.edit', $staffProfile))->assertOk();
        $this->actingAs($admin)->get(route('admin.staff.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.staff.store'), [])->assertForbidden();
    }

    public function test_super_admin_can_preauthorize_staff_without_a_password(): void
    {
        $superAdmin = User::factory()->create(['email' => config('auth.super_admin_email'), 'role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)->post(route('admin.staff.store'), [
            'name' => 'Google Therapist',
            'email' => 'therapist@example.com',
            'phone' => null,
            'is_active' => '1',
            'position' => 'Therapist',
            'hire_date' => now()->toDateString(),
            'is_bookable' => '1',
            'service_ids' => [],
        ])->assertRedirect();

        $user = User::where('email', 'therapist@example.com')->firstOrFail();
        $this->assertNull($user->password);
        $this->assertSame(User::ROLE_STAFF, $user->role);
        $this->assertNotNull($user->staffProfile);
    }

    public function test_admin_can_update_staff_operations_without_changing_google_email(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create();
        $staffProfile = StaffProfile::factory()->create();
        $originalEmail = $staffProfile->user->email;

        $this->actingAs($admin)->patch(route('admin.staff.update', $staffProfile), [
            'name' => 'Updated Therapist',
            'email' => 'attempted-change@example.com',
            'phone' => '09171234567',
            'is_active' => '1',
            'position' => 'Senior Therapist',
            'specialization' => 'Massage',
            'hire_date' => now()->toDateString(),
            'is_bookable' => '1',
            'service_ids' => [$service->id],
        ])->assertRedirect(route('admin.staff.show', $staffProfile));

        $this->assertSame($originalEmail, $staffProfile->user->fresh()->email);
        $this->assertSame('Senior Therapist', $staffProfile->fresh()->position);
        $this->assertTrue($staffProfile->services()->whereKey($service)->exists());
    }
}
