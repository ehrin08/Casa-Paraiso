<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_with_email_and_password(): void
    {
        Notification::fake();

        $this->get('/register')->assertOk();
        $response = $this->post('/register', [
            'name' => 'New Customer',
            'email' => 'CUSTOMER@example.com',
            'phone' => '09171234567',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);

        $response->assertRedirect(route('verification.notice', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'role' => User::ROLE_CUSTOMER,
            'email_verified_at' => null,
        ]);
        $user = User::where('email', 'customer@example.com')->firstOrFail();
        $this->assertDatabaseHas('customer_profiles', ['user_id' => $user->id]);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get(route('customer.appointments.index', absolute: false))
            ->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_registration_rejects_an_existing_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->post('/register', [
            'name' => 'Duplicate',
            'email' => 'EXISTING@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');
    }
}
