<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBookingModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_appointments_loads_the_booking_form_lazily_and_keeps_full_page_fallback(): void
    {
        $customer = CustomerProfile::factory()->create();

        $this->actingAs($customer->user)
            ->get(route('customer.appointments.index', absolute: false))
            ->assertOk()
            ->assertSee(route('customer.appointments.create', absolute: false), false)
            ->assertSee('data-panel-link', false)
            ->assertDontSee('customer-book-appointment', false)
            ->assertDontSee('_booking_context', false)
            ->assertSee('Book appointment');

        $this->actingAs($customer->user)
            ->get(route('customer.appointments.create', absolute: false))
            ->assertOk()
            ->assertSee('Book an appointment');
    }

    public function test_booking_page_accepts_validated_service_and_date_preselection(): void
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();
        $requestedStart = now()->addDays(8)->setTime(15, 30);

        $this->actingAs($customer->user)
            ->get(route('customer.appointments.create', [
                'service_id' => $service->id,
                'requested_start_at' => $requestedStart->format('Y-m-d H:i:s'),
            ], false))
            ->assertOk()
            ->assertSee('value="'.$service->id.'" selected', false)
            ->assertSee('value="'.$requestedStart->format('Y-m-d\TH:i').'"', false);

        $this->actingAs($customer->user)
            ->from(route('customer.appointments.index', absolute: false))
            ->get(route('customer.appointments.create', [
                'service_id' => 999999,
                'requested_start_at' => 'not-a-date',
            ], false))
            ->assertRedirect(route('customer.appointments.index', absolute: false))
            ->assertSessionHasErrors(['service_id', 'requested_start_at']);
    }

    public function test_calendar_submission_still_creates_an_immediately_confirmed_booking(): void
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

        $response = $this->actingAs($customer->user)->post(route('customer.appointments.store', absolute: false), [
            '_booking_context' => 'calendar',
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $therapist->id,
            'requested_start_at' => $start->format('Y-m-d H:i:s'),
            'customer_notes' => 'Calendar booking.',
        ]);

        $appointment = Appointment::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('customer.appointments.history', absolute: false));
        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertSame($therapist->id, $appointment->staff_profile_id);
    }
}
