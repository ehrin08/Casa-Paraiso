<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BookingSuccessConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingSuccessConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Manila')->setDate(2026, 11, 16)->setTime(10, 0));
    }

    public static function operationalRoles(): array
    {
        return [
            'admin' => ['admin', 'admin'],
            'super admin' => ['super_admin', 'admin'],
            'receptionist' => ['receptionist', 'reception'],
        ];
    }

    #[DataProvider('operationalRoles')]
    public function test_operational_roles_see_the_saved_booking_once(string $role, string $workspace): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $appointment = $this->appointment(CustomerProfile::createGuest('Walk-in customer', null));

        $this->actingAs($actor)
            ->withSession(['status' => 'appointment-created', BookingSuccessConfirmation::FLASH_KEY => $appointment->id])
            ->get(route($workspace.'.appointments.index'))
            ->assertOk()
            ->assertSee('data-booking-success', false)
            ->assertSee('Appointment saved')
            ->assertSee('Walk-in customer')
            ->assertSee('Massage package')
            ->assertSee('Assigned therapist')
            ->assertSee('Tuesday, November 17, 2026')
            ->assertSee('1:00 PM')
            ->assertSee($appointment->appointment_number)
            ->assertSee(route($workspace.'.appointments.show', $appointment))
            ->assertDontSee('data-toast-stack', false);

        $this->get(route($workspace.'.appointments.index'))
            ->assertOk()
            ->assertDontSee('data-booking-success', false);
    }

    public function test_customer_sees_their_own_booking_confirmation(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $profile = CustomerProfile::factory()->create(['user_id' => $customer->id]);
        $customer->unsetRelation('customerProfile');
        $appointment = $this->appointment($profile);

        $this->actingAs($customer)
            ->withSession(['status' => 'appointment-booked', BookingSuccessConfirmation::FLASH_KEY => $appointment->id])
            ->get(route('customer.appointments.history'))
            ->assertOk()
            ->assertSee('data-booking-success', false)
            ->assertSee('Booking confirmed')
            ->assertSee('Massage package')
            ->assertSee(route('customer.appointments.show', $appointment))
            ->assertDontSee('data-toast-stack', false);
    }

    public function test_customer_never_sees_another_customers_booking(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        CustomerProfile::factory()->create(['user_id' => $customer->id]);
        $customer->unsetRelation('customerProfile');
        $appointment = $this->appointment(CustomerProfile::createGuest('Someone else', null));

        $this->actingAs($customer)
            ->withSession(['status' => 'appointment-booked', BookingSuccessConfirmation::FLASH_KEY => $appointment->id])
            ->get(route('customer.appointments.history'))
            ->assertOk()
            ->assertDontSee('data-booking-success', false)
            ->assertDontSee($appointment->appointment_number)
            ->assertSee('data-toast-stack', false);
    }

    public function test_other_success_messages_stay_toasts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->withSession(['status' => 'service-activated'])
            ->get(route('admin.services.index'))
            ->assertOk()
            ->assertSee('data-toast-stack', false)
            ->assertSee('Service activated.')
            ->assertSee('Dismiss message')
            ->assertDontSee('data-booking-success', false);
    }

    private function appointment(CustomerProfile $profile): Appointment
    {
        return Appointment::factory()->create([
            'customer_profile_id' => $profile->id,
            'service_id' => Service::factory()->create(['name' => 'Massage package', 'price' => 1000])->id,
            'staff_profile_id' => StaffProfile::factory()->create(['user_id' => User::factory()->staff()->create(['name' => 'Assigned therapist'])->id])->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_start_at' => now('Asia/Manila')->addDay()->setTime(13, 0),
            'scheduled_end_at' => now('Asia/Manila')->addDay()->setTime(14, 0),
        ]);
    }
}
