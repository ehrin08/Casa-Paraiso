<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_manage_user_access(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/users')->assertForbidden();

        $superAdmin = User::factory()->create([
            'email' => config('auth.super_admin_email'),
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $this->actingAs($superAdmin)->get('/admin/users')->assertOk();
    }

    public function test_super_admin_can_preprovision_staff_without_password(): void
    {
        $superAdmin = User::factory()->create(['email' => config('auth.super_admin_email'), 'role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)->post('/admin/users', [
            'name' => 'New Therapist',
            'email' => 'therapist@example.com',
            'role' => User::ROLE_STAFF,
            'is_active' => '1',
        ])->assertRedirect();

        $staff = User::where('email', 'therapist@example.com')->firstOrFail();
        $this->assertNull($staff->password);
        $this->assertNotNull($staff->staffProfile);
    }

    public function test_protected_super_admin_cannot_be_updated(): void
    {
        $superAdmin = User::factory()->create(['email' => config('auth.super_admin_email'), 'role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($superAdmin)->put('/admin/users/'.$superAdmin->id, [
            'name' => 'Changed', 'email' => 'changed@example.com', 'role' => User::ROLE_ADMIN, 'is_active' => '0',
        ])->assertForbidden();
    }
}
