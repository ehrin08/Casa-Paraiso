<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GuestCustomerBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_operations_create_trimmed_guests_with_optional_phone_without_login_accounts(): void
    {
        [$service, $staff] = $this->team();
        foreach ([['admin', 'admin'], ['admin', 'super_admin'], ['reception', 'receptionist']] as $index => [$workspace, $role]) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $userCount = User::count();
            $date = '2026-10-'.(5 + $index);
            $this->post(route("{$workspace}.appointments.store"), $this->payload($service, $staff, "{$date}T14:00") + [
                'customer_name' => '  Walk-in '.$index.'  ',
                'customer_phone' => $index ? ' 09123456789 ' : '',
            ])->assertSessionHasNoErrors()->assertRedirect();
            $guest = CustomerProfile::where('guest_name', 'Walk-in '.$index)->sole();
            $this->assertNull($guest->user_id);
            $this->assertSame($index ? '09123456789' : null, $guest->display_phone);
            $this->assertStringStartsWith('CP-', $guest->customer_code);
            $this->assertSame($userCount, User::count());
            $this->assertSame($guest->id, Appointment::latest('id')->first()->customer_profile_id);
        }
    }

    public function test_explicit_guest_and_enrolled_selection_reuses_identity_without_contact_updates(): void
    {
        [$service, $staff] = $this->team();
        $guest = CustomerProfile::createGuest('Shared Name', '111');
        $enrolled = CustomerProfile::factory()->create(['user_id' => User::factory()->customer()->create(['name' => 'Shared Name', 'phone' => '222'])->id]);
        $this->actingAs(User::factory()->admin()->create());
        $count = CustomerProfile::count();
        foreach ([$guest, $enrolled] as $index => $profile) {
            $this->post(route('admin.appointments.store'), $this->payload($service, $staff, '2026-10-05T'.($index ? '16:00' : '14:00')) + [
                'customer_profile_id' => $profile->id, 'customer_name' => 'Ignored typed name', 'customer_phone' => 'Changed contact',
            ])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame($profile->id, Appointment::latest('id')->first()->customer_profile_id);
        }
        $this->assertSame($count, CustomerProfile::count());
        $this->assertSame('111', $guest->fresh()->display_phone);
        $this->assertSame('222', $enrolled->fresh()->display_phone);
        $this->post(route('admin.appointments.store'), $this->payload($service, $staff, '2026-10-05T18:00') + ['customer_name' => 'Shared Name'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($count + 1, CustomerProfile::count());
        $this->assertNotContains(Appointment::latest('id')->first()->customer_profile_id, [$guest->id, $enrolled->id]);
    }

    public function test_rejected_or_invalid_bookings_leave_no_orphan_guest_profiles(): void
    {
        [$service, $staff] = $this->team();
        $this->actingAs(User::factory()->admin()->create());
        $payload = $this->payload($service, $staff, '2026-10-05T14:00');
        $this->post(route('admin.appointments.store'), $payload + ['customer_name' => 'Reserved'])->assertSessionHasNoErrors();
        $count = CustomerProfile::count();
        $this->post(route('admin.appointments.store'), $payload + ['customer_name' => 'Rejected guest'])->assertSessionHasErrors('scheduled_start_at');
        $this->assertSame($count, CustomerProfile::count());
        $this->assertSame(1, Appointment::count());
        foreach ([['customer_name' => '   '], ['customer_name' => str_repeat('x', 256)], ['customer_name' => 'Guest', 'customer_phone' => str_repeat('1', 51)]] as $invalid) {
            $this->post(route('admin.appointments.store'), $payload + $invalid)->assertSessionHasErrors();
        }
        $this->assertSame($count, CustomerProfile::count());
    }

    public function test_id_only_calendar_submissions_remain_compatible_and_customer_self_booking_cannot_create_guests(): void
    {
        [$service, $staff] = $this->team();
        $profile = CustomerProfile::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.appointments.calendar.store'), $this->payload($service, $staff, '2026-10-05T14:00') + ['customer_profile_id' => $profile->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $user = User::factory()->customer()->create();
        $self = CustomerProfile::provisionFor($user);
        $count = CustomerProfile::count();
        $this->actingAs($user)->post(route('customer.appointments.store'), [
            'service_id' => $service->id, 'requested_start_at' => '2026-10-05T16:00', 'customer_name' => 'Ignored guest',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($count, CustomerProfile::count());
        $this->assertSame($self->id, Appointment::latest('id')->first()->customer_profile_id);
    }

    public function test_lookup_is_role_scoped_limited_searchable_and_identifies_both_profile_types(): void
    {
        $guest = CustomerProfile::createGuest('Lookup Guest', '09110001111');
        $enrolled = CustomerProfile::factory()->create(['user_id' => User::factory()->customer()->create(['name' => 'Lookup Enrolled', 'phone' => '09220002222'])->id]);
        CustomerProfile::factory()->count(18)->create(['user_id' => null, 'guest_name' => 'Lookup Extra']);
        $deleted = CustomerProfile::createGuest('Lookup Deleted');
        $deleted->delete();
        foreach (['admin', 'super_admin', 'receptionist', 'customer', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['admin', 'reception'] as $workspace) {
                $response = $this->getJson(route("{$workspace}.customers.lookup", ['q' => 'Lookup']));
                $allowed = $workspace === 'admin' ? in_array($role, ['admin', 'super_admin']) : $role === 'receptionist';
                if ($allowed) {
                    $response->assertOk()->assertJsonCount(15, 'customers');
                    foreach ([[$guest, true], [$enrolled, false]] as [$profile, $isGuest]) {
                        $result = $this->getJson(route("{$workspace}.customers.lookup", ['q' => $profile->customer_code]))->assertOk()->json('customers.0');
                        $this->assertSame($profile->id, $result['id']);
                        $this->assertSame($profile->display_name, $result['name']);
                        $this->assertSame($isGuest, $result['is_guest']);
                        $this->assertSame($profile->display_phone, $result['phone']);
                    }
                    $this->getJson(route("{$workspace}.customers.lookup", ['q' => '0911000']))->assertJsonPath('customers.0.id', $guest->id);
                    $this->getJson(route("{$workspace}.customers.lookup", ['q' => 'Lookup Deleted']))->assertJsonCount(0, 'customers');
                    $this->getJson(route("{$workspace}.customers.lookup", ['q' => 'x']))->assertUnprocessable();
                } else {
                    $response->assertForbidden();
                }
            }
        }
        auth()->logout();
        $this->getJson(route('admin.customers.lookup', ['q' => 'Lookup']))->assertUnauthorized();
    }

    public function test_guest_history_payments_rewards_reports_and_calendar_display_and_search(): void
    {
        [$service, $staff] = $this->team();
        $guest = CustomerProfile::createGuest('Guest History', '09123456789');
        $appointment = Appointment::factory()->create([
            'customer_profile_id' => $guest->id, 'service_id' => $service->id, 'staff_profile_id' => $staff->id,
            'requested_start_at' => '2026-10-05 14:00', 'scheduled_start_at' => '2026-10-05 14:00', 'scheduled_end_at' => '2026-10-05 15:00',
        ]);
        $payment = Transaction::factory()->create(['customer_profile_id' => $guest->id, 'appointment_id' => $appointment->id, 'service_id' => $service->id]);
        $reward = PromotionSuggestion::factory()->create(['customer_profile_id' => $guest->id]);
        $feedback = Feedback::factory()->create(['customer_profile_id' => $guest->id, 'appointment_id' => $appointment->id, 'service_id' => $service->id]);
        foreach ([['admin', User::factory()->admin()->create()], ['reception', User::factory()->receptionist()->create()], ['staff', $staff->user]] as [$workspace, $user]) {
            $this->actingAs($user);
            $this->get(route("{$workspace}.customers.index", ['q' => 'Guest History']))->assertOk()->assertSee('Guest History');
            $this->get(route("{$workspace}.customers.show", $guest))->assertOk()->assertSee('Guest History')->assertSee('09123456789');
            $this->get(route("{$workspace}.appointments.show", $appointment))->assertOk()->assertSee('Guest History');
            $this->get(route("{$workspace}.transactions.index", ['q' => 'Guest History']))->assertOk()->assertSee('Guest History');
            $this->get(route("{$workspace}.transactions.show", $payment))->assertOk()->assertSee('Guest History');
        }
        $this->actingAs(User::factory()->admin()->create());
        foreach (['appointments', 'transactions', 'customers', 'promotions', 'feedback'] as $type) {
            $this->get(route('admin.reports.index', ['type' => $type, 'q' => 'Guest History', 'sort' => $type === 'customers' ? 'name' : 'customer']))->assertOk()->assertSee('Guest History');
            $csv = $this->get(route('admin.reports.export', ['type' => $type, 'q' => 'Guest History']))->assertOk()->streamedContent();
            $this->assertStringContainsString('Guest History', $csv);
        }
        $this->get(route('admin.promotions.index', ['q' => 'Guest History']))->assertOk()->assertSee('Guest History');
        $this->get(route('admin.promotions.show', $reward))->assertOk()->assertSee('Guest History');
        $this->get(route('admin.feedback.show', $feedback))->assertOk()->assertSee('Guest History');
        $this->assertStringContainsString('Guest History', $this->get(route('admin.transactions.export', ['q' => 'Guest History']))->assertOk()->streamedContent());
        $calendar = $this->getJson(route('admin.appointments.calendar', ['start' => '2026-10-04', 'end' => '2026-10-11']))->assertOk()->json();
        $this->assertStringContainsString('Guest History', json_encode($calendar));
    }

    public function test_reception_contact_edits_update_guest_profile_and_historical_notes_keep_identity(): void
    {
        [$service, $staff] = $this->team();
        $guest = CustomerProfile::createGuest('Historical Guest');
        $appointment = Appointment::factory()->create([
            'customer_profile_id' => $guest->id, 'service_id' => $service->id, 'staff_profile_id' => $staff->id,
            'requested_start_at' => '2026-10-03 14:00', 'scheduled_start_at' => '2026-10-03 14:00', 'scheduled_end_at' => '2026-10-03 15:00',
        ]);
        $this->actingAs(User::factory()->receptionist()->create());
        $this->patch(route('reception.customers.update', $guest), ['phone' => '09998887777', 'notes' => 'Guest contact'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('09998887777', $guest->fresh()->display_phone);
        $this->get(route('reception.appointments.edit', $appointment))->assertOk()->assertSee('Historical Guest')->assertSee('scheduleLocked: true', false);
        $this->patch(route('reception.appointments.update', $appointment), $this->payload($service, $staff, '2026-10-03T14:00') + ['customer_profile_id' => $guest->id, 'customer_notes' => 'Updated historical note'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($guest->id, $appointment->fresh()->customer_profile_id);
        $this->assertSame('Updated historical note', $appointment->fresh()->customer_notes);
        $count = CustomerProfile::count();
        $this->patch(route('reception.appointments.update', $appointment), $this->payload($service, $staff, '2026-10-03T14:00') + ['customer_name' => 'Rejected historical change'])
            ->assertSessionHasErrors('scheduled_start_at');
        $this->assertSame($count, CustomerProfile::count());
        $this->assertSame($guest->id, $appointment->fresh()->customer_profile_id);
    }

    private function team(): array
    {
        $service = Service::factory()->create(['duration_minutes' => 60, 'is_active' => true]);
        $staff = StaffProfile::factory()->create(['is_bookable' => true]);
        $staff->services()->attach($service);
        foreach (range(0, 6) as $day) {
            StaffWeeklySchedule::factory()->for($staff)->create(['day_of_week' => $day, 'start_time' => '13:00', 'end_time' => '00:00', 'ends_next_day' => true]);
        }

        return [$service, $staff];
    }

    private function payload(Service $service, StaffProfile $staff, string $start): array
    {
        return ['service_id' => $service->id, 'staff_profile_id' => $staff->id, 'scheduled_start_at' => $start, 'status' => Appointment::STATUS_CONFIRMED];
    }
}
