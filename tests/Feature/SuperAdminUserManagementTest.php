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

    public function test_account_filters_and_pagination_preserve_context_but_reset_selection(): void
    {
        $superAdmin = User::factory()->create(['email' => config('auth.super_admin_email'), 'role' => User::ROLE_SUPER_ADMIN]);
        $matches = User::factory()->count(16)->create(['role' => User::ROLE_STAFF, 'is_active' => false, 'name' => 'Matched Therapist']);
        User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => true, 'name' => 'Matched Active']);
        User::factory()->admin()->create(['is_active' => false, 'name' => 'Matched Admin']);
        User::factory()->create(['role' => User::ROLE_STAFF, 'is_active' => false, 'name' => 'Unrelated Therapist']);

        $this->actingAs($superAdmin)->get(route('admin.users.index', [
            'q' => 'Matched', 'role' => 'staff', 'status' => 'inactive', 'selected' => $matches->first()->id,
        ]))->assertOk()
            ->assertViewHas('users', fn ($users) => $users->total() === 16 && $users->count() === 15)
            ->assertViewHas('selectedUser', fn ($user) => $user->is($matches->first()))
            ->assertSee('Edit user access')
            ->assertSee('q=Matched&amp;role=staff&amp;status=inactive&amp;page=2', false)
            ->assertDontSee('Matched Active')->assertDontSee('Matched Admin')->assertDontSee('Unrelated Therapist');

        $this->get(route('admin.users.index', ['q' => $matches->last()->email]))
            ->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 1);
        $this->get(route('admin.users.index', ['selected' => $matches->last()->id]))
            ->assertOk()->assertViewHas('selectedUser', null);
        $this->get(route('admin.users.index', ['selected' => $superAdmin->id]))
            ->assertOk()->assertViewHas('selectedUser', null)->assertSee('Protected');
        $this->get('/admin/users?q=missing-account')->assertOk()->assertSee('No user accounts found');
    }

    public function test_invalid_account_filters_are_rejected(): void
    {
        $superAdmin = User::factory()->create(['email' => config('auth.super_admin_email'), 'role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($superAdmin)->get('/admin/users?role=owner&status=deleted&selected=0&page=0')
            ->assertSessionHasErrors(['role', 'status', 'selected', 'page']);
    }

    public function test_edit_validation_keeps_entered_values_in_the_selected_form(): void
    {
        $superAdmin = User::factory()->create(['email' => config('auth.super_admin_email'), 'role' => User::ROLE_SUPER_ADMIN]);
        $managed = User::factory()->create(['role' => User::ROLE_RECEPTIONIST, 'google_id' => 'linked-account']);
        $url = route('admin.users.index', ['selected' => $managed->id], absolute: false);

        $this->actingAs($superAdmin)->from($url)->put(route('admin.users.update', $managed), [
            '_user_form' => 'edit', '_user_id' => $managed->id,
            'name' => 'Entered name', 'email' => 'invalid-email', 'role' => 'receptionist', 'is_active' => '0',
        ])->assertRedirect($url)->assertSessionHasErrors('email');

        $this->get($url)->assertOk()->assertSee('value="Entered name"', false)
            ->assertSee('The account could not be saved.')->assertSee('readonly', false)
            ->assertSee('Linked emails are updated only through Google sign-in.');
        $this->assertSame($managed->name, $managed->fresh()->name);
    }
}
