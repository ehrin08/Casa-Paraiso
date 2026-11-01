<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Services\CustomerBookingGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
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

    public function test_allows_customer_without_restrictions(): void
    {
        $this->assertNull(app(CustomerBookingGuard::class)->restriction(CustomerProfile::factory()->create()));
    }

    public function test_admin_block_wins(): void
    {
        $profile = $this->blockedProfile();
        $this->appointment($profile, Appointment::STATUS_NO_SHOW, now()->subDays(5));
        $this->appointment($profile, Appointment::STATUS_NO_SHOW, now()->subDays(6));

        $this->assertSame(CustomerBookingGuard::RESTRICTION_ADMIN_BLOCK, app(CustomerBookingGuard::class)->restriction($profile));
    }

    public function test_two_recent_no_shows_pause_booking(): void
    {
        $profile = CustomerProfile::factory()->create();
        $this->appointment($profile, Appointment::STATUS_NO_SHOW, now()->subDays(10));
        $this->appointment($profile, Appointment::STATUS_NO_SHOW, now()->subDays(80));

        $this->assertSame(CustomerBookingGuard::RESTRICTION_NO_SHOWS, app(CustomerBookingGuard::class)->restriction($profile));
    }

    public function test_old_no_shows_do_not_count(): void
    {
        $guard = app(CustomerBookingGuard::class);
        $profile = CustomerProfile::factory()->create();
        $this->appointment($profile, Appointment::STATUS_NO_SHOW, now()->subDays(10));
        $this->appointment($profile, Appointment::STATUS_NO_SHOW, now()->subDays(91));

        $this->assertNull($guard->restriction($profile));
        $this->assertSame(1, $guard->noShowCount($profile));
    }

    public function test_cap_counts_only_upcoming_confirmed_self_bookings(): void
    {
        $guard = app(CustomerBookingGuard::class);
        $capped = CustomerProfile::factory()->create();
        $this->appointment($capped, Appointment::STATUS_CONFIRMED, now()->addDays(2));
        $this->appointment($capped, Appointment::STATUS_CONFIRMED, now()->addDays(3));

        $this->assertSame(CustomerBookingGuard::RESTRICTION_UPCOMING_CAP, $guard->restriction($capped));

        $profile = CustomerProfile::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->appointment($profile, Appointment::STATUS_CONFIRMED, now()->addDays(2));
        $this->appointment($profile, Appointment::STATUS_CONFIRMED, now()->addDays(4), $admin->id);
        $this->appointment($profile, Appointment::STATUS_CANCELLED, now()->addDays(5));
        $this->appointment($profile, Appointment::STATUS_COMPLETED, now()->subDays(2));
        $this->appointment($profile, Appointment::STATUS_NO_SHOW, now()->subDays(100));
        $this->appointment($profile, Appointment::STATUS_CONFIRMED, now()->subHour());

        $this->assertSame(1, $guard->upcomingSelfBookingCount($profile));
        $this->assertNull($guard->restriction($profile));
    }

    public function test_assert_can_book_throws_category_message(): void
    {
        $guard = app(CustomerBookingGuard::class);
        $capped = CustomerProfile::factory()->create();
        $this->appointment($capped, Appointment::STATUS_CONFIRMED, now()->addDays(2));
        $this->appointment($capped, Appointment::STATUS_CONFIRMED, now()->addDays(3));

        try {
            $guard->assertCanBook($capped);
            $this->fail('Expected the cap to stop the booking.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'You already have 2 upcoming online bookings. You can book again once one is completed or cancelled.',
                $exception->errors()['booking'][0],
            );
        }

        try {
            $guard->assertCanBook($this->blockedProfile());
            $this->fail('Expected the admin block to stop the booking.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['booking'][0];
            $this->assertSame('Online booking is paused for your account. Please call or visit the spa to book.', $message);
            $this->assertStringNotContainsString('Private spam reason', $message);
        }
    }

    private function blockedProfile(): CustomerProfile
    {
        return CustomerProfile::factory()->create([
            'online_booking_blocked_at' => now(),
            'online_booking_block_reason' => 'Private spam reason',
        ]);
    }

    private function appointment(CustomerProfile $profile, string $status, Carbon $start, ?int $createdBy = null): Appointment
    {
        return Appointment::factory()->for($profile)->create([
            'status' => $status,
            'requested_start_at' => $start,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
            'created_by' => $createdBy ?? $profile->user_id,
        ]);
    }
}
