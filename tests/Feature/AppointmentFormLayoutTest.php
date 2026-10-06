<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_create_and_edit_pages_render_the_shared_booking_layout(): void
    {
        $appointment = Appointment::factory()->create();

        foreach ([['admin', User::factory()->admin()->create()], ['reception', User::factory()->receptionist()->create()]] as [$role, $user]) {
            foreach (['create', 'edit'] as $action) {
                $this->actingAs($user)
                    ->get(route("{$role}.appointments.{$action}", $action === 'edit' ? $appointment : []))
                    ->assertOk()
                    ->assertSee('Booking summary')
                    ->assertSee('data-booking-step="1"', false)
                    ->assertSee('data-booking-step="2"', false)
                    ->assertSee('data-booking-step="3"', false)
                    ->assertSee('Start time')
                    ->assertSee('name="customer_name"', false)
                    ->assertSee('role="combobox"', false)
                    ->assertSee('id="booking_start_time"', false)
                    ->assertSee('name="scheduled_start_at"', false)
                    ->assertSee('Back to appointments')
                    ->assertSee((string) \Illuminate\Support\Js::from(route("{$role}.appointments.available-therapists")), false)
                    ->assertSee((string) \Illuminate\Support\Js::from(route("{$role}.appointments.availability")), false);
            }
        }
    }

    public function test_validation_values_are_preserved_in_the_summary_configuration_and_notes(): void
    {
        $admin = User::factory()->admin()->create();
        $appointment = Appointment::factory()->create();

        $this->actingAs($admin)->withSession(['_old_input' => [
            'scheduled_start_at' => '2026-10-03T14:30',
            'customer_notes' => 'Use gentle pressure',
            'internal_notes' => 'Reception reminder',
        ]])->get(route('admin.appointments.edit', $appointment))
            ->assertOk()
            ->assertSee('value="2026-10-03T14:30"', false)
            ->assertSee('Use gentle pressure')
            ->assertSee('Reception reminder');
    }
}
