<?php

namespace Tests\Feature;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerBookingGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_reports_online_booking_block(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = CustomerProfile::factory()->create();

        $this->assertFalse($profile->isOnlineBookingBlocked());

        $profile->update([
            'online_booking_blocked_at' => now(),
            'online_booking_block_reason' => 'Spam',
            'online_booking_blocked_by' => $admin->id,
        ]);
        $profile = $profile->fresh();

        $this->assertTrue($profile->isOnlineBookingBlocked());
        $this->assertTrue($profile->onlineBookingBlockedBy->is($admin));
        $this->assertInstanceOf(Carbon::class, $profile->online_booking_blocked_at);
        $this->assertSame(2, config('casa.customer_booking_limits.max_upcoming'));
    }
}
