<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use App\Services\AppointmentAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalendarSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_feeds_enforce_roles_and_customer_ownership(): void
    {
        $admin = User::factory()->admin()->create();
        $staffUser = User::factory()->staff()->create();
        StaffProfile::factory()->for($staffUser)->create();
        $firstCustomer = User::factory()->customer()->create();
        $firstProfile = CustomerProfile::factory()->for($firstCustomer)->create();
        $secondCustomer = User::factory()->customer()->create();
        $secondProfile = CustomerProfile::factory()->for($secondCustomer)->create();
        $service = Service::factory()->create();
        $start = now()->addDays(2)->setTime(14, 0);

        Appointment::factory()->for($firstProfile)->for($service)->create([
            'appointment_number' => 'APT-OWN-CALENDAR',
            'requested_start_at' => $start,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
        ]);
        Appointment::factory()->for($secondProfile)->for($service)->create([
            'appointment_number' => 'APT-OTHER-CALENDAR',
            'requested_start_at' => $start,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
        ]);

        $customerResponse = $this->actingAs($firstCustomer)->getJson(route('customer.appointments.calendar', [
            'month' => $start->format('Y-m'),
        ], false));
        $numbers = collect($customerResponse->json('events'))->pluck('appointment_number');

        $this->assertTrue($numbers->contains('APT-OWN-CALENDAR'));
        $this->assertFalse($numbers->contains('APT-OTHER-CALENDAR'));
        $this->actingAs($firstCustomer)->getJson(route('admin.appointments.calendar', [
            'start' => $start->toDateString(),
            'end' => $start->copy()->addDay()->toDateString(),
        ], false))->assertForbidden();
        $this->actingAs($staffUser)->getJson(route('customer.appointments.calendar', [
            'month' => $start->format('Y-m'),
        ], false))->assertForbidden();
        $this->actingAs($admin)->getJson(route('staff.appointments.calendar', [
            'start' => $start->toDateString(),
            'end' => $start->copy()->addDay()->toDateString(),
        ], false))->assertForbidden();
    }

    public function test_customer_slots_can_end_exactly_at_midnight(): void
    {
        $customer = User::factory()->customer()->create();
        $staffUser = User::factory()->staff()->create();
        $staff = StaffProfile::factory()->for($staffUser)->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(23, 0, 0);

        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '00:00:00',
            'ends_next_day' => true,
        ]);

        $response = $this->actingAs($customer)->getJson(route('customer.appointments.availability', [
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $staff->id,
            'month' => $start->format('Y-m'),
        ], false));

        $response->assertOk();
        $slot = collect($response->json('dates.'.$start->toDateString()))->firstWhere('time', '23:00');

        $this->assertNotNull($slot);
        $this->assertSame($start->copy()->addHour()->toDateTimeString(), $slot['ends_at']);
    }

    public function test_month_availability_resolves_therapist_schedules_with_bounded_queries(): void
    {
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $month = now('Asia/Manila')->addMonthNoOverflow()->format('Y-m');

        foreach (StaffProfile::factory()->count(3)->create() as $staff) {
            $staff->services()->attach($service);

            foreach (range(0, 6) as $dayOfWeek) {
                StaffWeeklySchedule::factory()->for($staff)->create([
                    'day_of_week' => $dayOfWeek,
                    'start_time' => '13:00:00',
                    'end_time' => '00:00:00',
                    'ends_next_day' => true,
                ]);
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $availability = app(AppointmentAvailability::class)->month($service, $month);
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertNotEmpty($availability['dates']);
        $this->assertLessThan(20, $queryCount, 'Monthly availability must resolve each therapist schedule once per date, not once per slot.');
    }

    public function test_customer_availability_and_submission_require_thirty_minutes_of_lead_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 19:04:00', 'Asia/Manila'));

        try {
            $customer = User::factory()->customer()->create();
            CustomerProfile::factory()->for($customer)->create();
            $staff = StaffProfile::factory()->create();
            $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
            $staff->services()->attach($service);
            StaffWeeklySchedule::factory()->for($staff)->create([
                'day_of_week' => now()->dayOfWeek,
                'start_time' => '13:00:00',
                'end_time' => '00:00:00',
                'ends_next_day' => true,
            ]);

            $response = $this->actingAs($customer)->getJson(route('customer.appointments.availability', [
                'service_id' => $service->id,
                'preferred_staff_profile_id' => $staff->id,
                'month' => now()->format('Y-m'),
            ], false));

            $slots = collect($response->json('dates.'.now()->toDateString()));
            $response->assertOk();
            $this->assertFalse($slots->contains('time', '19:30'));
            $this->assertTrue($slots->contains('time', '20:00'));

            $this->actingAs($customer)
                ->post(route('customer.appointments.store', absolute: false), [
                    'service_id' => $service->id,
                    'preferred_staff_profile_id' => $staff->id,
                    'requested_start_at' => now()->setTime(19, 30)->toDateTimeString(),
                ])
                ->assertSessionHasErrors('requested_start_at');

            $this->assertDatabaseCount('appointments', 0);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_customer_lead_time_accepts_exactly_thirty_minutes_and_rejects_one_second_less(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 19:00:00', 'Asia/Manila'));

        try {
            $firstCustomer = User::factory()->customer()->create();
            CustomerProfile::factory()->for($firstCustomer)->create();
            $secondCustomer = User::factory()->customer()->create();
            CustomerProfile::factory()->for($secondCustomer)->create();
            $staff = StaffProfile::factory()->create();
            $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
            $staff->services()->attach($service);
            StaffWeeklySchedule::factory()->for($staff)->create([
                'day_of_week' => now()->dayOfWeek,
                'start_time' => '13:00:00',
                'end_time' => '00:00:00',
                'ends_next_day' => true,
            ]);
            $start = now()->setTime(19, 30);

            $this->actingAs($firstCustomer)
                ->post(route('customer.appointments.store', absolute: false), [
                    'service_id' => $service->id,
                    'preferred_staff_profile_id' => $staff->id,
                    'requested_start_at' => $start->toDateTimeString(),
                ])
                ->assertSessionDoesntHaveErrors();

            Carbon::setTestNow(Carbon::parse('2026-07-14 19:00:01', 'Asia/Manila'));

            $this->actingAs($secondCustomer)
                ->post(route('customer.appointments.store', absolute: false), [
                    'service_id' => $service->id,
                    'preferred_staff_profile_id' => $staff->id,
                    'requested_start_at' => $start->toDateTimeString(),
                ])
                ->assertSessionHasErrors('requested_start_at');

            $this->assertDatabaseCount('appointments', 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_staff_operated_bookings_do_not_use_the_customer_lead_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 19:04:00', 'Asia/Manila'));

        try {
            $admin = User::factory()->admin()->create();
            $receptionist = User::factory()->receptionist()->create();
            $firstCustomer = CustomerProfile::factory()->create();
            $secondCustomer = CustomerProfile::factory()->create();
            $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
            $start = now()->setTime(19, 30);
            $staffProfiles = StaffProfile::factory()->count(2)->create();

            foreach ($staffProfiles as $staff) {
                $staff->services()->attach($service);
                StaffWeeklySchedule::factory()->for($staff)->create([
                    'day_of_week' => $start->dayOfWeek,
                    'start_time' => '13:00:00',
                    'end_time' => '00:00:00',
                    'ends_next_day' => true,
                ]);
            }

            $this->actingAs($admin)
                ->post(route('admin.appointments.store', absolute: false), [
                    'customer_profile_id' => $firstCustomer->id,
                    'service_id' => $service->id,
                    'staff_profile_id' => $staffProfiles[0]->id,
                    'scheduled_start_at' => $start->toDateTimeString(),
                    'status' => Appointment::STATUS_CONFIRMED,
                ])
                ->assertSessionDoesntHaveErrors();

            $this->actingAs($receptionist)
                ->post(route('reception.appointments.store', absolute: false), [
                    'customer_profile_id' => $secondCustomer->id,
                    'service_id' => $service->id,
                    'staff_profile_id' => $staffProfiles[1]->id,
                    'scheduled_start_at' => $start->toDateTimeString(),
                    'status' => Appointment::STATUS_CONFIRMED,
                ])
                ->assertSessionDoesntHaveErrors();

            $this->assertDatabaseCount('appointments', 2);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_operational_calendar_rejects_unbounded_ranges(): void
    {
        $admin = User::factory()->admin()->create();
        $start = now()->startOfDay();

        $this->actingAs($admin)->getJson(route('admin.appointments.calendar', [
            'start' => $start->toDateString(),
            'end' => $start->copy()->addDays(9)->toDateString(),
        ], false))->assertUnprocessable()->assertJsonValidationErrors('end');
    }

    public function test_preference_is_assigned_and_confirmed_booking_holds_capacity(): void
    {
        $firstCustomer = User::factory()->customer()->create();
        CustomerProfile::factory()->for($firstCustomer)->create();
        $secondCustomer = User::factory()->customer()->create();
        CustomerProfile::factory()->for($secondCustomer)->create();
        $staffUser = User::factory()->staff()->create();
        $staff = StaffProfile::factory()->for($staffUser)->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);

        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
        ]);

        $this->actingAs($firstCustomer)->post(route('customer.appointments.store', absolute: false), [
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $staff->id,
            'requested_start_at' => $start->toDateTimeString(),
            'customer_notes' => 'Quiet room please.',
        ])->assertRedirect();

        $appointment = Appointment::query()->firstOrFail();
        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertSame($staff->id, $appointment->staff_profile_id);
        $this->assertSame($staff->id, $appointment->preferred_staff_profile_id);
        $this->assertSame('Quiet room please.', $appointment->customer_notes);

        $response = $this->actingAs($secondCustomer)->getJson(route('customer.appointments.availability', [
            'service_id' => $service->id,
            'preferred_staff_profile_id' => $staff->id,
            'month' => $start->format('Y-m'),
        ], false));

        $this->assertFalse(collect($response->json('dates.'.$start->toDateString()))->contains('time', '14:00'));
    }

    public function test_operational_calendars_exclude_unassigned_cancelled_history(): void
    {
        $admin = User::factory()->admin()->create();
        $staffUser = User::factory()->staff()->create();
        $staff = StaffProfile::factory()->for($staffUser)->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['is_active' => true]);
        $staff->services()->attach($service);
        $start = now()->addDays(2)->setTime(14, 0, 0);

        Appointment::factory()->for($customer)->for($service)->create([
            'appointment_number' => 'APT-UNASSIGNED-CANCELLED',
            'staff_profile_id' => null,
            'requested_start_at' => $start,
            'scheduled_start_at' => null,
            'scheduled_end_at' => null,
            'status' => Appointment::STATUS_CANCELLED,
            'confirmed_at' => null,
            'cancelled_at' => now(),
        ]);
        Appointment::factory()->for($customer)->for($service)->for($staff, 'staffProfile')->create([
            'appointment_number' => 'APT-ASSIGNED-CONFIRMED',
            'requested_start_at' => $start,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $range = [
            'start' => $start->copy()->startOfDay()->toDateString(),
            'end' => $start->copy()->addDay()->startOfDay()->toDateString(),
        ];
        $staffResponse = $this->actingAs($staffUser)->getJson(route('staff.appointments.calendar', $range, false));
        $staffNumbers = collect($staffResponse->json('events'))->pluck('appointment_number')->filter();

        $this->assertTrue($staffNumbers->contains('APT-ASSIGNED-CONFIRMED'));
        $this->assertFalse($staffNumbers->contains('APT-UNASSIGNED-CANCELLED'));

        $adminResponse = $this->actingAs($admin)->getJson(route('admin.appointments.calendar', [
            ...$range,
            'mode' => 'bookings',
        ], false));
        $adminNumbers = collect($adminResponse->json('events'))->pluck('appointment_number')->filter();

        $this->assertTrue($adminNumbers->contains('APT-ASSIGNED-CONFIRMED'));
        $this->assertFalse($adminNumbers->contains('APT-UNASSIGNED-CANCELLED'));
    }

    public function test_availability_change_is_rolled_back_when_it_breaks_a_confirmed_booking(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = StaffProfile::factory()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(15, 0, 0);

        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '00:00:00',
            'ends_next_day' => true,
        ]);
        Appointment::factory()->for($customer)->for($service)->for($staff, 'staffProfile')->create([
            'appointment_number' => 'APT-CONFLICT-GUARD',
            'status' => Appointment::STATUS_CONFIRMED,
            'requested_start_at' => $start,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.staff.show', $staff, false))
            ->post(route('admin.staff.schedule-exceptions.store', $staff, false), [
                'exception_date' => $start->toDateString(),
                'exception_type' => StaffScheduleException::TYPE_UNAVAILABLE,
                'start_time' => '15:00',
                'end_time' => '16:00',
                'reason' => 'Conflicting leave',
            ])
            ->assertRedirect(route('admin.staff.show', $staff, false))
            ->assertSessionHasErrors('schedule')
            ->assertSessionHas('schedule_conflicts');

        $this->assertDatabaseMissing('staff_schedule_exceptions', ['reason' => 'Conflicting leave']);

        $this->actingAs($admin)->post(route('admin.staff.schedule-exceptions.store', $staff, false), [
            'exception_date' => $start->toDateString(),
            'exception_type' => StaffScheduleException::TYPE_UNAVAILABLE,
            'start_time' => '20:00',
            'end_time' => '21:00',
            'reason' => 'Harmless leave',
        ])->assertRedirect(route('admin.staff.show', $staff, false));

        $this->assertDatabaseHas('staff_schedule_exceptions', ['reason' => 'Harmless leave']);
    }

    public function test_admin_available_therapists_lookup_excludes_confirmed_overlap(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
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

        Appointment::factory()->for($customer)->for($service)->for($first, 'staffProfile')->create([
            'status' => Appointment::STATUS_CONFIRMED,
            'requested_start_at' => $start,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.appointments.available-therapists', [
            'service_id' => $service->id,
            'starts_at' => $start->toDateTimeString(),
        ], false));

        $response->assertOk();
        $ids = collect($response->json('therapists'))->pluck('id');
        $this->assertFalse($ids->contains($first->id));
        $this->assertTrue($ids->contains($second->id));
    }

    public function test_admin_calendar_page_loads_the_creation_form_lazily_through_the_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.appointments.index', absolute: false))
            ->assertOk()
            ->assertSee(route('admin.appointments.create', absolute: false), false)
            ->assertSee('data-panel-link', false)
            ->assertSee('createUrl:', false)
            ->assertDontSee('calendar-appointment-create', false)
            ->assertDontSee(route('admin.appointments.calendar.store', absolute: false), false);
    }

    public function test_admin_calendar_creation_saves_confirmed_reservation_and_returns_to_calendar(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 90, 'is_active' => true]);
        $staff = StaffProfile::factory()->create();
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);
        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $start->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '18:00:00',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.appointments.calendar.store', absolute: false), [
                '_modal' => 'calendar-appointment-create',
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'staff_profile_id' => $staff->id,
                'scheduled_start_at' => $start->toDateTimeString(),
                'status' => Appointment::STATUS_CONFIRMED,
            ])
            ->assertRedirect(route('admin.appointments.index', absolute: false));

        $appointment = Appointment::query()->sole();
        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertSame($staff->id, $appointment->staff_profile_id);
        $this->assertTrue($appointment->requested_start_at->equalTo($appointment->scheduled_start_at));
        $this->assertTrue($appointment->scheduled_end_at->equalTo($start->copy()->addMinutes(90)));
        $this->assertDatabaseHas('appointment_status_logs', [
            'appointment_id' => $appointment->id,
            'from_status' => null,
            'to_status' => Appointment::STATUS_CONFIRMED,
        ]);
    }

    public function test_admin_calendar_creation_rejects_unsupported_status_missing_staff_and_overlap(): void
    {
        $admin = User::factory()->admin()->create();
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
        Appointment::factory()->for($customer)->for($service)->for($staff, 'staffProfile')->create([
            'status' => Appointment::STATUS_CONFIRMED,
            'requested_start_at' => $start,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $start->copy()->addHour(),
        ]);

        $base = [
            'customer_profile_id' => $customer->id,
            'service_id' => $service->id,
            'requested_start_at' => $start->toDateTimeString(),
            'scheduled_start_at' => $start->toDateTimeString(),
        ];

        $this->actingAs($admin)
            ->from(route('admin.appointments.index', absolute: false))
            ->post(route('admin.appointments.calendar.store', absolute: false), [
                ...$base,
                'status' => 'pending',
            ])
            ->assertSessionHasErrors(['status', 'staff_profile_id']);

        $this->actingAs($admin)
            ->from(route('admin.appointments.index', absolute: false))
            ->post(route('admin.appointments.calendar.store', absolute: false), [
                ...$base,
                'staff_profile_id' => $staff->id,
                'status' => Appointment::STATUS_CONFIRMED,
                '_modal' => 'calendar-appointment-create',
            ])
            ->assertSessionHasErrors('scheduled_start_at')
            ->assertSessionHasInput('_modal', 'calendar-appointment-create');

        $this->assertDatabaseCount('appointments', 1);
    }
}
