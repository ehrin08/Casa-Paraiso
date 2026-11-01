<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerOnlineBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_blocks_with_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = CustomerProfile::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.customers.online-booking.block', $customer, false), ['reason' => 'Repeated fake bookings'])
            ->assertRedirect(route('admin.customers.show', $customer, false))
            ->assertSessionHas('status', 'customer-online-booking-blocked');

        $customer->refresh();
        $this->assertNotNull($customer->online_booking_blocked_at);
        $this->assertSame('Repeated fake bookings', $customer->online_booking_block_reason);
        $this->assertSame($admin->id, $customer->online_booking_blocked_by);
    }

    public function test_reason_is_required(): void
    {
        $customer = CustomerProfile::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.customers.online-booking.block', $customer, false), ['reason' => '  '])
            ->assertSessionHasErrors('reason');

        $this->assertNull($customer->fresh()->online_booking_blocked_at);
    }

    public function test_super_admin_can_unblock(): void
    {
        $customer = $this->blockedCustomer();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]))
            ->patch(route('admin.customers.online-booking.unblock', $customer, false))
            ->assertRedirect(route('admin.customers.show', $customer, false))
            ->assertSessionHas('status', 'customer-online-booking-unblocked');

        $customer->refresh();
        $this->assertNull($customer->online_booking_blocked_at);
        $this->assertNull($customer->online_booking_block_reason);
        $this->assertNull($customer->online_booking_blocked_by);
    }

    public function test_receptionist_and_customer_cannot_block(): void
    {
        $customer = CustomerProfile::factory()->create();

        foreach ([User::factory()->receptionist()->create(), $customer->user] as $user) {
            $this->actingAs($user)
                ->patch(route('admin.customers.online-booking.block', $customer, false), ['reason' => 'Nope'])
                ->assertForbidden();
        }

        $this->assertNull($customer->fresh()->online_booking_blocked_at);
    }

    public function test_detail_page_shows_state(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ana Admin']);
        $blocked = $this->blockedCustomer($admin);

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $blocked, false))
            ->assertOk()
            ->assertSee('Online booking')
            ->assertSee('Blocked by admin')
            ->assertSee('Repeated fake bookings')
            ->assertSee('Ana Admin');

        $paused = CustomerProfile::factory()->create();
        foreach ([5, 20] as $daysAgo) {
            Appointment::factory()->for($paused)->create([
                'status' => Appointment::STATUS_NO_SHOW,
                'scheduled_start_at' => now()->subDays($daysAgo),
                'scheduled_end_at' => now()->subDays($daysAgo)->addHour(),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $paused, false))
            ->assertOk()
            ->assertSee('Paused by no-shows');

        $this->actingAs($admin)
            ->get(route('admin.customers.show', CustomerProfile::factory()->create(), false))
            ->assertOk()
            ->assertSee('Allowed');
    }

    public function test_detail_page_shows_upcoming_limit_reached(): void
    {
        $customer = CustomerProfile::factory()->create();
        foreach ([2, 3] as $days) {
            Appointment::factory()->for($customer)->create([
                'scheduled_start_at' => now()->addDays($days),
                'scheduled_end_at' => now()->addDays($days)->addHour(),
                'created_by' => $customer->user_id,
            ]);
        }

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.customers.show', $customer, false))
            ->assertOk()
            ->assertSee('Upcoming booking limit reached')
            ->assertDontSee('can book online within the normal limits');
    }

    public function test_list_recovery_modal_reports_no_show_pause(): void
    {
        $customer = CustomerProfile::factory()->create();
        foreach ([5, 20] as $daysAgo) {
            Appointment::factory()->for($customer)->create([
                'status' => Appointment::STATUS_NO_SHOW,
                'scheduled_start_at' => now()->subDays($daysAgo),
                'scheduled_end_at' => now()->subDays($daysAgo)->addHour(),
            ]);
        }

        $this->actingAs(User::factory()->admin()->create())
            ->withSession(['customer_modal_id' => $customer->id])
            ->get(route('admin.customers.index', absolute: false))
            ->assertOk()
            ->assertSee('Paused by no-shows');
    }

    public function test_modal_detail_shows_card(): void
    {
        $customer = $this->blockedCustomer();

        $this->actingAs(User::factory()->admin()->create())
            ->withHeader('X-Casa-Panel', '1')
            ->get(route('admin.customers.show', ['customer' => $customer, 'modal' => 1], false))
            ->assertOk()
            ->assertSee('Online booking')
            ->assertSee('Blocked by admin');
    }

    public function test_reception_sees_badge(): void
    {
        $customer = $this->blockedCustomer();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('reception.customers.show', $customer, false))
            ->assertOk()
            ->assertSee('Online booking paused')
            ->assertDontSee('Repeated fake bookings');
    }

    private function blockedCustomer(?User $admin = null): CustomerProfile
    {
        return CustomerProfile::factory()->create([
            'online_booking_blocked_at' => now(),
            'online_booking_block_reason' => 'Repeated fake bookings',
            'online_booking_blocked_by' => ($admin ?? User::factory()->admin()->create())->id,
        ]);
    }
}
