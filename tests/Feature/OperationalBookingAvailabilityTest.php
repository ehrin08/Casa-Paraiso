<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\PromotionSuggestion;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\StaffScheduleException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class OperationalBookingAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-04 19:10:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_the_matching_operations_roles_can_access_month_availability(): void
    {
        [$service] = $this->bookableTeam();
        foreach (['admin', 'super_admin', 'receptionist', 'customer', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            foreach (['admin', 'reception'] as $workspace) {
                $response = $this->actingAs($user)->getJson(route("{$workspace}.appointments.availability", ['service_id' => $service->id, 'month' => '2026-10']));
                $allowed = $workspace === 'admin' ? in_array($role, ['admin', 'super_admin']) : $role === 'receptionist';
                $allowed ? $response->assertOk() : $response->assertForbidden();
            }
        }
    }

    public function test_operations_use_future_slots_without_the_customer_lead_time(): void
    {
        [$service] = $this->bookableTeam();
        $this->actingAs(User::factory()->admin()->create());
        $slots = collect($this->getJson(route('admin.appointments.availability', ['service_id' => $service->id, 'month' => '2026-10']))->assertOk()->json('dates.2026-10-04'));
        $this->assertFalse($slots->contains('time', '19:00'));
        $this->assertTrue($slots->contains('time', '19:30'));
        $this->assertTrue($slots->contains('time', '23:00'));
        $this->assertFalse($slots->contains('time', '23:30'));
        $this->assertSame('2026-10-05 00:00:00', $slots->firstWhere('time', '23:00')['ends_at']);
        $this->actingAs(User::factory()->customer()->create());
        $customerSlots = collect($this->getJson(route('customer.appointments.availability', ['service_id' => $service->id, 'month' => '2026-10']))->assertOk()->json('dates.2026-10-04'));
        $this->assertFalse($customerSlots->contains('time', '19:30'));
    }

    public function test_future_store_dates_remain_open_without_any_eligible_therapist(): void
    {
        $service = Service::factory()->create(['duration_minutes' => 60]);
        $this->actingAs(User::factory()->admin()->create());
        $response = $this->getJson(route('admin.appointments.availability', ['service_id' => $service->id, 'month' => '2026-10']))->assertOk();
        $this->assertSame([], $response->json('dates'));
        $this->assertNull($response->json('store_dates.2026-10-03'));
        $response->assertJsonPath('store_dates.2026-10-05.opens_at', '2026-10-05 13:00:00')
            ->assertJsonPath('store_dates.2026-10-05.closes_at', '2026-10-06 00:00:00')
            ->assertJsonCount(22, 'store_dates.2026-10-05.slots');
        $this->assertTrue(collect($response->json('store_dates.2026-10-05.slots'))->every(fn ($slot) => $slot['staff_count'] === 0));
    }

    public function test_store_starts_include_capacity_lead_time_duration_and_closing_rejections(): void
    {
        [$service, $staff] = $this->bookableTeam();
        $this->reservation($service, $staff, '2026-10-05 14:00');
        $this->actingAs(User::factory()->admin()->create());
        $params = ['service_id' => $service->id, 'month' => '2026-10'];
        $response = $this->getJson(route('admin.appointments.availability', $params))->assertOk();
        $today = collect($response->json('store_dates.2026-10-04.slots'));
        $future = collect($response->json('store_dates.2026-10-05.slots'));
        $this->assertSame(0, $today->firstWhere('time', '19:00')['staff_count']);
        $this->assertSame(1, $today->firstWhere('time', '19:30')['staff_count']);
        $this->assertSame(0, $future->firstWhere('time', '14:00')['staff_count']);
        $this->assertSame(0, $future->firstWhere('time', '13:30')['staff_count']);
        $this->assertSame(1, $future->firstWhere('time', '23:00')['staff_count']);
        $this->assertSame('2026-10-06 00:00:00', $future->firstWhere('time', '23:00')['ends_at']);
        $this->assertSame(0, $future->firstWhere('time', '23:30')['staff_count']);
        $this->actingAs(User::factory()->customer()->create());
        $customer = collect($this->getJson(route('customer.appointments.availability', $params))->assertOk()->json('store_dates.2026-10-04.slots'));
        $this->assertSame(0, $customer->firstWhere('time', '19:30')['staff_count']);
        $this->assertSame(1, $customer->firstWhere('time', '20:00')['staff_count']);
        $addon = Addon::query()->create(['code' => 'store-duration', 'name' => 'Extra time', 'price' => 299, 'duration_minutes' => 30, 'is_active' => true]);
        $this->actingAs(User::factory()->admin()->create());
        $extended = collect($this->getJson(route('admin.appointments.availability', [...$params, 'addon_codes' => [$addon->code]]))->assertOk()->json('store_dates.2026-10-05.slots'));
        $this->assertSame(1, $extended->firstWhere('time', '22:30')['staff_count']);
        $this->assertSame(0, $extended->firstWhere('time', '23:00')['staff_count']);
    }

    public function test_unavailable_roster_exception_disables_times_without_closing_the_store_date(): void
    {
        [$service, $staff] = $this->bookableTeam();
        StaffScheduleException::factory()->for($staff)->create(['exception_date' => '2026-10-05']);
        $this->actingAs(User::factory()->admin()->create());
        $response = $this->getJson(route('admin.appointments.availability', ['service_id' => $service->id, 'month' => '2026-10']))->assertOk();
        $this->assertNull($response->json('dates.2026-10-05'));
        $response->assertJsonCount(22, 'store_dates.2026-10-05.slots');
        $this->assertTrue(collect($response->json('store_dates.2026-10-05.slots'))->every(fn ($slot) => $slot['staff_count'] === 0));
    }

    public function test_edit_exclusion_preserves_its_slot_but_does_not_ignore_other_bookings(): void
    {
        [$service, $staff] = $this->bookableTeam();
        $appointment = $this->reservation($service, $staff, '2026-10-05 14:00');
        $this->reservation($service, $staff, '2026-10-05 16:00');
        foreach ([['admin', 'admin'], ['reception', 'receptionist']] as [$workspace, $role]) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $params = ['service_id' => $service->id, 'month' => '2026-10'];
            $create = collect($this->getJson(route("{$workspace}.appointments.availability", $params))->assertOk()->json('dates.2026-10-05'));
            $edit = collect($this->getJson(route("{$workspace}.appointments.availability", [...$params, 'appointment_id' => $appointment->id]))->assertOk()->json('dates.2026-10-05'));
            $this->assertFalse($create->contains('time', '14:00'));
            $this->assertTrue($edit->contains('time', '14:00'));
            $this->assertFalse($edit->contains('time', '16:00'));
            $this->assertFalse($edit->contains('time', '15:30'));
            $this->assertSame(1, $edit->firstWhere('time', '14:00')['staff_count']);
        }
    }

    public function test_addon_and_existing_voucher_duration_are_included_in_month_and_therapist_checks(): void
    {
        [$service, $staff] = $this->bookableTeam();
        $back = Addon::query()->create(['code' => 'calendar-extra', 'name' => 'Extra care', 'price' => 299, 'duration_minutes' => 30, 'is_active' => true]);
        $this->actingAs(User::factory()->admin()->create());
        $slots = collect($this->getJson(route('admin.appointments.availability', ['service_id' => $service->id, 'month' => '2026-10', 'addon_codes' => [$back->code]]))->assertOk()->json('dates.2026-10-05'));
        $this->assertTrue($slots->contains('time', '22:30'));
        $this->assertFalse($slots->contains('time', '23:00'));
        $appointment = $this->reservation($service, $staff, '2026-10-05 14:00');
        $voucher = PromotionSuggestion::factory()->create(['customer_profile_id' => $appointment->customer_profile_id, 'addon_code' => $back->code]);
        $appointment->update(['promotion_suggestion_id' => $voucher->id, 'scheduled_end_at' => '2026-10-05 15:30:00']);
        $params = ['service_id' => $service->id, 'month' => '2026-10', 'appointment_id' => $appointment->id];
        $voucherSlots = collect($this->getJson(route('admin.appointments.availability', $params))->assertOk()->json('dates.2026-10-05'));
        $this->assertFalse($voucherSlots->contains('time', '23:00'));
        $this->reservation($service, $staff, '2026-10-05 16:00');
        foreach ([['admin', 'admin'], ['reception', 'receptionist']] as [$workspace, $role]) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->getJson(route("{$workspace}.appointments.available-therapists", ['service_id' => $service->id, 'appointment_id' => $appointment->id, 'starts_at' => '2026-10-05T15:00']))->assertOk()->assertJsonCount(0, 'therapists');
        }
    }

    public function test_invalid_month_service_appointment_and_addons_are_rejected(): void
    {
        [$service] = $this->bookableTeam();
        $this->actingAs(User::factory()->admin()->create());
        $params = ['service_id' => $service->id, 'month' => '2026-10'];
        foreach ([['month' => '2026-13'], ['appointment_id' => 999999], ['addon_codes' => ['unknown']], ['addon_codes' => ['back-massage', 'back-massage']]] as $invalid) {
            $this->getJson(route('admin.appointments.availability', [...$params, ...$invalid]))->assertUnprocessable();
        }
        $service->update(['is_active' => false]);
        $this->getJson(route('admin.appointments.availability', $params))->assertUnprocessable()->assertJsonValidationErrors('service_id');
    }

    public function test_an_existing_inactive_service_is_retained_and_terminal_forms_lock_booking_fields(): void
    {
        [$service, $staff] = $this->bookableTeam();
        $appointment = $this->reservation($service, $staff, '2026-10-05 14:00');
        $service->update(['is_active' => false]);
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson(route('admin.appointments.availability', ['service_id' => $service->id, 'month' => '2026-10', 'appointment_id' => $appointment->id]))->assertOk();
        $appointment->update(['status' => Appointment::STATUS_COMPLETED]);
        $this->get(route('admin.appointments.edit', $appointment))->assertOk()->assertSee('Booking details are read-only')->assertSee('scheduleLocked: true', false)->assertSee('name="customer_notes"', false);
        $historical = $this->reservation($service, $staff, '2026-10-03 14:00');
        $this->get(route('admin.appointments.edit', $historical))->assertOk()->assertSee('scheduleLocked: true', false);
        $this->patch(route('admin.appointments.update', $historical), ['customer_profile_id' => $historical->customer_profile_id, 'service_id' => $service->id, 'staff_profile_id' => $staff->id, 'scheduled_start_at' => '2026-10-03T14:00', 'status' => Appointment::STATUS_CONFIRMED, 'customer_notes' => 'Historical note'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Historical note', $historical->fresh()->customer_notes);
        $this->assertSame('2026-10-03 14:00', $historical->fresh()->scheduled_start_at->format('Y-m-d H:i'));
    }

    public function test_errors_open_the_earliest_step_with_values_preserved_for_each_role(): void
    {
        foreach ([['admin', 'admin'], ['reception', 'receptionist'], ['customer', 'customer']] as [$workspace, $role]) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach ([['service_id', 1], [$workspace === 'customer' ? 'requested_start_at' : 'scheduled_start_at', 2], ['customer_notes', 3]] as [$field, $step]) {
                $this->withSession(['errors' => (new ViewErrorBag)->put('default', new MessageBag([$field => 'Check this value.'])), '_old_input' => ['customer_notes' => 'Please use gentle pressure']])
                    ->get(route("{$workspace}.appointments.create"))->assertOk()->assertSee("initialStep: {$step}", false)->assertSee('Please use gentle pressure');
            }
        }
    }

    public function test_edits_retain_an_inactive_therapist_for_an_unchanged_schedule_in_both_workspaces(): void
    {
        [$service, $staff] = $this->bookableTeam();
        $staff->user->update(['name' => 'Retained therapist', 'is_active' => false]);
        $appointment = $this->reservation($service, $staff, '2026-10-05 14:00');
        $staff->delete();
        foreach ([['admin', 'admin'], ['reception', 'receptionist']] as [$workspace, $role]) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route("{$workspace}.appointments.edit", $appointment))->assertOk()->assertSee('Retained therapist');
            $this->patch(route("{$workspace}.appointments.update", $appointment), [
                'customer_profile_id' => $appointment->customer_profile_id, 'service_id' => $service->id,
                'staff_profile_id' => $staff->id, 'scheduled_start_at' => '2026-10-05T14:00',
                'status' => Appointment::STATUS_CONFIRMED, 'customer_notes' => 'Keep the existing reservation',
            ])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame($staff->id, $appointment->fresh()->staff_profile_id);
        }
    }

    public function test_wizard_can_clear_all_addons_while_older_notes_payloads_retain_them(): void
    {
        [$service, $staff] = $this->bookableTeam();
        $addon = Addon::query()->create(['code' => 'calendar-extra', 'name' => 'Extra care', 'price' => 299, 'duration_minutes' => 30, 'is_active' => true]);
        foreach ([['admin', 'admin'], ['reception', 'receptionist']] as [$workspace, $role]) {
            $date = $workspace === 'admin' ? '2026-10-06' : '2026-10-07';
            $appointment = $this->reservation($service, $staff, "{$date} 14:00");
            $appointment->addons()->create(['addon_code' => $addon->code, 'addon_name' => $addon->name, 'price' => 299, 'duration_minutes' => 30]);
            $appointment->update(['scheduled_end_at' => "{$date} 15:30"]);
            $payload = ['customer_profile_id' => $appointment->customer_profile_id, 'service_id' => $service->id, 'staff_profile_id' => $staff->id, 'scheduled_start_at' => "{$date}T14:00", 'status' => Appointment::STATUS_CONFIRMED, 'internal_notes' => 'Notes only'];
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->patch(route("{$workspace}.appointments.update", $appointment), $payload)->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame(1, $appointment->fresh()->addons()->count());
            $this->patch(route("{$workspace}.appointments.update", $appointment), [...$payload, '_booking_addons_present' => '1'])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame(0, $appointment->fresh()->addons()->count());
            $this->assertSame('15:00', $appointment->fresh()->scheduled_end_at->format('H:i'));
        }
    }

    private function bookableTeam(): array
    {
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staff = StaffProfile::factory()->create(['is_bookable' => true]);
        $staff->services()->attach($service);
        foreach (range(0, 6) as $day) {
            StaffWeeklySchedule::factory()->for($staff)->create(['day_of_week' => $day, 'start_time' => '13:00:00', 'end_time' => '00:00:00', 'ends_next_day' => true]);
        }

        return [$service, $staff];
    }

    private function reservation(Service $service, StaffProfile $staff, string $start): Appointment
    {
        $time = Carbon::parse($start, 'Asia/Manila');

        return Appointment::factory()->create(['customer_profile_id' => CustomerProfile::factory(), 'service_id' => $service->id, 'staff_profile_id' => $staff->id, 'status' => Appointment::STATUS_CONFIRMED, 'requested_start_at' => $time, 'scheduled_start_at' => $time, 'scheduled_end_at' => $time->copy()->addHour()]);
    }
}
