<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use App\Services\AppointmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerBookingSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_capped_customer_cannot_book_and_nothing_is_held(): void
    {
        Notification::fake();
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $this->selfBooking($customer, now()->addDays(2));
        $this->selfBooking($customer, now()->addDays(3));

        $this->actingAs($customer->user)
            ->post(route('customer.appointments.store', absolute: false), $this->payload($service, $therapist, $start))
            ->assertSessionHasErrors('booking');

        $this->assertSame(2, Appointment::query()->count());
        $this->assertFalse(Appointment::query()->where('staff_profile_id', $therapist->id)->exists());
        Notification::assertNothingSent();
    }

    public function test_cancelling_frees_a_slot(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $this->selfBooking($customer, now()->addDays(2));
        $this->selfBooking($customer, now()->addDays(3))->update(['status' => Appointment::STATUS_CANCELLED]);

        $this->actingAs($customer->user)
            ->post(route('customer.appointments.store', absolute: false), $this->payload($service, $therapist, $start))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('customer.appointments.history', absolute: false));

        $this->assertTrue(Appointment::query()->where('staff_profile_id', $therapist->id)->exists());
    }

    public function test_blocked_customer_cannot_book(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $customer->update(['online_booking_blocked_at' => now(), 'online_booking_block_reason' => 'Private spam reason']);

        $this->actingAs($customer->user)
            ->from(route('customer.appointments.create', absolute: false))
            ->post(route('customer.appointments.store', absolute: false), $this->payload($service, $therapist, $start))
            ->assertSessionHasErrors('booking');

        $this->assertSame(0, Appointment::query()->count());
        $this->assertStringNotContainsString('Private spam reason', session('errors')->first('booking'));
    }

    public function test_fourth_submission_in_window_is_rate_limited(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $pastStart = now()->subDay()->setTime(14, 0);

        foreach (range(1, 3) as $attempt) {
            $this->actingAs($customer->user)
                ->post(route('customer.appointments.store', absolute: false), $this->payload($service, $therapist, $pastStart))
                ->assertSessionHasErrors('requested_start_at');
        }

        $this->actingAs($customer->user)
            ->post(route('customer.appointments.store', absolute: false), $this->payload($service, $therapist, $start))
            ->assertSessionHasErrors('booking');
        $this->assertStringContainsString('Too many booking attempts', session('errors')->first('booking'));
        $this->assertSame(0, Appointment::query()->count());

        $otherCustomer = CustomerProfile::factory()->create();

        $this->actingAs($otherCustomer->user)
            ->post(route('customer.appointments.store', absolute: false), $this->payload($service, $therapist, $start))
            ->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_admin_can_still_book_a_blocked_customer(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $customer->update(['online_booking_blocked_at' => now(), 'online_booking_block_reason' => 'Spam']);
        $this->selfBooking($customer, now()->addDays(2));
        $this->selfBooking($customer, now()->addDays(3));

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.appointments.store', absolute: false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'staff_profile_id' => $therapist->id,
                'scheduled_start_at' => $start->toDateTimeString(),
                'status' => Appointment::STATUS_CONFIRMED,
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertTrue(Appointment::query()->where('staff_profile_id', $therapist->id)->exists());
    }

    public function test_customer_cannot_book_an_overlapping_slot_with_another_therapist(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $existing = $this->selfBooking($customer, $start->copy()->subMinutes(30));

        $this->actingAs($customer->user)
            ->post(route('customer.appointments.store', absolute: false), $this->payload($service, $therapist, $start))
            ->assertSessionHasErrors('requested_start_at');

        $this->assertSame(1, Appointment::query()->count());
        $this->assertNotSame($therapist->id, $existing->staff_profile_id);
        $this->assertStringContainsString('overlaps this time', session('errors')->first('requested_start_at'));
    }

    public function test_back_to_back_and_cancelled_bookings_do_not_block_the_customer(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $this->selfBooking($customer, $start->copy()->subHour());
        $this->selfBooking($customer, $start->copy())->update(['status' => Appointment::STATUS_CANCELLED]);

        $this->actingAs($customer->user)
            ->post(route('customer.appointments.store', absolute: false), $this->payload($service, $therapist, $start))
            ->assertSessionDoesntHaveErrors();

        $this->assertTrue(Appointment::query()->where('staff_profile_id', $therapist->id)->exists());
    }

    public function test_staff_cannot_book_an_overlapping_appointment_for_the_same_customer(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        Appointment::factory()->for($customer)->create([
            'scheduled_start_at' => $start,
            'requested_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.appointments.store', absolute: false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'staff_profile_id' => $therapist->id,
                'scheduled_start_at' => $start->copy()->addMinutes(30)->toDateTimeString(),
                'status' => Appointment::STATUS_CONFIRMED,
            ])
            ->assertSessionHasErrors('scheduled_start_at');

        $this->assertFalse(Appointment::query()->where('staff_profile_id', $therapist->id)->exists());
    }

    public function test_rescheduling_does_not_conflict_with_the_appointment_itself(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $appointment = Appointment::factory()->for($customer)->for($service)->for($therapist)->create([
            'scheduled_start_at' => $start,
            'requested_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
        ]);

        app(AppointmentWorkflow::class)->schedule($appointment, $therapist, $service, $start->copy()->addMinutes(30));

        $this->assertTrue($appointment->fresh()->scheduled_start_at->equalTo($start->copy()->addMinutes(30)));
    }

    public function test_booking_page_shows_notice_instead_of_form_when_restricted(): void
    {
        $blocked = CustomerProfile::factory()->create(['online_booking_blocked_at' => now(), 'online_booking_block_reason' => 'Private spam reason']);

        $this->actingAs($blocked->user)
            ->get(route('customer.appointments.create', absolute: false))
            ->assertOk()
            ->assertSee('Online booking is paused for your account.')
            ->assertDontSee('Private spam reason')
            ->assertDontSee('name="requested_start_at"', false);

        $this->actingAs(CustomerProfile::factory()->create()->user)
            ->get(route('customer.appointments.create', absolute: false))
            ->assertOk()
            ->assertDontSee('Online booking is paused for your account.')
            ->assertSee('name="requested_start_at"', false);
    }

    public function test_rejected_dialog_submit_reopens_the_booking_dialog_with_the_message(): void
    {
        [$customer, $service, $therapist, $start] = $this->bookingFixture();
        $index = route('customer.appointments.index', absolute: false);
        $create = route('customer.appointments.create', absolute: false);

        $this->actingAs($customer->user)
            ->withHeader('X-Casa-Panel', '1')
            ->get($create)
            ->assertOk()
            ->assertSee('name="_panel" value="'.$create.'"', false);

        $this->flushHeaders()->actingAs($customer->user)
            ->get($create)
            ->assertOk()
            ->assertDontSee('name="_panel"', false);

        $this->selfBooking($customer, now()->addDays(2));
        $this->selfBooking($customer, now()->addDays(3));

        $this->actingAs($customer->user)
            ->from($index)
            ->post(route('customer.appointments.store', absolute: false), [...$this->payload($service, $therapist, $start), '_panel' => $create])
            ->assertRedirect($index)
            ->assertSessionHasErrors('booking');

        $this->actingAs($customer->user)
            ->get($index)
            ->assertOk()
            ->assertSee('data-panel-restore="'.$create.'"', false);

        $this->actingAs($customer->user)
            ->withHeader('X-Casa-Panel', '1')
            ->get($create)
            ->assertOk()
            ->assertSee('You already have 2 upcoming online bookings.');
    }

    private function payload(Service $service, StaffProfile $therapist, Carbon $start): array
    {
        return [
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $therapist->id,
            'requested_start_at' => $start->format('Y-m-d H:i:s'),
        ];
    }

    private function selfBooking(CustomerProfile $customer, Carbon $start): Appointment
    {
        return Appointment::factory()->for($customer)->create([
            'scheduled_start_at' => $start,
            'requested_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
            'created_by' => $customer->user_id,
        ]);
    }

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
