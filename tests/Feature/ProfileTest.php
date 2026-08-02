<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_email_is_not_editable_but_name_and_phone_are(): void
    {
        $user = User::factory()->customer()->create(['email' => 'linked@example.com', 'google_id' => 'google-1']);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Updated Name',
            'phone' => '09171234567',
            'email' => 'ignored@example.com',
        ])->assertRedirect('/profile');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name', 'phone' => '09171234567', 'email' => 'linked@example.com']);
    }

    public function test_privileged_users_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create(['google_id' => 'google-admin']);
        $this->actingAs($admin)->withSession(['google_reauthenticated_for_deletion' => $admin->id])->delete('/profile')->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_customer_deletion_requires_google_reauthentication_marker(): void
    {
        $customer = User::factory()->customer()->create(['google_id' => 'google-customer', 'password' => null]);
        $this->actingAs($customer)->delete('/profile')->assertForbidden();
    }

    public function test_password_customer_can_confirm_password_to_delete_account(): void
    {
        $customer = User::factory()->customer()->create(['google_id' => null]);

        $this->actingAs($customer)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'is_active' => false, 'password' => null]);
    }

    public function test_password_deletion_confirmation_is_rate_limited(): void
    {
        $customer = User::factory()->customer()->create();

        foreach (range(1, 5) as $attempt) {
            $this->actingAs($customer)
                ->delete('/profile', ['password' => 'wrong-password'])
                ->assertSessionHasErrors('password');
        }

        $this->actingAs($customer)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrors(['password' => 'Too many confirmation attempts. Please wait before trying again.']);

        $this->assertDatabaseHas('users', ['id' => $customer->id, 'is_active' => true]);
    }
}
