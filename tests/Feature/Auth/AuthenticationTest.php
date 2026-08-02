<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_offers_password_and_google_sign_in(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee('By continuing, you agree to Casa Paraiso’s transparent data practices.')
            ->assertSee('href="'.route('privacy-policy').'"', false)
            ->assertSee('Read the Security &amp; Privacy Policy', false)
            ->assertSee('name="password"', false)
            ->assertSee('Show password')
            ->assertSee('x-bind:type=', false);
    }

    public function test_privacy_policy_is_public_and_describes_current_data_practices(): void
    {
        $this->get(route('privacy-policy'))
            ->assertOk()
            ->assertSee('Security &amp; Privacy Policy', false)
            ->assertSee('Information we collect')
            ->assertSee('How we use information')
            ->assertSee('Who can access it')
            ->assertSee('Security and your choices')
            ->assertSee('Questions');
    }

    public function test_active_user_can_sign_in_with_a_password(): void
    {
        $user = User::factory()->customer()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('customer.appointments.index', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_sign_in_with_a_password(): void
    {
        $user = User::factory()->customer()->create(['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unknown_verified_google_user_becomes_customer(): void
    {
        $this->mockGoogle('google-1', 'New Guest', 'Guest@Example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('customer.appointments.index', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'guest@example.com', 'role' => User::ROLE_CUSTOMER, 'google_id' => 'google-1']);
        $this->assertDatabaseCount('customer_profiles', 1);
    }

    public function test_designated_email_becomes_the_protected_super_admin(): void
    {
        config(['auth.super_admin_email' => 'ehrinjohn08@gmail.com']);
        $this->mockGoogle('google-root', 'Ehrin John', 'EHRINJOHN08@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertDatabaseHas('users', ['email' => 'ehrinjohn08@gmail.com', 'role' => User::ROLE_SUPER_ADMIN]);
    }

    public function test_preprovisioned_role_is_preserved_and_linked(): void
    {
        $user = User::factory()->staff()->create(['email' => 'staff@example.com', 'google_id' => null]);
        $this->mockGoogle('staff-google', 'Staff Name', 'staff@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('staff.dashboard', absolute: false));
        $this->assertSame(User::ROLE_STAFF, $user->fresh()->role);
        $this->assertSame('staff-google', $user->fresh()->google_id);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->mockGoogle('google-2', 'Guest', 'guest@example.com', false);
        $this->get('/auth/google/callback')->assertRedirect(route('login', absolute: false))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_preauthorized_team_user_can_request_a_password_setup_link(): void
    {
        Notification::fake();
        $staff = User::factory()->staff()->create(['password' => null, 'google_id' => null]);

        $this->post('/forgot-password', ['email' => $staff->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($staff, ResetPassword::class);
    }

    public function test_password_reset_request_does_not_reveal_whether_an_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->post('/forgot-password', ['email' => $user->email]);
        $unknown = $this->post('/forgot-password', ['email' => 'unknown@example.com']);

        $known->assertSessionHas('status');
        $unknown->assertSessionHas('status');
        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
        $unknown->assertSessionDoesntHaveErrors('email');
    }

    public function test_password_reset_rotates_credentials_and_revokes_database_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['remember_token' => 'old-token']);
        DB::table('sessions')->insert([
            'id' => 'existing-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('login', absolute: false));

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
        $this->assertNotSame('old-token', $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'existing-session']);
    }

    public function test_password_change_rotates_the_remember_token(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-token']);

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHas('status', 'password-updated');

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
        $this->assertNotSame('old-token', $user->remember_token);
    }

    private function mockGoogle(string $id, string $name, string $email, bool $verified = true): void
    {
        $googleUser = new GoogleUser;
        $googleUser->id = $id;
        $googleUser->name = $name;
        $googleUser->email = $email;
        $googleUser->user = ['verified_email' => $verified];

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }
}
