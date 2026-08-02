<?php

namespace Tests\Feature;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_heavy_authenticated_pages_keep_bounded_query_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = StaffProfile::factory()->create();

        $this->assertQueryCountAtMost($admin, route('admin.dashboard', absolute: false), 15);
        $this->assertQueryCountAtMost($admin, route('admin.appointments.index', absolute: false), 15);
        $this->assertQueryCountAtMost($admin, route('admin.transactions.index', absolute: false), 15);
        $this->assertQueryCountAtMost($staff->user, route('staff.dashboard', absolute: false), 15);
    }

    private function assertQueryCountAtMost(User $user, string $url, int $maximum): void
    {
        $timing = $this->actingAs($user)->get($url)->assertOk()->headers->get('Server-Timing');

        $this->assertIsString($timing);
        $this->assertMatchesRegularExpression('/queries;desc="(\d+)"/', $timing);
        preg_match('/queries;desc="(\d+)"/', $timing, $matches);
        $this->assertLessThanOrEqual($maximum, (int) $matches[1], "{$url} exceeded its query budget.");
    }
}
