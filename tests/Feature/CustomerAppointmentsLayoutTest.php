<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerAppointmentsLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_the_nearest_owned_booking_and_latest_unreviewed_visit(): void
    {
        $this->travelTo(Carbon::parse('2026-10-11 12:00:00', 'Asia/Manila'));
        $customer = CustomerProfile::factory()->create();
        $otherCustomer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['name' => 'Island massage', 'duration_minutes' => 60]);
        $therapist = StaffProfile::factory()->create();

        $nextVisit = Appointment::factory()->for($customer)->for($service)->for($therapist, 'staffProfile')->create([
            'scheduled_start_at' => '2026-10-16 13:00:00',
            'scheduled_end_at' => '2026-10-16 14:30:00',
        ]);
        Appointment::factory()->for($customer)->for($service)->create(['scheduled_start_at' => '2026-10-23 14:00:00']);
        Appointment::factory()->for($customer)->for($service)->create(['scheduled_start_at' => '2026-10-10 14:00:00']);
        Appointment::factory()->for($customer)->for($service)->create([
            'scheduled_start_at' => '2026-10-12 14:00:00', 'status' => Appointment::STATUS_CANCELLED,
        ]);
        Appointment::factory()->for($otherCustomer)->create(['scheduled_start_at' => '2026-10-12 13:00:00']);

        $feedbackVisit = Appointment::factory()->for($customer)->for($service)->create([
            'status' => Appointment::STATUS_COMPLETED, 'completed_at' => '2026-10-03 15:00:00',
            'scheduled_start_at' => '2026-10-03 14:00:00',
        ]);
        Appointment::factory()->for($customer)->for($service)->create([
            'status' => Appointment::STATUS_COMPLETED, 'completed_at' => '2026-10-01 15:00:00',
        ]);
        $reviewed = Appointment::factory()->for($customer)->for($service)->create([
            'status' => Appointment::STATUS_COMPLETED, 'completed_at' => '2026-10-09 15:00:00',
        ]);
        Feedback::factory()->for($reviewed)->for($customer)->for($service)->create();
        Appointment::factory()->for($otherCustomer)->create([
            'status' => Appointment::STATUS_COMPLETED, 'completed_at' => '2026-10-10 15:00:00',
        ]);

        $response = $this->actingAs($customer->user)
            ->get(route('customer.appointments.index', ['status' => 'cancelled'], false));

        $response->assertOk()
            ->assertViewHas('nextVisit', fn ($visit) => $visit->is($nextVisit)
                && $visit->relationLoaded('service') && $visit->staffProfile->relationLoaded('user'))
            ->assertViewHas('feedbackVisit', fn ($visit) => $visit->is($feedbackVisit) && $visit->relationLoaded('service'))
            ->assertViewHas('summary', ['upcoming' => 2, 'cancelled' => 1, 'completed' => 3])
            ->assertSee('A little time for yourself.')
            ->assertSee('90 minutes')
            ->assertSee('1:00 PM')
            ->assertSee('2:30 PM')
            ->assertSee('Asia/Manila')
            ->assertSee($therapist->user->name)
            ->assertSee(route('customer.appointments.show', $nextVisit), false)
            ->assertSee(route('customer.feedback.create', ['appointment_id' => $feedbackVisit->id]), false)
            ->assertDontSee(route('customer.feedback.create', ['appointment_id' => $reviewed->id]), false);

        $this->get(route('customer.appointments.show', Appointment::where('customer_profile_id', $otherCustomer->id)->first(), false))
            ->assertForbidden();
    }

    public function test_empty_cards_do_not_use_another_customers_visits(): void
    {
        $customer = CustomerProfile::factory()->create();
        Appointment::factory()->create();
        Appointment::factory()->create(['status' => Appointment::STATUS_COMPLETED, 'completed_at' => now()->subDay()]);

        $this->actingAs($customer->user)->get(route('customer.appointments.index', absolute: false))
            ->assertOk()
            ->assertViewHas('nextVisit', null)
            ->assertViewHas('feedbackVisit', null)
            ->assertViewHas('summary', ['upcoming' => 0, 'cancelled' => 0, 'completed' => 0])
            ->assertSee('You have no upcoming visits.')
            ->assertSee('There are no completed visits awaiting feedback.')
            ->assertSee('Open your appointment history')
            ->assertSee(route('customer.appointments.history'), false)
            ->assertDontSee('Share feedback');
    }

    public function test_next_visit_uses_saved_midnight_end_and_handles_missing_duration(): void
    {
        $this->travelTo(Carbon::parse('2026-10-11 12:00:00', 'Asia/Manila'));
        $customer = CustomerProfile::factory()->create();
        $appointment = Appointment::factory()->for($customer)->create([
            'scheduled_start_at' => '2026-10-16 23:00:00', 'scheduled_end_at' => '2026-10-17 00:00:00',
        ]);

        $this->actingAs($customer->user)->get(route('customer.appointments.index', absolute: false))
            ->assertOk()->assertSee('60 minutes')->assertSee('12:00 AM')->assertSee('(next day)');

        $appointment->update(['scheduled_end_at' => null]);
        $this->get(route('customer.appointments.index', absolute: false))
            ->assertOk()->assertSee('Not recorded')->assertDontSee('60 minutes');
    }

    public function test_calendar_covers_complete_monday_to_sunday_weeks_and_respects_boundaries(): void
    {
        $customer = CustomerProfile::factory()->create();
        foreach (['2026-09-27 14:00:00', '2026-09-28 14:00:00', '2026-11-01 14:00:00', '2026-11-02 14:00:00'] as $index => $start) {
            Appointment::factory()->for($customer)->create([
                'appointment_number' => 'APT-BOUNDARY-'.$index,
                'requested_start_at' => $start, 'scheduled_start_at' => $start,
                'scheduled_end_at' => Carbon::parse($start)->addHour(),
            ]);
        }

        $response = $this->actingAs($customer->user)->getJson(route('customer.appointments.calendar', ['month' => '2026-10'], false));
        $response->assertOk()->assertJsonPath('range.start', '2026-09-28')->assertJsonPath('range.end', '2026-11-02');
        $this->assertEqualsCanonicalizing(['APT-BOUNDARY-1', 'APT-BOUNDARY-2'], array_column($response->json('events'), 'appointment_number'));

        foreach ([
            ['month' => '2027-02', 'start' => '2027-02-01', 'end' => '2027-03-01'],
            ['month' => '2028-02', 'start' => '2028-01-31', 'end' => '2028-03-06'],
        ] as $range) {
            $this->getJson(route('customer.appointments.calendar', ['month' => $range['month']], false))
                ->assertOk()->assertJsonPath('range.start', $range['start'])->assertJsonPath('range.end', $range['end']);
        }
    }

    public function test_feedback_card_disappears_after_the_last_eligible_review_is_submitted(): void
    {
        $customer = CustomerProfile::factory()->create();
        $visit = Appointment::factory()->for($customer)->create([
            'status' => Appointment::STATUS_COMPLETED, 'completed_at' => now()->subDay(),
        ]);
        Feedback::factory()->for($visit, 'appointment')->for($customer)->create(['service_id' => $visit->service_id]);

        $this->actingAs($customer->user)->get(route('customer.appointments.index', absolute: false))
            ->assertOk()->assertViewHas('feedbackVisit', null)->assertDontSee('Share feedback');
    }
}
