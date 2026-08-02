<?php

namespace Tests\Feature;

use App\Http\Controllers\Customer\AppointmentController;
use App\Http\Requests\CustomerAppointmentStoreRequest;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\User;
use App\Services\AppointmentNumberGenerator;
use App\Services\AppointmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentCrudRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_request_uses_the_concrete_request_and_enforces_start_intervals(): void
    {
        $customerUser = User::factory()->customer()->create();
        $customer = CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staff = StaffProfile::factory()->create();
        $staff->services()->attach($service);
        $day = now()->addWeek()->startOfDay();
        $this->giveFullBusinessDay($staff, $day);

        $this->assertSame(
            CustomerAppointmentStoreRequest::class,
            (new \ReflectionMethod(AppointmentController::class, 'store'))
                ->getParameters()[0]
                ->getType()?->getName(),
        );

        $this->actingAs($customerUser)
            ->from(route('customer.appointments.create', absolute: false))
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $service->id,
                'requested_start_at' => $day->copy()->setTime(14, 15)->toDateTimeString(),
            ])
            ->assertRedirect(route('customer.appointments.create', absolute: false))
            ->assertSessionHasErrors('requested_start_at');

        $this->assertDatabaseCount('appointments', 0);

        $validStart = $day->copy()->setTime(14, 30);
        $this->actingAs($customerUser)
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $service->id,
                'requested_start_at' => $validStart->toDateTimeString(),
                'customer_notes' => '  Quiet room, please.  ',
            ])
            ->assertRedirect();

        $appointment = Appointment::query()->where('customer_profile_id', $customer->id)->sole();
        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertSame($staff->id, $appointment->staff_profile_id);
        $this->assertSame('Quiet room, please.', $appointment->customer_notes);
        $this->assertStringStartsWith('APT-', $appointment->appointment_number);
        $this->assertDatabaseHas('appointment_status_logs', [
            'appointment_id' => $appointment->id,
            'from_status' => null,
            'to_status' => Appointment::STATUS_CONFIRMED,
        ]);
    }

    public function test_admin_create_requires_a_confirmed_assignment_and_rejects_other_states(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staff = StaffProfile::factory()->create();
        $staff->services()->attach($service);
        $day = now()->addWeek()->startOfDay();
        $this->giveFullBusinessDay($staff, $day);

        $confirmedStart = $day->copy()->setTime(14, 0);
        $this->actingAs($admin)
            ->post(route('admin.appointments.store', absolute: false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'staff_profile_id' => $staff->id,
                'requested_start_at' => $confirmedStart->toDateTimeString(),
                'scheduled_start_at' => $confirmedStart->toDateTimeString(),
                'status' => 'pending',
            ])
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->post(route('admin.appointments.store', absolute: false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'requested_start_at' => $confirmedStart->toDateTimeString(),
                'status' => Appointment::STATUS_CONFIRMED,
            ])
            ->assertSessionHasErrors(['staff_profile_id', 'scheduled_start_at']);

        $this->actingAs($admin)
            ->post(route('admin.appointments.store', absolute: false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'staff_profile_id' => $staff->id,
                'requested_start_at' => $confirmedStart->toDateTimeString(),
                'scheduled_start_at' => $confirmedStart->toDateTimeString(),
                'status' => Appointment::STATUS_CONFIRMED,
            ])
            ->assertRedirect();

        $confirmed = Appointment::query()->where('status', Appointment::STATUS_CONFIRMED)->sole();
        $this->assertSame($staff->id, $confirmed->staff_profile_id);
        $this->assertTrue($confirmed->scheduled_end_at->equalTo($confirmedStart->copy()->addHour()));
        $this->assertNotNull($confirmed->confirmed_at);

        foreach ([Appointment::STATUS_COMPLETED, Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW] as $terminalStatus) {
            $this->actingAs($admin)
                ->from(route('admin.appointments.create', absolute: false))
                ->post(route('admin.appointments.store', absolute: false), [
                    'customer_profile_id' => $customer->id,
                    'service_id' => $service->id,
                    'staff_profile_id' => $staff->id,
                    'requested_start_at' => $day->copy()->setTime(18, 0)->toDateTimeString(),
                    'scheduled_start_at' => $day->copy()->setTime(18, 0)->toDateTimeString(),
                    'status' => $terminalStatus,
                ])
                ->assertRedirect(route('admin.appointments.create', absolute: false))
                ->assertSessionHasErrors('status');
        }

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_transition_matrix_exhaustively_matches_the_declared_lifecycle(): void
    {
        $workflow = app(AppointmentWorkflow::class);

        foreach (Appointment::STATUSES as $fromStatus) {
            foreach (Appointment::STATUSES as $targetStatus) {
                $appointment = new Appointment(['status' => $fromStatus]);
                $appointment->exists = true;
                $accepted = true;

                try {
                    $workflow->assertTransitionAllowed($appointment, $targetStatus);
                } catch (ValidationException) {
                    $accepted = false;
                }

                $this->assertSame(
                    in_array($targetStatus, Appointment::STATUS_TRANSITIONS[$fromStatus], true),
                    $accepted,
                    "Unexpected {$fromStatus} -> {$targetStatus} transition result.",
                );
            }
        }

        $newAppointment = new Appointment;
        foreach (Appointment::STATUSES as $targetStatus) {
            $accepted = true;

            try {
                $workflow->assertTransitionAllowed($newAppointment, $targetStatus);
            } catch (ValidationException) {
                $accepted = false;
            }

            $this->assertSame(in_array($targetStatus, Appointment::CREATION_STATUSES, true), $accepted);
        }
    }

    public function legacy_admin_updates_apply_outcomes_and_preserve_canonical_status_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $staff = StaffProfile::factory()->create();
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);
        $this->giveFullBusinessDay($staff, $start);

        $pending = Appointment::factory()->for($customer)->for($service)->create([
            'status' => Appointment::STATUS_CONFIRMED,
            'requested_start_at' => $start,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.appointments.update', $pending, false), $this->adminPayload($pending, [
                'staff_profile_id' => $staff->id,
                'scheduled_start_at' => $start->toDateTimeString(),
                'status' => Appointment::STATUS_COMPLETED,
            ]))
            ->assertSessionHasErrors('status');
        $this->assertSame(Appointment::STATUS_CONFIRMED, $pending->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('admin.appointments.update', $pending, false), $this->adminPayload($pending, [
                'staff_profile_id' => $staff->id,
                'scheduled_start_at' => $start->toDateTimeString(),
                'status' => Appointment::STATUS_CONFIRMED,
            ]))
            ->assertRedirect(route('admin.appointments.show', $pending, false));

        $confirmed = $pending->fresh();
        $this->assertSame(Appointment::STATUS_CONFIRMED, $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);

        $this->actingAs($admin)
            ->patch(route('admin.appointments.update', $confirmed, false), $this->adminPayload($confirmed, [
                'status' => Appointment::STATUS_COMPLETED,
            ]))
            ->assertRedirect(route('admin.appointments.show', $confirmed, false));

        $completed = $confirmed->fresh();
        $this->assertSame(Appointment::STATUS_COMPLETED, $completed->status);
        $this->assertNotNull($completed->confirmed_at);
        $this->assertNotNull($completed->completed_at);
        $this->assertNull($completed->cancelled_at);

        $this->actingAs($admin)
            ->patch(route('admin.appointments.update', $completed, false), $this->adminPayload($completed, [
                'status' => Appointment::STATUS_CONFIRMED,
            ]))
            ->assertSessionHasErrors('status');
        $this->assertSame(Appointment::STATUS_COMPLETED, $completed->fresh()->status);

        $cancelledPending = Appointment::factory()->for($customer)->for($service)->create([
            'status' => Appointment::STATUS_CONFIRMED,
            'requested_start_at' => $start->copy()->addDay(),
        ]);
        $this->actingAs($admin)
            ->patch(route('admin.appointments.update', $cancelledPending, false), $this->adminPayload($cancelledPending, [
                'staff_profile_id' => $staff->id,
                'scheduled_start_at' => $start->copy()->addDay()->toDateTimeString(),
                'status' => Appointment::STATUS_CANCELLED,
            ]))
            ->assertRedirect(route('admin.appointments.show', $cancelledPending, false));

        $cancelledPending->refresh();
        $this->assertSame(Appointment::STATUS_CANCELLED, $cancelledPending->status);
        $this->assertSame($staff->id, $cancelledPending->staff_profile_id);
        $this->assertTrue($cancelledPending->scheduled_start_at->equalTo($start->copy()->addDay()));
        $this->assertTrue($cancelledPending->scheduled_end_at->equalTo($start->copy()->addDay()->addHour()));
        $this->assertNotNull($cancelledPending->confirmed_at);
        $this->assertNotNull($cancelledPending->cancelled_at);
        $this->assertSame($admin->id, $cancelledPending->cancelled_by);
    }

    public function legacy_staff_can_record_an_outcome_after_the_requested_time_has_passed(): void
    {
        $staffUser = User::factory()->staff()->create();
        $staff = StaffProfile::factory()->for($staffUser)->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();
        $staff->services()->attach($service);
        $start = now()->subDay()->setTime(14, 0, 0);
        $appointment = $this->confirmedAppointment($customer, $service, $staff, $start);

        $this->actingAs($staffUser)
            ->patch(route('staff.appointments.update', $appointment, false), [
                'status' => Appointment::STATUS_COMPLETED,
                'internal_notes' => 'Service completed normally.',
            ])
            ->assertRedirect(route('staff.appointments.show', $appointment, false));

        $appointment->refresh();
        $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->status);
        $this->assertSame('Service completed normally.', $appointment->internal_notes);
        $this->assertNotNull($appointment->completed_at);
    }

    public function legacy_staff_reschedule_control_rechecks_availability_and_rolls_back_overlap(): void
    {
        $staffUser = User::factory()->staff()->create();
        $staff = StaffProfile::factory()->for($staffUser)->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $staff->services()->attach($service);
        $day = now()->addWeek()->startOfDay();
        $this->giveFullBusinessDay($staff, $day);
        $appointment = $this->confirmedAppointment($customer, $service, $staff, $day->copy()->setTime(14, 0));
        $blocker = $this->confirmedAppointment($customer, $service, $staff, $day->copy()->setTime(16, 0));

        $this->actingAs($staffUser)
            ->get(route('staff.appointments.show', $appointment, false))
            ->assertOk()
            ->assertSee('Reschedule appointment')
            ->assertSee('name="scheduled_start_at"', false);

        $adjacentStart = $day->copy()->setTime(15, 0);
        $this->actingAs($staffUser)
            ->patch(route('staff.appointments.update', $appointment, false), [
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => $adjacentStart->toDateTimeString(),
            ])
            ->assertRedirect(route('staff.appointments.show', $appointment, false));

        $appointment->refresh();
        $this->assertTrue($appointment->scheduled_start_at->equalTo($adjacentStart));
        $this->assertTrue($appointment->scheduled_end_at->equalTo($blocker->scheduled_start_at));

        $this->actingAs($staffUser)
            ->from(route('staff.appointments.show', $appointment, false))
            ->patch(route('staff.appointments.update', $appointment, false), [
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => $blocker->scheduled_start_at->toDateTimeString(),
            ])
            ->assertRedirect(route('staff.appointments.show', $appointment, false))
            ->assertSessionHasErrors('scheduled_start_at');

        $this->assertTrue($appointment->fresh()->scheduled_start_at->equalTo($adjacentStart));
    }

    public function legacy_staff_rescheduling_enforces_opening_midnight_interval_and_future_boundaries(): void
    {
        $staffUser = User::factory()->staff()->create();
        $staff = StaffProfile::factory()->for($staffUser)->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $staff->services()->attach($service);
        $day = now()->addWeek()->startOfDay();
        $this->giveFullBusinessDay($staff, $day);
        $appointment = $this->confirmedAppointment($customer, $service, $staff, $day->copy()->setTime(18, 0));

        foreach ([
            $day->copy()->setTime(12, 30),
            $day->copy()->setTime(14, 15),
            $day->copy()->setTime(23, 30),
            now()->subDay()->setTime(14, 0, 0),
        ] as $invalidStart) {
            $originalStart = $appointment->fresh()->scheduled_start_at->copy();
            $this->actingAs($staffUser)
                ->patch(route('staff.appointments.update', $appointment, false), [
                    'status' => Appointment::STATUS_CONFIRMED,
                    'scheduled_start_at' => $invalidStart->toDateTimeString(),
                ])
                ->assertSessionHasErrors('scheduled_start_at');
            $this->assertTrue($appointment->fresh()->scheduled_start_at->equalTo($originalStart));
        }

        $opening = $day->copy()->setTime(13, 0);
        $this->actingAs($staffUser)
            ->patch(route('staff.appointments.update', $appointment, false), [
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => $opening->toDateTimeString(),
            ])
            ->assertRedirect(route('staff.appointments.show', $appointment, false));
        $this->assertTrue($appointment->fresh()->scheduled_start_at->equalTo($opening));

        $midnightEnding = $day->copy()->setTime(23, 0);
        $this->actingAs($staffUser)
            ->patch(route('staff.appointments.update', $appointment, false), [
                'status' => Appointment::STATUS_CONFIRMED,
                'scheduled_start_at' => $midnightEnding->toDateTimeString(),
            ])
            ->assertRedirect(route('staff.appointments.show', $appointment, false));

        $appointment->refresh();
        $this->assertTrue($appointment->scheduled_start_at->equalTo($midnightEnding));
        $this->assertTrue($appointment->scheduled_end_at->equalTo($day->copy()->addDay()->startOfDay()));
    }

    public function test_confirmed_scheduling_rejects_ineligible_therapists(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $day = now()->addWeek()->startOfDay();

        $wrongService = StaffProfile::factory()->create();
        $unbookable = StaffProfile::factory()->create(['is_bookable' => false]);
        $unbookable->services()->attach($service);
        $inactiveUser = User::factory()->staff()->inactive()->create();
        $inactive = StaffProfile::factory()->for($inactiveUser)->create();
        $inactive->services()->attach($service);

        foreach ([$wrongService, $unbookable, $inactive] as $staff) {
            $this->giveFullBusinessDay($staff, $day);
            $this->actingAs($admin)
                ->post(route('admin.appointments.store', absolute: false), [
                    'customer_profile_id' => $customer->id,
                    'service_id' => $service->id,
                    'staff_profile_id' => $staff->id,
                    'requested_start_at' => $day->copy()->setTime(14, 0)->toDateTimeString(),
                    'scheduled_start_at' => $day->copy()->setTime(14, 0)->toDateTimeString(),
                    'status' => Appointment::STATUS_CONFIRMED,
                ])
                ->assertSessionHasErrors('scheduled_start_at');
        }

        $deleted = StaffProfile::factory()->create();
        $deleted->services()->attach($service);
        $this->giveFullBusinessDay($deleted, $day);
        $deleted->delete();

        $this->actingAs($admin)
            ->post(route('admin.appointments.store', absolute: false), [
                'customer_profile_id' => $customer->id,
                'service_id' => $service->id,
                'staff_profile_id' => $deleted->id,
                'requested_start_at' => $day->copy()->setTime(14, 0)->toDateTimeString(),
                'scheduled_start_at' => $day->copy()->setTime(14, 0)->toDateTimeString(),
                'status' => Appointment::STATUS_CONFIRMED,
            ])
            ->assertSessionHasErrors('staff_profile_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_existing_inactive_service_and_deleted_therapist_can_be_preserved_without_reassignment(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $staff = StaffProfile::factory()->create();
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);
        $this->giveFullBusinessDay($staff, $start);
        $appointment = $this->confirmedAppointment($customer, $service, $staff, $start);
        $service->update(['is_active' => false]);
        $staff->delete();

        $this->actingAs($admin)
            ->get(route('admin.appointments.edit', $appointment, false))
            ->assertOk()
            ->assertSee($staff->user->name)
            ->assertSee('persistedStaffId', false);

        $this->actingAs($admin)
            ->patch(route('admin.appointments.update', $appointment, false), $this->adminPayload($appointment, [
                'status' => Appointment::STATUS_CONFIRMED,
                'internal_notes' => 'Historical assignment retained.',
            ]))
            ->assertRedirect(route('admin.appointments.show', $appointment, false));

        $appointment->refresh();
        $this->assertSame($service->id, $appointment->service_id);
        $this->assertSame($staff->id, $appointment->staff_profile_id);
        $this->assertSame('Historical assignment retained.', $appointment->internal_notes);
        $this->assertTrue($appointment->staffProfile->trashed());
    }

    public function test_terminal_appointment_structure_is_immutable_but_notes_remain_editable(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $staff = StaffProfile::factory()->create();
        $start = now()->subDay()->setTime(14, 0, 0);
        $appointment = $this->confirmedAppointment($customer, $service, $staff, $start);
        $appointment->forceFill([
            'status' => Appointment::STATUS_COMPLETED,
            'completed_at' => $start->copy()->addHour(),
        ])->save();

        $this->actingAs($admin)
            ->patch(route('admin.appointments.update', $appointment, false), $this->adminPayload($appointment, [
                'status' => Appointment::STATUS_COMPLETED,
                'internal_notes' => 'Corrected historical note.',
            ]))
            ->assertRedirect(route('admin.appointments.show', $appointment, false));
        $this->assertSame('Corrected historical note.', $appointment->fresh()->internal_notes);

        $changedStart = $start->copy()->addDay();
        $this->actingAs($admin)
            ->patch(route('admin.appointments.update', $appointment, false), $this->adminPayload($appointment->fresh(), [
                'status' => Appointment::STATUS_COMPLETED,
                'scheduled_start_at' => $changedStart->toDateTimeString(),
            ]))
            ->assertSessionHasErrors('status');
        $this->assertTrue($appointment->fresh()->scheduled_start_at->equalTo($start));
    }

    public function test_appointment_number_collision_is_retried_for_customer_creation(): void
    {
        $customerUser = User::factory()->customer()->create();
        $customer = CustomerProfile::factory()->for($customerUser)->create();
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $staff = StaffProfile::factory()->create();
        $staff->services()->attach($service);
        $start = now()->addWeek()->setTime(14, 0, 0);
        $this->giveFullBusinessDay($staff, $start);

        Appointment::factory()->create(['appointment_number' => 'APT-FORCED-COLLISION']);

        $generator = new class extends AppointmentNumberGenerator
        {
            public int $calls = 0;

            public function next(): string
            {
                $this->calls++;

                return $this->calls === 1 ? 'APT-FORCED-COLLISION' : 'APT-RETRY-SUCCEEDED';
            }
        };
        $this->app->instance(AppointmentNumberGenerator::class, $generator);

        $this->actingAs($customerUser)
            ->post(route('customer.appointments.store', absolute: false), [
                'service_id' => $service->id,
                'requested_start_at' => $start->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertSame(2, $generator->calls);
        $this->assertDatabaseHas('appointments', [
            'customer_profile_id' => $customer->id,
            'appointment_number' => 'APT-RETRY-SUCCEEDED',
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
    }

    public function test_appointment_numbers_use_a_compact_global_sequence(): void
    {
        Appointment::factory()->create(['appointment_number' => 'APT-0001']);

        $this->assertSame('APT-0002', app(AppointmentNumberGenerator::class)->next());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function adminPayload(Appointment $appointment, array $overrides = []): array
    {
        return [
            'customer_profile_id' => $appointment->customer_profile_id,
            'service_id' => $appointment->service_id,
            'staff_profile_id' => $appointment->staff_profile_id,
            'preferred_staff_profile_id' => $appointment->preferred_staff_profile_id,
            'requested_start_at' => $appointment->requested_start_at?->toDateTimeString(),
            'scheduled_start_at' => $appointment->scheduled_start_at?->toDateTimeString(),
            'status' => $appointment->status,
            'customer_notes' => $appointment->customer_notes,
            'internal_notes' => $appointment->internal_notes,
            ...$overrides,
        ];
    }

    private function giveFullBusinessDay(StaffProfile $staff, Carbon $day): void
    {
        StaffWeeklySchedule::factory()->for($staff)->create([
            'day_of_week' => $day->dayOfWeek,
            'start_time' => '13:00:00',
            'end_time' => '00:00:00',
            'ends_next_day' => true,
            'is_available' => true,
        ]);
    }

    private function confirmedAppointment(
        CustomerProfile $customer,
        Service $service,
        StaffProfile $staff,
        Carbon $start,
    ): Appointment {
        return Appointment::factory()
            ->for($customer)
            ->for($service)
            ->for($staff, 'staffProfile')
            ->create([
                'status' => Appointment::STATUS_CONFIRMED,
                'requested_start_at' => $start,
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $start->copy()->addMinutes($service->duration_minutes),
                'confirmed_at' => $start->copy()->subHour(),
            ]);
    }
}
