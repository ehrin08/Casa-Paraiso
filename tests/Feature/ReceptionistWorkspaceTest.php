<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\TherapistCommission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceptionistWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_has_a_dedicated_workspace_without_domain_profiles(): void
    {
        $receptionist = User::factory()->receptionist()->create();

        $this->assertTrue($receptionist->isReceptionist());
        $this->assertNull($receptionist->staffProfile);
        $this->assertNull($receptionist->customerProfile);
        $this->actingAs($receptionist)->get('/dashboard')->assertRedirect(route('reception.dashboard', absolute: false));

        foreach (['/reception/dashboard', '/reception/appointments', '/reception/customers', '/reception/transactions'] as $path) {
            $this->actingAs($receptionist)->get($path)->assertOk();
        }

        foreach (['/admin/dashboard', '/staff/dashboard', '/customer/appointments', '/admin/commissions', '/staff/commissions'] as $path) {
            $this->actingAs($receptionist)->get($path)->assertForbidden();
        }
    }

    public function test_super_admin_can_provision_receptionist_without_creating_profiles(): void
    {
        $superAdmin = User::factory()->create(['email' => config('auth.super_admin_email'), 'role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)->post('/admin/users', [
            'name' => 'Front Desk',
            'email' => 'frontdesk@example.com',
            'role' => User::ROLE_RECEPTIONIST,
            'is_active' => '1',
        ])->assertRedirect();

        $receptionist = User::query()->where('email', 'frontdesk@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_RECEPTIONIST, $receptionist->role);
        $this->assertNull($receptionist->staffProfile);
        $this->assertNull($receptionist->customerProfile);
    }

    public function test_receptionist_can_update_only_permitted_customer_contact_fields(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $customer = CustomerProfile::factory()->create();
        $originalName = $customer->user->name;
        $originalEmail = $customer->user->email;

        $this->actingAs($receptionist)->patch(route('reception.customers.update', $customer, false), [
            'phone' => '09171234567',
            'address' => 'Updated address',
            'contact_preference' => CustomerProfile::CONTACT_SMS,
            'notes' => 'Call before arrival.',
            'name' => 'Unauthorized Name',
            'email' => 'unauthorized@example.com',
        ])->assertRedirect(route('reception.customers.show', $customer, false));

        $customer->refresh();
        $customer->user->refresh();
        $this->assertSame('09171234567', $customer->user->phone);
        $this->assertSame($originalName, $customer->user->name);
        $this->assertSame($originalEmail, $customer->user->email);
        $this->assertSame('Updated address', $customer->address);
        $this->assertSame(CustomerProfile::CONTACT_SMS, $customer->contact_preference);
        $this->assertSame('Call before arrival.', $customer->notes);
    }

    public function test_therapist_profiles_default_to_the_therapist_type(): void
    {
        $profile = StaffProfile::factory()->create();

        $this->assertSame(StaffProfile::TYPE_THERAPIST, $profile->staff_type);
        $this->assertSame([StaffProfile::TYPE_THERAPIST], StaffProfile::TYPES);
    }

    public function test_receptionist_can_create_confirmed_bookings_and_calendar_availability_is_read_only(): void
    {
        $receptionist = User::factory()->receptionist()->create();
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

        $this->actingAs($receptionist)->post(route('reception.appointments.store', absolute: false), [
            'customer_profile_id' => $customer->id,
            'service_id' => $service->id,
            'staff_profile_id' => $therapist->id,
            'preferred_staff_profile_id' => null,
            'requested_start_at' => $start->format('Y-m-d H:i:s'),
            'scheduled_start_at' => $start->format('Y-m-d H:i:s'),
            'status' => Appointment::STATUS_CONFIRMED,
        ])->assertRedirect();

        $appointment = Appointment::query()->latest('id')->firstOrFail();
        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertSame($receptionist->id, $appointment->created_by);

        $bookings = $this->actingAs($receptionist)->getJson(route('reception.appointments.calendar', [
            'mode' => 'bookings',
            'start' => $start->copy()->startOfWeek()->toDateString(),
            'end' => $start->copy()->startOfWeek()->addDays(7)->toDateString(),
        ], false))->assertOk()->json('events');
        $booking = collect($bookings)->firstWhere('appointment_id', $appointment->id);
        $this->assertSame(route('reception.appointments.show', $appointment), $booking['detail_url']);

        $availability = $this->actingAs($receptionist)->getJson(route('reception.appointments.calendar', [
            'mode' => 'availability',
            'start' => $start->copy()->startOfWeek()->toDateString(),
            'end' => $start->copy()->startOfWeek()->addDays(7)->toDateString(),
        ], false))->assertOk()->json('events');
        $weeklyAvailability = collect($availability)->firstWhere('kind', 'availability');
        $this->assertNotNull($weeklyAvailability);
        $this->assertTrue($weeklyAvailability['read_only']);
        $this->assertNull($weeklyAvailability['detail_url']);
    }

    public function test_receptionist_completion_and_payment_updates_use_shared_commission_rules(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['price' => 1000]);
        $therapist = StaffProfile::factory()->create();
        $appointment = Appointment::factory()
            ->for($customer)
            ->for($service)
            ->for($therapist, 'staffProfile')
            ->create([
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => now()->subHours(2),
                'scheduled_end_at' => now()->subHour(),
                'completed_at' => null,
            ]);

        $this->actingAs($receptionist)->post(route('reception.appointments.complete', $appointment, false), [
            'amount' => 1000,
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => now()->subMinutes(30)->format('Y-m-d H:i:s'),
            'notes' => 'Front desk settlement.',
        ])->assertRedirect();

        $transaction = $appointment->transactions()->firstOrFail();
        $earning = $transaction->therapistCommissions()->firstOrFail();
        $this->assertSame($receptionist->id, $transaction->recorded_by);
        $this->assertSame('220.00', $earning->commission_amount);
        $this->assertSame(TherapistCommission::STATUS_PENDING, $earning->status);

        $this->actingAs($receptionist)->patch(route('reception.transactions.update', $transaction, false), [
            'customer_profile_id' => $customer->id,
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'amount' => 1000,
            'payment_status' => Transaction::PAYMENT_REFUNDED,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => $transaction->paid_at->format('Y-m-d H:i:s'),
            'notes' => 'Refunded.',
        ])->assertRedirect();

        $this->assertSame('0.00', $earning->fresh()->commission_amount);
    }

    public function test_receptionist_is_explicitly_denied_management_and_analytics_modules(): void
    {
        $receptionist = User::factory()->receptionist()->create();

        foreach ([
            '/admin/staff',
            '/admin/services',
            '/admin/promotions',
            '/admin/feedback',
            '/admin/reports',
            '/admin/commissions',
            '/admin/settings',
            '/admin/users',
        ] as $path) {
            $this->actingAs($receptionist)->get($path)->assertForbidden();
        }
    }
}
