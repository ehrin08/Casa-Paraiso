<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use App\Notifications\NewCustomerBookingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerBookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_booking_notifies_assigned_therapist_and_active_operations_users_only(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $receptionist = User::factory()->receptionist()->create();
        $inactiveAdmin = User::factory()->admin()->inactive()->create();
        $unassignedTherapist = StaffProfile::factory()->create();

        $response = $this->actingAs($customer->user)->post(route('customer.appointments.store', absolute: false), [
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $therapist->id,
            'requested_start_at' => $start->format('Y-m-d H:i:s'),
        ]);

        $appointment = Appointment::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('customer.appointments.history', absolute: false));

        foreach ([$therapist->user, $admin, $superAdmin, $receptionist] as $recipient) {
            $notification = $recipient->notifications()->sole();

            $this->assertSame(NewCustomerBookingNotification::class, $notification->type);
            $this->assertSame($appointment->id, $notification->data['appointment_id']);
            $this->assertSame($customer->user->name, $notification->data['customer_name']);
        }

        $this->assertSame(0, $customer->user->notifications()->count());
        $this->assertSame(0, $inactiveAdmin->notifications()->count());
        $this->assertSame(0, $unassignedTherapist->user->notifications()->count());
    }

    public function test_notifications_inbox_marks_an_item_read_and_opens_the_scoped_booking(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $appointment = Appointment::factory()
            ->confirmed()
            ->for($customer)
            ->for($service)
            ->for($therapist, 'staffProfile')
            ->create([
                'staff_profile_id' => $therapist->id,
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $start->copy()->addHour(),
            ]);

        $therapist->user->notify(new NewCustomerBookingNotification($appointment));
        $notification = $therapist->user->notifications()->sole();

        $this->actingAs($therapist->user)
            ->get(route('notifications.index', absolute: false))
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee($appointment->appointment_number)
            ->assertSee('>1<', false);

        $this->actingAs($therapist->user)
            ->patch(route('notifications.open', $notification, false))
            ->assertRedirect(route('staff.appointments.show', $appointment, false));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notifications_are_unavailable_to_customers_and_failed_bookings_create_none(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();

        $this->actingAs($customer->user)
            ->get(route('notifications.index', absolute: false))
            ->assertForbidden();

        $this->actingAs($customer->user)
            ->from(route('customer.appointments.create', absolute: false))
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $service->id,
                'preferred_staff_profile_id' => $therapist->id,
                'requested_start_at' => $start->copy()->subDays(20)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('customer.appointments.create', absolute: false));

        $this->assertSame(0, $therapist->user->notifications()->count());
        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_mark_all_read_clears_the_recipients_unread_booking_alerts(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $appointment = Appointment::factory()
            ->confirmed()
            ->for($customer)
            ->for($service)
            ->for($therapist, 'staffProfile')
            ->create([
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $start->copy()->addHour(),
            ]);

        $therapist->user->notify(new NewCustomerBookingNotification($appointment));
        $therapist->user->notify(new NewCustomerBookingNotification($appointment));

        $this->actingAs($therapist->user)
            ->patch(route('notifications.read-all', absolute: false))
            ->assertRedirect(route('notifications.index', absolute: false));

        $this->assertSame(0, $therapist->user->unreadNotifications()->count());
    }

    public function test_operational_users_can_fetch_and_acknowledge_only_their_unread_booking_count(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $appointment = Appointment::factory()
            ->confirmed()
            ->for($customer)
            ->for($service)
            ->for($therapist, 'staffProfile')
            ->create([
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $start->copy()->addHour(),
            ]);

        $therapist->user->notify(new NewCustomerBookingNotification($appointment));
        $therapist->user->notify(new NewCustomerBookingNotification($appointment));

        $otherRecipient = User::factory()->admin()->create();
        $otherRecipient->notify(new NewCustomerBookingNotification($appointment));

        $this->actingAs($therapist->user)
            ->getJson(route('notifications.bookings.count', absolute: false))
            ->assertOk()
            ->assertExactJson(['count' => 2]);

        $this->actingAs($therapist->user)
            ->patchJson(route('notifications.bookings.read', absolute: false))
            ->assertOk()
            ->assertExactJson(['count' => 0]);

        $this->assertSame(0, $therapist->user->unreadNotifications()
            ->where('type', NewCustomerBookingNotification::class)
            ->count());

        $this->assertSame(1, $otherRecipient->unreadNotifications()->count());
        $therapist->user->notify(new NewCustomerBookingNotification($appointment));
        $poll = $this->actingAs($therapist->user)
            ->getJson(route('notifications.bookings.count', absolute: false))
            ->assertOk()
            ->assertExactJson(['count' => 1]);
        $this->assertStringContainsString('no-store', $poll->headers->get('Cache-Control'));
        $this->assertSame(1, $therapist->user->unreadNotifications()->count());

        $this->actingAs($customer->user)
            ->getJson(route('notifications.bookings.count', absolute: false))
            ->assertForbidden();
    }

    public function test_schedule_badge_is_rendered_for_operational_users_but_not_the_notifications_link(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $appointment = Appointment::factory()
            ->confirmed()
            ->for($customer)
            ->for($service)
            ->for($therapist, 'staffProfile')
            ->create([
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $start->copy()->addHour(),
            ]);

        $therapist->user->notify(new NewCustomerBookingNotification($appointment));

        $this->actingAs($therapist->user)
            ->get(route('staff.appointments.index', absolute: false))
            ->assertOk()
            ->assertSee('data-booking-notifications', false)
            ->assertSee('data-booking-schedule-link', false)
            ->assertSee('data-booking-notification-badge', false)
            ->assertSee('1 unread booking notifications', false)
            ->assertSee('Notifications');
    }

    public function test_staff_created_bookings_do_not_create_customer_booking_notifications(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)->post(route('reception.appointments.store', absolute: false), [
            'customer_profile_id' => $customer->id,
            'service_id' => $service->id,
            'staff_profile_id' => $therapist->id,
            'requested_start_at' => $start->format('Y-m-d H:i:s'),
            'scheduled_start_at' => $start->format('Y-m-d H:i:s'),
            'status' => Appointment::STATUS_CONFIRMED,
        ])->assertRedirect();

        $this->assertSame(0, $therapist->user->notifications()->count());
        $this->assertSame(0, $receptionist->notifications()->count());
    }

    /** @return array{CustomerProfile, Service, StaffProfile, Carbon} */
    private function bookingFixture(): array
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $therapist = StaffProfile::factory()->create();
        $therapist->services()->attach($service);
        $start = now()->addDays(8)->setTime(14, 0);

        StaffWeeklySchedule::factory()->for($therapist)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
            'ends_next_day' => false,
            'is_available' => true,
        ]);

        return [$customer, $service, $therapist, $start];
    }
}
