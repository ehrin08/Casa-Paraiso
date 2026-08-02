<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class PasswordSetupTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION_KEY = 'google_reauthenticated_for_password_setup';

    public function test_passwordless_google_accounts_across_all_roles_see_google_password_setup(): void
    {
        foreach (User::ROLES as $index => $role) {
            $user = User::factory()->create([
                'email' => "passwordless-{$index}@example.com",
                'role' => $role,
                'google_id' => "google-{$index}",
                'password' => null,
            ]);

            $this->actingAs($user)
                ->get('/profile')
                ->assertOk()
                ->assertSee('Set a Password')
                ->assertSee('Confirm with Google')
                ->assertDontSee('Current Password');
        }
    }

    public function test_passwordless_account_without_google_uses_the_reset_link_fallback(): void
    {
        $user = User::factory()->staff()->create([
            'google_id' => null,
            'password' => null,
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Email a Password Setup Link')
            ->assertSee(route('password.request', absolute: false), false);
    }

    public function test_initial_password_submission_requires_google_confirmation(): void
    {
        $user = $this->googleOnlyUser();

        $this->actingAs($user)->put('/password', [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('password', null, 'updatePassword');

        $this->assertNull($user->fresh()->password);
    }

    public function test_matching_google_callback_stores_a_user_bound_confirmation(): void
    {
        $user = $this->googleOnlyUser();
        $this->mockGoogleCallback('google-password-setup');

        $this->actingAs($user)
            ->get(route('profile.password.google.callback', absolute: false))
            ->assertRedirect(route('profile.edit', absolute: false))
            ->assertSessionHas('status', 'password-identity-confirmed')
            ->assertSessionHas(self::SESSION_KEY, fn (array $confirmation): bool => $confirmation['user_id'] === $user->id
                && is_int($confirmation['confirmed_at'])
            );
    }

    public function test_mismatched_google_callback_is_rejected(): void
    {
        $user = $this->googleOnlyUser();
        $this->mockGoogleCallback('another-google-account');

        $this->actingAs($user)
            ->get(route('profile.password.google.callback', absolute: false))
            ->assertRedirect(route('profile.edit', absolute: false))
            ->assertSessionHasErrors('google')
            ->assertSessionMissing(self::SESSION_KEY);
    }

    public function test_validation_errors_preserve_a_valid_google_confirmation(): void
    {
        $user = $this->googleOnlyUser();

        $this->actingAs($user)
            ->withSession($this->confirmationFor($user))
            ->put('/password', [
                'password' => 'new-secure-password',
                'password_confirmation' => 'does-not-match',
            ])
            ->assertSessionHasErrors('password', null, 'updatePassword')
            ->assertSessionHas(self::SESSION_KEY);
    }

    public function test_expired_or_wrong_user_confirmation_is_rejected(): void
    {
        config(['auth.profile_password_setup_reauth_ttl' => 600]);
        $user = $this->googleOnlyUser();

        $expired = [self::SESSION_KEY => [
            'user_id' => $user->id,
            'confirmed_at' => now()->subSeconds(601)->timestamp,
        ]];

        $this->actingAs($user)
            ->withSession($expired)
            ->put('/password', [
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])->assertSessionHasErrors('password', null, 'updatePassword');

        $wrongUser = [self::SESSION_KEY => [
            'user_id' => $user->id + 100,
            'confirmed_at' => now()->timestamp,
        ]];

        $this->actingAs($user)
            ->withSession($wrongUser)
            ->put('/password', [
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])->assertSessionHasErrors('password', null, 'updatePassword');

        $this->assertNull($user->fresh()->password);
    }

    public function test_confirmed_google_user_can_set_a_password_and_log_in_conventionally(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->googleOnlyUser(['remember_token' => 'old-token']);
        DB::table('sessions')->insert([
            'id' => 'other-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->withSession($this->confirmationFor($user))
            ->put('/password', [
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertSessionHas('status', 'password-set')
            ->assertSessionMissing(self::SESSION_KEY);

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
        $this->assertNotSame('old-token', $user->remember_token);
        $this->assertSame('google-password-setup', $user->google_id);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'new-secure-password',
        ])->assertRedirect(route('customer.appointments.index', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_confirmation_is_single_use(): void
    {
        $user = $this->googleOnlyUser();

        $this->actingAs($user)
            ->withSession($this->confirmationFor($user))
            ->put('/password', [
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])->assertSessionHas('status', 'password-set');

        $this->actingAs($user->fresh())->put('/password', [
            'password' => 'another-secure-password',
            'password_confirmation' => 'another-secure-password',
        ])->assertSessionHasErrors('current_password', null, 'updatePassword');
    }

    public function test_existing_password_accounts_keep_the_current_password_flow(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Update Password')
            ->assertSee('Current Password');

        $this->actingAs($user)->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertSessionHasErrors('current_password', null, 'updatePassword');
    }

    private function googleOnlyUser(array $attributes = []): User
    {
        return User::factory()->customer()->create(array_merge([
            'email' => 'google-only@example.com',
            'google_id' => 'google-password-setup',
            'password' => null,
        ], $attributes));
    }

    private function confirmationFor(User $user): array
    {
        return [self::SESSION_KEY => [
            'user_id' => $user->id,
            'confirmed_at' => now()->timestamp,
        ]];
    }

    private function mockGoogleCallback(string $googleId): void
    {
        $googleUser = new GoogleUser;
        $googleUser->id = $googleId;

        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('redirectUrl')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }
}
