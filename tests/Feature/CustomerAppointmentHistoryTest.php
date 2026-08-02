<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAppointmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_history_is_owned_filterable_and_links_to_details(): void
    {
        $customer = CustomerProfile::factory()->create();
        $otherCustomer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['name' => 'Island Renewal']);
        $therapist = StaffProfile::factory()->create();

        $upcoming = Appointment::factory()->for($customer)->for($service)->for($therapist, 'staffProfile')->create([
            'appointment_number' => 'APT-HISTORY-UPCOMING',
            'status' => Appointment::STATUS_CONFIRMED,
            'requested_start_at' => now()->addDays(4)->setTime(14, 0),
            'scheduled_start_at' => now()->addDays(4)->setTime(14, 0),
        ]);
        Appointment::factory()->for($customer)->for($service)->create([
            'appointment_number' => 'APT-HISTORY-CANCELLED',
            'status' => Appointment::STATUS_CANCELLED,
            'requested_start_at' => now()->subDays(2)->setTime(14, 0),
            'scheduled_start_at' => null,
        ]);
        Appointment::factory()->for($otherCustomer)->for($service)->create([
            'appointment_number' => 'APT-OTHER-CUSTOMER',
            'status' => Appointment::STATUS_CONFIRMED,
            'requested_start_at' => now()->addDay()->setTime(14, 0),
            'scheduled_start_at' => now()->addDay()->setTime(14, 0),
        ]);

        $this->actingAs($customer->user)
            ->get(route('customer.appointments.index', absolute: false))
            ->assertOk()
            ->assertSee(route('customer.appointments.history'), false)
            ->assertDontSee('Selected day');

        $this->actingAs($customer->user)
            ->get(route('customer.appointments.history', absolute: false))
            ->assertOk()
            ->assertSee('Appointment history')
            ->assertSee('APT-HISTORY-UPCOMING')
            ->assertSee('Island Renewal')
            ->assertSee($therapist->user->name)
            ->assertSee('data-modal-name="customer-appointment-details-'.$upcoming->id.'"', false)
            ->assertDontSee('href="'.route('customer.appointments.show', $upcoming).'"', false)
            ->assertDontSee('APT-OTHER-CUSTOMER');

        $this->actingAs($customer->user)
            ->get(route('customer.appointments.history', ['status' => Appointment::STATUS_CANCELLED], false))
            ->assertOk()
            ->assertSee('APT-HISTORY-CANCELLED')
            ->assertDontSee('APT-HISTORY-UPCOMING');
    }

    public function test_customer_history_filters_dates_orders_upcoming_first_and_preserves_filters_when_paginated(): void
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();

        Appointment::factory()->for($customer)->for($service)->create([
            'appointment_number' => 'APT-HISTORY-LATER',
            'status' => Appointment::STATUS_CONFIRMED,
            'requested_start_at' => now()->addDays(5),
            'scheduled_start_at' => now()->addDays(5),
        ]);
        Appointment::factory()->for($customer)->for($service)->create([
            'appointment_number' => 'APT-HISTORY-SOONER',
            'status' => Appointment::STATUS_CONFIRMED,
            'requested_start_at' => now()->addDays(2),
            'scheduled_start_at' => now()->addDays(2),
        ]);
        Appointment::factory()->for($customer)->for($service)->create([
            'appointment_number' => 'APT-HISTORY-PAST',
            'status' => Appointment::STATUS_COMPLETED,
            'requested_start_at' => now()->subDay(),
            'scheduled_start_at' => now()->subDay(),
        ]);

        $content = $this->actingAs($customer->user)
            ->get(route('customer.appointments.history', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($content, 'APT-HISTORY-LATER'),
            strpos($content, 'APT-HISTORY-SOONER'),
        );
        $this->assertLessThan(
            strpos($content, 'APT-HISTORY-PAST'),
            strpos($content, 'APT-HISTORY-LATER'),
        );

        $this->actingAs($customer->user)
            ->get(route('customer.appointments.history', [
                'date_from' => now()->addDay()->toDateString(),
                'date_to' => now()->addDays(3)->toDateString(),
            ], false))
            ->assertOk()
            ->assertSee('APT-HISTORY-SOONER')
            ->assertDontSee('APT-HISTORY-LATER')
            ->assertDontSee('APT-HISTORY-PAST');
    }

    public function test_customer_history_rejects_invalid_filters_without_changing_calendar_feed(): void
    {
        $customer = CustomerProfile::factory()->create();

        $this->actingAs($customer->user)
            ->from(route('customer.appointments.history', absolute: false))
            ->get(route('customer.appointments.history', ['status' => 'invalid'], false))
            ->assertRedirect(route('customer.appointments.history', absolute: false))
            ->assertSessionHasErrors('status');

        $this->actingAs($customer->user)
            ->from(route('customer.appointments.history', absolute: false))
            ->get(route('customer.appointments.history', [
                'date_from' => now()->addDay()->toDateString(),
                'date_to' => now()->toDateString(),
            ], false))
            ->assertRedirect(route('customer.appointments.history', absolute: false))
            ->assertSessionHasErrors('date_to');

        $this->actingAs($customer->user)
            ->getJson(route('customer.appointments.calendar', ['month' => now()->format('Y-m')], false))
            ->assertOk();
    }

    public function test_customer_history_pagination_preserves_active_filters(): void
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();

        Appointment::factory()
            ->count(16)
            ->for($customer)
            ->for($service)
            ->create([
                'status' => Appointment::STATUS_CANCELLED,
                'scheduled_start_at' => null,
                'requested_start_at' => now()->subDays(2),
            ]);

        $response = $this->actingAs($customer->user)
            ->get(route('customer.appointments.history', ['status' => Appointment::STATUS_CANCELLED], false))
            ->assertOk();

        parse_str((string) parse_url($response->viewData('appointments')->url(2), PHP_URL_QUERY), $query);

        $this->assertSame(Appointment::STATUS_CANCELLED, $query['status']);
        $this->assertSame('2', $query['page']);
    }
}
