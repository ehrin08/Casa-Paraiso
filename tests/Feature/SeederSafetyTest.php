<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $application = app();
        $originalEnvironment = $application->environment();
        $application->detectEnvironment(fn () => 'production');

        try {
            (new DatabaseSeeder)->run();
            $this->fail('The demo seeder ran in production.');
        } catch (\LogicException $exception) {
            $this->assertSame(
                'Demo data must not be seeded in production. Run migrations without --seed.',
                $exception->getMessage(),
            );
        } finally {
            $application->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    public function test_demo_seeder_creates_the_protected_super_admin_without_replacing_an_existing_account(): void
    {
        (new DatabaseSeeder)->run();

        $superAdmin = User::query()->where('email', config('auth.super_admin_email'))->firstOrFail();

        $this->assertSame(User::ROLE_SUPER_ADMIN, $superAdmin->role);
        $this->assertTrue(Hash::check('password', $superAdmin->password));

        $superAdmin->update(['name' => 'Existing Owner']);

        (new DatabaseSeeder)->run();

        $this->assertSame('Existing Owner', $superAdmin->fresh()->name);
    }
}
