<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomatedAppointmentQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_booking_uses_least_booked_eligible_therapist(): void
    {
        $customer = User::factory()->customer()->create();
        CustomerProfile::factory()->for($customer)->create();
        $service = Service::factory()->create(['is_active' => true, 'duration_minutes' => 60]);
        $first = StaffProfile::factory()->create();
        $second = StaffProfile::factory()->create();
        $start = now()->addWeek()->setTime(14, 0, 0);

        foreach ([$first, $second] as $staff) {
            $staff->services()->attach($service);
            StaffWeeklySchedule::factory()->for($staff)->create([
                'day_of_week' => $start->dayOfWeek,
                'start_time' => '13:00:00',
                'end_time' => '18:00:00',
            ]);
        }

        Appointment::factory()->for($service)->for($first, 'staffProfile')->create([
            'status' => Appointment::STATUS_CONFIRMED,
            'requested_start_at' => $start->copy()->addDay(),
            'scheduled_start_at' => $start->copy()->addDay(),
            'scheduled_end_at' => $start->copy()->addDay()->addHour(),
        ]);

        $this->actingAs($customer)->post(route('customer.appointments.store', absolute: false), [
            'service_id' => $service->id,
            'requested_start_at' => $start->toDateTimeString(),
        ])->assertRedirect();

        $booking = Appointment::query()->where('requested_start_at', $start)->firstOrFail();
        $this->assertSame(Appointment::STATUS_CONFIRMED, $booking->status);
        $this->assertSame($second->id, $booking->staff_profile_id);
    }

    public function test_admin_completion_records_transaction_atomically(): void
    {
        $admin = User::factory()->admin()->create();
        $assignedStaff = StaffProfile::factory()->create();
        $appointment = Appointment::factory()->for($assignedStaff, 'staffProfile')->create([
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_start_at' => now()->subMinutes(30),
            'scheduled_end_at' => now()->addMinutes(30),
            'confirmed_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)->post(route('admin.appointments.complete', $appointment, false), [
            'amount' => 499,
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => now()->toDateTimeString(),
            'notes' => 'Paid at reception.',
        ])->assertRedirect();

        $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->fresh()->status);
        $this->assertDatabaseHas('transactions', [
            'appointment_id' => $appointment->id,
            'payment_status' => Transaction::PAYMENT_PAID,
            'recorded_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.appointments.complete', $appointment, false), [
            'amount' => 499,
            'payment_status' => Transaction::PAYMENT_UNPAID,
        ])->assertSessionHasErrors('status');

        $this->assertSame(1, $appointment->transactions()->count());
    }

    public function test_customer_cancellation_releases_the_reserved_slot(): void
    {
        $customer = User::factory()->customer()->create();
        CustomerProfile::factory()->for($customer)->create();
        $otherCustomer = User::factory()->customer()->create();
        CustomerProfile::factory()->for($otherCustomer)->create();
        $service = Service::factory()->create(['is_active' => true, 'duration_minutes' => 60]);
        $staff = StaffProfile::factory()->create();
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);
        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
        ]);

        $this->actingAs($customer)->post(route('customer.appointments.store', absolute: false), [
            'service_id' => $service->id,
            'requested_start_at' => $start->toDateTimeString(),
        ]);
        $appointment = Appointment::query()->sole();

        $this->actingAs($otherCustomer)
            ->patch(route('customer.appointments.cancel', $appointment, false))
            ->assertForbidden();

        $this->actingAs($customer)
            ->patch(route('customer.appointments.cancel', $appointment, false))
            ->assertRedirect();

        $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->fresh()->status);
        $availability = $this->actingAs($otherCustomer)->getJson(route('customer.appointments.availability', [
            'service_id' => $service->id,
            'month' => $start->format('Y-m'),
        ], false));
        $this->assertTrue(collect($availability->json('dates.'.$start->toDateString()))->contains('time', '14:00'));
    }

    public function test_admin_queue_orders_overdue_before_upcoming_and_no_show_creates_no_transaction(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = StaffProfile::factory()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();
        $overdue = Appointment::factory()->for($customer)->for($service)->for($staff, 'staffProfile')->create([
            'appointment_number' => 'APT-QUEUE-OVERDUE',
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_start_at' => now()->subHours(2),
            'scheduled_end_at' => now()->subHour(),
        ]);
        Appointment::factory()->for($customer)->for($service)->for($staff, 'staffProfile')->create([
            'appointment_number' => 'APT-QUEUE-UPCOMING',
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_start_at' => now()->addHour(),
            'scheduled_end_at' => now()->addHours(2),
        ]);

        $this->actingAs($admin)->get(route('admin.appointments.index', absolute: false))
            ->assertOk()
            ->assertSeeInOrder(['APT-QUEUE-OVERDUE', 'APT-QUEUE-UPCOMING']);

        $this->actingAs($admin)->patch(route('admin.appointments.outcome', $overdue, false), [
            'status' => Appointment::STATUS_NO_SHOW,
        ])->assertRedirect(route('admin.appointments.index', absolute: false));

        $this->assertSame(Appointment::STATUS_NO_SHOW, $overdue->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_admin_service_queue_uses_the_fixed_fifteen_record_page_size(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = StaffProfile::factory()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();

        foreach (range(1, 16) as $offset) {
            Appointment::factory()->for($customer)->for($service)->for($staff, 'staffProfile')->create([
                'appointment_number' => sprintf('APT-PAGED-%02d', $offset),
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => now()->addDays($offset),
                'scheduled_end_at' => now()->addDays($offset)->addHour(),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.appointments.index', absolute: false))
            ->assertOk()
            ->assertSee('APT-PAGED-15')
            ->assertDontSee('APT-PAGED-16');

        $this->actingAs($admin)
            ->get(route('admin.appointments.index', ['queue_page' => 2], false))
            ->assertOk()
            ->assertSee('APT-PAGED-16')
            ->assertDontSee('APT-PAGED-01');
    }

    public function test_future_completion_and_staff_mutations_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $assignedStaff = StaffProfile::factory()->create();
        $appointment = Appointment::factory()->for($assignedStaff, 'staffProfile')->create([
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_start_at' => now()->addHour(),
            'scheduled_end_at' => now()->addHours(2),
        ]);

        $this->actingAs($admin)->post(route('admin.appointments.complete', $appointment, false), [
            'amount' => 499,
            'payment_status' => Transaction::PAYMENT_UNPAID,
        ])->assertSessionHasErrors('status');

        $this->actingAs($staff)->post(route('admin.appointments.complete', $appointment, false), [
            'amount' => 499,
            'payment_status' => Transaction::PAYMENT_UNPAID,
        ])->assertForbidden();

        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
    }
}
