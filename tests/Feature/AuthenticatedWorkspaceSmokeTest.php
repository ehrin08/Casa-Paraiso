<?php

namespace Tests\Feature;

use App\Models\CustomerProfile;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedWorkspaceSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_representative_pages_render_for_all_authenticated_workspaces(): void
    {
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->receptionist()->create();
        $therapist = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();

        StaffProfile::factory()->for($therapist, 'user')->create();
        CustomerProfile::factory()->for($customer, 'user')->create();

        $workspaces = [
            [$admin, 'admin', [
                route('admin.dashboard'),
                route('admin.appointments.index'),
                route('admin.transactions.index'),
                route('admin.commissions.index'),
                route('admin.settings.index'),
            ]],
            [$receptionist, 'receptionist', [
                route('reception.dashboard'),
                route('reception.appointments.index'),
                route('reception.customers.index'),
                route('reception.transactions.index'),
            ]],
            [$therapist, 'staff', [
                route('staff.dashboard'),
                route('staff.appointments.index'),
                route('staff.customers.index'),
                route('staff.transactions.index'),
                route('staff.commissions.index'),
            ]],
            [$customer, 'customer', [
                route('customer.appointments.index'),
                route('customer.feedback.index'),
                route('profile.edit'),
            ]],
        ];

        foreach ($workspaces as [$user, $role, $urls]) {
            foreach ($urls as $url) {
                $this->actingAs($user)
                    ->get($url)
                    ->assertOk()
                    ->assertSee('data-workspace-role="'.$role.'"', false);
            }
        }
    }
}
