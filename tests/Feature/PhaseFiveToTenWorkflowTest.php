<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\PromotionRule;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFiveToTenWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_availability_endpoint_returns_real_calendar_slots(): void
    {
        $customer = User::factory()->customer()->create();
        StaffProfile::factory()->create();
        $staffUser = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->for($staffUser)->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staffProfile->services()->attach($service);

        $slotDate = now()->addWeek()->setTime(14, 0, 0);
        StaffWeeklySchedule::factory()->for($staffProfile)->create([
            'day_of_week' => $slotDate->dayOfWeek,
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
        ]);

        $response = $this->actingAs($customer)
            ->getJson(route('customer.appointments.availability', [
                'service_id' => $service->id,
                'month' => $slotDate->format('Y-m'),
            ], false));

        $response->assertOk();

        $slots = collect($response->json('dates.'.$slotDate->toDateString()));

        $this->assertTrue($slots->contains('time', '14:00'));
        $this->assertTrue($slots->contains('time', '15:00'));
        $this->assertFalse($slots->contains('time', '16:00'));
        $this->assertNotNull($slots->firstWhere('time', '14:00')['ends_at'] ?? null);
    }

    public function test_customer_request_calendar_includes_time_preview_hooks(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->get(route('customer.appointments.create', absolute: false))
            ->assertOk()
            ->assertSee('slotPreviewLimit: 2', false)
            ->assertSee('previewSlots', false)
            ->assertSee('moreSlotsLabel(day)', false)
            ->assertSee('slot.label', false)
            ->assertSee('eligibleStaff', false);
    }

    public function test_customer_availability_excludes_confirmed_overlap_and_honors_preferred_staff(): void
    {
        $customer = User::factory()->customer()->create();
        $customerProfile = CustomerProfile::factory()->for($customer)->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $firstStaff = StaffProfile::factory()->create();
        $secondStaff = StaffProfile::factory()->create();
        $firstStaff->services()->attach($service);
        $secondStaff->services()->attach($service);

        $slotDate = now()->addWeek()->setTime(14, 0, 0);

        foreach ([$firstStaff, $secondStaff] as $staffProfile) {
            StaffWeeklySchedule::factory()->for($staffProfile)->create([
                'day_of_week' => $slotDate->dayOfWeek,
                'start_time' => '14:00:00',
                'end_time' => '16:00:00',
            ]);
        }

        Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->for($firstStaff, 'staffProfile')
            ->create([
                'status' => Appointment::STATUS_CONFIRMED,
                'requested_start_at' => $slotDate,
                'scheduled_start_at' => $slotDate,
                'scheduled_end_at' => $slotDate->copy()->addHour(),
            ]);

        $generalResponse = $this->actingAs($customer)
            ->getJson(route('customer.appointments.availability', [
                'service_id' => $service->id,
                'month' => $slotDate->format('Y-m'),
            ], false));

        $preferredResponse = $this->actingAs($customer)
            ->getJson(route('customer.appointments.availability', [
                'service_id' => $service->id,
                'preferred_staff_profile_id' => $firstStaff->id,
                'month' => $slotDate->format('Y-m'),
            ], false));

        $this->assertTrue(collect($generalResponse->json('dates.'.$slotDate->toDateString()))->contains('time', '14:00'));
        $this->assertFalse(collect($preferredResponse->json('dates.'.$slotDate->toDateString()))->contains('time', '14:00'));
    }

    public function test_customer_booking_is_confirmed_automatically_and_overlap_is_blocked(): void
    {
        $staffUser = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->for($staffUser)->create();
        $customerUser = User::factory()->customer()->create();
        $customerProfile = CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staffProfile->services()->attach($service);

        $start = now()->addWeek()->setTime(14, 0, 0);
        StaffWeeklySchedule::factory()->for($staffProfile)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->actingAs($customerUser)
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $service->id,
                'preferred_staff_profile_id' => $staffProfile->id,
                'requested_start_at' => $start->format('Y-m-d H:i:s'),
                'customer_notes' => 'Quiet room please.',
            ])
            ->assertRedirect();

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertSame($staffProfile->id, $appointment->staff_profile_id);
        $this->assertSame($staffProfile->id, $appointment->preferred_staff_profile_id);
        $this->assertSame('Quiet room please.', $appointment->customer_notes);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'staff_profile_id' => $staffProfile->id,
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $secondCustomer = User::factory()->customer()->create();
        CustomerProfile::factory()->for($secondCustomer)->create();
        $this->actingAs($secondCustomer)
            ->from(route('customer.appointments.create', absolute: false))
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $service->id,
                'requested_start_at' => $start->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('customer.appointments.create', absolute: false))
            ->assertSessionHasErrors('requested_start_at');

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_customer_cannot_request_unavailable_calendar_slot(): void
    {
        $customerUser = User::factory()->customer()->create();
        CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staffProfile = StaffProfile::factory()->create();
        $staffProfile->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);

        StaffWeeklySchedule::factory()->for($staffProfile)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '15:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->actingAs($customerUser)
            ->from(route('customer.appointments.create', absolute: false))
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $service->id,
                'requested_start_at' => $start->format('Y-m-d H:i:s'),
                'customer_notes' => 'Trying an unavailable slot.',
            ])
            ->assertRedirect(route('customer.appointments.create', absolute: false))
            ->assertSessionHasErrors('requested_start_at');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_customer_can_attach_an_rfm_addon_voucher_and_cancellation_releases_it(): void
    {
        $customerUser = User::factory()->customer()->create();
        $customerProfile = CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create(['price' => 649, 'duration_minutes' => 60, 'is_active' => true]);
        $staffProfile = StaffProfile::factory()->create();
        $staffProfile->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);

        StaffWeeklySchedule::factory()->for($staffProfile)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '17:00:00',
        ]);

        $segment = RfmSegment::factory()->create(['is_active' => true]);
        $rule = PromotionRule::factory()->for($segment, 'rfmSegment')->create([
            'suggested_offer' => 'Complimentary Hot Compress add-on voucher',
            'addon_code' => 'hot-compress',
            'is_active' => true,
        ]);
        $voucher = PromotionSuggestion::factory()
            ->for($customerProfile)
            ->for($segment, 'rfmSegment')
            ->for($rule, 'promotionRule')
            ->create([
                'suggested_offer' => $rule->suggested_offer,
                'addon_code' => $rule->addon_code,
                'status' => PromotionSuggestion::STATUS_SUGGESTED,
            ]);

        $this->actingAs($customerUser)
            ->get(route('customer.appointments.create', absolute: false))
            ->assertOk()
            ->assertSee('Hot Compress')
            ->assertSee('does not change the package price');

        $this->actingAs($customerUser)
            ->from(route('customer.appointments.create', absolute: false))
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $service->id,
                'promotion_suggestion_id' => $voucher->id,
                'addon_codes' => ['hot-compress'],
                'requested_start_at' => $start->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('customer.appointments.create', absolute: false))
            ->assertSessionHasErrors('addon_codes');

        $this->assertDatabaseCount('appointments', 0);

        $this->actingAs($customerUser)
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $service->id,
                'promotion_suggestion_id' => $voucher->id,
                'requested_start_at' => $start->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame($voucher->id, $appointment->promotion_suggestion_id);
        $this->assertSame('649.00', $service->fresh()->price);
        $this->assertDatabaseHas('promotion_suggestions', [
            'id' => $voucher->id,
            'status' => PromotionSuggestion::STATUS_APPLIED,
        ]);

        $this->actingAs($customerUser)
            ->patch(route('customer.appointments.cancel', $appointment, false))
            ->assertRedirect(route('customer.appointments.history', absolute: false));

        $this->assertDatabaseHas('promotion_suggestions', [
            'id' => $voucher->id,
            'status' => PromotionSuggestion::STATUS_SUGGESTED,
        ]);
    }

    public function test_customer_paid_addons_extend_availability_and_persist_the_billing_snapshot(): void
    {
        $customerUser = User::factory()->customer()->create();
        CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create(['price' => 649, 'duration_minutes' => 60, 'is_active' => true]);
        $staffProfile = StaffProfile::factory()->create();
        $staffProfile->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);

        StaffWeeklySchedule::factory()->for($staffProfile)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '14:00:00',
            'end_time' => '17:00:00',
        ]);

        $slots = collect($this->actingAs($customerUser)
            ->getJson(route('customer.appointments.availability', [
                'service_id' => $service->id,
                'addon_codes' => ['back-massage-30'],
                'month' => $start->format('Y-m'),
            ], false))
            ->assertOk()
            ->json('dates.'.$start->toDateString()));

        $this->assertTrue($slots->contains('time', '14:00'));
        $this->assertFalse($slots->contains('time', '16:00'));

        $this->actingAs($customerUser)
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $service->id,
                'requested_start_at' => $start->format('Y-m-d H:i:s'),
                'addon_codes' => ['hot-compress', 'back-massage-30'],
            ])
            ->assertRedirect();

        $appointment = Appointment::query()->with(['service', 'addons'])->sole();

        $this->assertSame($start->copy()->addMinutes(90)->toDateTimeString(), $appointment->scheduled_end_at?->toDateTimeString());
        $this->assertSame(['back-massage-30', 'hot-compress'], $appointment->addons->pluck('addon_code')->sort()->values()->all());
        $this->assertSame(1148.0, $appointment->expectedAmount());
    }

    public function test_admin_and_receptionist_can_save_paid_addons_with_confirmed_appointments(): void
    {
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->receptionist()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staff = StaffProfile::factory()->create();
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);

        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
        ]);

        $payload = [
            'customer_profile_id' => $customer->id,
            'service_id' => $service->id,
            'staff_profile_id' => $staff->id,
            'requested_start_at' => $start->format('Y-m-d H:i:s'),
            'scheduled_start_at' => $start->format('Y-m-d H:i:s'),
            'status' => Appointment::STATUS_CONFIRMED,
        ];

        $this->actingAs($admin)
            ->post(route('admin.appointments.store', absolute: false), [...$payload, 'addon_codes' => ['ventosa']])
            ->assertRedirect();

        $this->actingAs($receptionist)
            ->post(route('reception.appointments.store', absolute: false), [
                ...$payload,
                'requested_start_at' => $start->copy()->addHours(2)->format('Y-m-d H:i:s'),
                'scheduled_start_at' => $start->copy()->addHours(2)->format('Y-m-d H:i:s'),
                'addon_codes' => ['back-massage-30'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('appointment_addons', ['addon_code' => 'ventosa']);
        $this->assertDatabaseHas('appointment_addons', ['addon_code' => 'back-massage-30']);
    }

    public function test_transaction_feedback_and_csv_report_workflow(): void
    {
        $admin = User::factory()->admin()->create();
        $customerUser = User::factory()->customer()->create();
        $customerProfile = CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create(['price' => 1250, 'is_active' => true]);
        $appointment = Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create([
                'status' => Appointment::STATUS_COMPLETED,
                'requested_start_at' => now()->subDay()->setTime(10, 0),
                'scheduled_start_at' => now()->subDay()->setTime(10, 0),
                'scheduled_end_at' => now()->subDay()->setTime(11, 0),
                'completed_at' => now()->subDay()->setTime(11, 0),
            ]);

        $this->actingAs($admin)
            ->post(route('admin.transactions.store', absolute: false), [
                'customer_profile_id' => $customerProfile->id,
                'appointment_id' => $appointment->id,
                'service_id' => $service->id,
                'amount' => 1250,
                'payment_status' => Transaction::PAYMENT_PAID,
                'payment_method' => Transaction::METHOD_CASH,
                'paid_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'customer_profile_id' => $customerProfile->id,
            'appointment_id' => $appointment->id,
            'payment_status' => Transaction::PAYMENT_PAID,
        ]);

        $this->actingAs($customerUser)
            ->post(route('customer.feedback.store', absolute: false), [
                'appointment_id' => $appointment->id,
                'rating' => 5,
                'comment' => 'Excellent and relaxing visit.',
            ])
            ->assertRedirect(route('customer.feedback.index', absolute: false));

        $this->assertDatabaseHas('feedback', [
            'appointment_id' => $appointment->id,
            'sentiment_label' => Feedback::SENTIMENT_POSITIVE,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.export', ['type' => 'transactions'], false))
            ->assertOk()
            ->assertDownload();
    }

    public function test_paid_completed_transaction_automatically_issues_customer_reward(): void
    {
        $admin = User::factory()->admin()->create();
        $customerProfile = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['price' => 1500]);
        $appointment = Appointment::factory()
            ->for($customerProfile)
            ->for($service)
            ->create([
                'status' => Appointment::STATUS_COMPLETED,
                'completed_at' => now()->subDays(5),
            ]);

        app(TransactionWorkflow::class)->persist(new Transaction, [
            'appointment_id' => $appointment->id,
            'customer_profile_id' => $customerProfile->id,
            'service_id' => $service->id,
            'amount' => 1500,
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => now(),
            'notes' => null,
        ], $admin->id);

        $this->assertDatabaseHas('promotion_suggestions', [
            'customer_profile_id' => $customerProfile->id,
            'status' => PromotionSuggestion::STATUS_SUGGESTED,
            'addon_code' => 'hot-compress',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.promotions.index', absolute: false))
            ->assertOk()
            ->assertSee('Customer rewards');
    }
}
