<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_only_admin_area(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $this->actingAs($admin)->get('/staff/dashboard')->assertForbidden();
        $this->actingAs($admin)->get('/customer/appointments')->assertForbidden();
    }

    public function test_staff_can_access_only_staff_area(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/staff/dashboard')->assertOk();
        $this->actingAs($staff)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($staff)->get('/customer/appointments')->assertForbidden();
    }

    public function test_customer_can_access_only_customer_area(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get('/customer/appointments')->assertOk();
        $this->actingAs($customer)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($customer)->get('/staff/dashboard')->assertForbidden();
    }

    public function test_generic_dashboard_redirects_to_role_home(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('admin.dashboard', absolute: false));
        $this->actingAs($staff)->get('/dashboard')->assertRedirect(route('staff.dashboard', absolute: false));
        $this->actingAs($customer)->get('/dashboard')->assertRedirect(route('customer.appointments.index', absolute: false));
    }

    public function test_inactive_authenticated_users_are_logged_out_on_protected_routes(): void
    {
        $customer = User::factory()->customer()->inactive()->create();

        $this->actingAs($customer)
            ->get('/customer/appointments')
            ->assertRedirect(route('login', absolute: false));

        $this->assertGuest();
    }
}
