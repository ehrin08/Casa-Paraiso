<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModalConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reception_finish_and_outcomes_open_in_modals(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_start_at' => now()->subHour(),
            'scheduled_end_at' => now()->subMinutes(15),
        ]);
        $completionModal = 'reception-appointment-completion-'.$appointment->id;

        $this->actingAs($receptionist)
            ->get(route('reception.appointments.show', $appointment))
            ->assertOk()
            ->assertSee('data-modal-name="'.$completionModal.'"', false)
            ->assertSee(route('reception.appointments.complete', $appointment), false)
            ->assertSee('Mark this visit as no-show?')
            ->assertSee('Cancel this appointment?')
            ->assertSee('Keep appointment')
            ->assertDontSee('Record outcome');
    }

    public function test_failed_reception_finish_reopens_its_modal(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_start_at' => now()->subHour(),
            'scheduled_end_at' => now()->subMinutes(15),
        ]);
        $completionModal = 'reception-appointment-completion-'.$appointment->id;
        $show = route('reception.appointments.show', $appointment);

        $this->actingAs($receptionist)
            ->from($show)
            ->post(route('reception.appointments.complete', $appointment), ['_modal' => $completionModal, 'amount' => -1, 'payment_status' => 'paid'])
            ->assertRedirect($show)
            ->assertSessionHasErrors('amount');

        $this->actingAs($receptionist)->get($show)->assertOk()
            ->assertSee('initialShow: true', false)
            ->assertSee('value="-1"', false);
        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->fresh()->status);
    }

    public function test_reception_outcome_confirmation_records_the_reason(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $appointment = Appointment::factory()->create(['status' => Appointment::STATUS_CONFIRMED]);

        $this->actingAs($receptionist)
            ->patch(route('reception.appointments.outcome', $appointment), ['status' => Appointment::STATUS_CANCELLED, 'reason' => 'Customer called'])
            ->assertRedirect(route('reception.appointments.show', $appointment));

        $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->fresh()->status);
    }

    public function test_account_deletion_is_confirmed_in_a_modal_that_reopens_on_a_wrong_password(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)->get(route('profile.edit'))->assertOk()
            ->assertSee('data-modal-name="confirm-account-deletion"', false)
            ->assertSee('Delete your account?')
            ->assertSee('Keep my account');

        $this->actingAs($customer)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['_modal' => 'confirm-account-deletion', 'password' => 'wrong-password'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('password');

        $this->actingAs($customer)->get(route('profile.edit'))->assertOk()
            ->assertSee('initialShow: true', false)
            ->assertSee('The password is incorrect.');
        $this->assertNotNull($customer->fresh()->password);
    }
}
