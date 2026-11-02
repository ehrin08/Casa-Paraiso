<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\NewCustomerBookingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LiveStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_gets_only_its_own_scopes_without_caching(): void
    {
        $expectations = [
            [User::factory()->admin()->create(), ['appointments', 'attendance', 'notifications']],
            [User::factory()->receptionist()->create(), ['appointments', 'notifications']],
            [StaffProfile::factory()->create()->user, ['appointments', 'notifications']],
            [CustomerProfile::factory()->create()->user, ['appointments']],
        ];

        foreach ($expectations as [$user, $scopes]) {
            $response = $this->actingAs($user)->getJson(route('live.state', [], false))->assertOk();
            $this->assertSame($scopes, array_keys($response->json('scopes')));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_guests_cannot_read_live_state(): void
    {
        $this->getJson(route('live.state', [], false))->assertUnauthorized();
    }

    public function test_appointment_fingerprints_change_only_for_users_who_can_see_the_change(): void
    {
        $admin = User::factory()->admin()->create();
        $therapist = StaffProfile::factory()->create();
        $otherTherapist = StaffProfile::factory()->create();
        $customer = CustomerProfile::factory()->create();
        $appointment = Appointment::factory()->create(['staff_profile_id' => $therapist->id, 'customer_profile_id' => $customer->id]);
        $before = $this->scopes([$admin, $therapist->user, $otherTherapist->user, $customer->user], 'appointments');

        $appointment->forceFill(['status' => 'cancelled', 'updated_at' => now()->addMinute()])->save();
        $after = $this->scopes([$admin, $therapist->user, $otherTherapist->user, $customer->user], 'appointments');

        $this->assertNotSame($before[0], $after[0]);
        $this->assertNotSame($before[1], $after[1]);
        $this->assertSame($before[2], $after[2], 'another therapist sees no change');
        $this->assertNotSame($before[3], $after[3]);

        Appointment::factory()->create();
        $this->assertNotSame($after[0], $this->scopes([$admin], 'appointments')[0]);
        $this->assertSame($after[3], $this->scopes([$customer->user], 'appointments')[0]);
    }

    public function test_attendance_and_notification_fingerprints_follow_their_records(): void
    {
        $admin = User::factory()->admin()->create();
        $attendanceBefore = $this->scopes([$admin], 'attendance')[0];
        $staff = StaffProfile::factory()->create();
        $record = StaffAttendance::create([
            'staff_profile_id' => $staff->id,
            'attendance_date' => '2026-11-02',
            'time_in_at' => '2026-11-02 13:00:00',
        ]);
        $attendanceAfter = $this->scopes([$admin], 'attendance')[0];
        $this->assertNotSame($attendanceBefore, $attendanceAfter);
        $record->events()->create([
            'staff_profile_id' => $staff->id, 'event_type' => 'time_in', 'source' => 'auto_verified_qr',
            'occurred_at' => $record->time_in_at, 'recorded_by' => $staff->user->id,
        ]);
        $this->assertNotSame($attendanceAfter, $this->scopes([$admin], 'attendance')[0]);

        $notificationsBefore = $this->scopes([$admin], 'notifications')[0];
        $notification = $admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => NewCustomerBookingNotification::class,
            'data' => ['appointment_id' => 0],
        ]);
        $unread = $this->scopes([$admin], 'notifications')[0];
        $this->assertNotSame($notificationsBefore, $unread);
        $notification->markAsRead();
        $this->assertNotSame($unread, $this->scopes([$admin], 'notifications')[0]);
    }

    /**
     * @param  list<User>  $users
     * @return list<string>
     */
    private function scopes(array $users, string $scope): array
    {
        return array_map(
            fn (User $user) => $this->actingAs($user)->getJson(route('live.state', [], false))->assertOk()->json('scopes.'.$scope),
            $users,
        );
    }
}
