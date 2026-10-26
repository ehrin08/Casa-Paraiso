<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplexCrudPageNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_therapist_service_and_schedule_forms_open_in_form_panels_with_page_fallbacks(): void
    {
        $superAdmin = User::factory()->create([
            'email' => config('auth.super_admin_email'),
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $staffProfile = StaffProfile::factory()->create();
        $service = Service::factory()->create();
        $scheduleException = StaffScheduleException::factory()->for($staffProfile)->create();

        $staffIndex = $this->actingAs($superAdmin)
            ->get(route('admin.staff.index', absolute: false));
        $staffIndex
            ->assertOk()
            ->assertSee(route('admin.staff.create', absolute: false), false)
            ->assertSee(route('admin.staff.edit', $staffProfile, false), false)
            ->assertDontSee(route('admin.services.create', absolute: false), false)
            ->assertDontSee(route('admin.services.edit', $service, false), false)
            ->assertDontSee('admin-staff-create', false);
        $this->assertPanelLink($staffIndex->getContent(), route('admin.staff.create', absolute: false));
        $this->assertPanelLink($staffIndex->getContent(), route('admin.staff.edit', $staffProfile, false));

        $servicesIndex = $this->actingAs($superAdmin)
            ->get(route('admin.services.index', absolute: false));
        $servicesIndex
            ->assertOk()
            ->assertSee(route('admin.services.create', absolute: false), false)
            ->assertSee(route('admin.services.edit', $service, false), false)
            ->assertDontSee('admin-service-create', false);
        $this->assertPanelLink($servicesIndex->getContent(), route('admin.services.create', absolute: false));
        $this->assertPanelLink($servicesIndex->getContent(), route('admin.services.edit', $service, false));

        $staffShow = $this->actingAs($superAdmin)
            ->get(route('admin.staff.show', $staffProfile, false));
        $staffShow
            ->assertOk()
            ->assertSee('Working hours and booking conflicts')
            ->assertSee(route('admin.staff-roster.index', ['staff_profile_id' => $staffProfile->id], false), false)
            ->assertSee(route('admin.staff.schedule-exceptions.create', $staffProfile, false), false)
            ->assertSee(route('admin.staff.schedule-exceptions.edit', [$staffProfile, $scheduleException], false), false)
            ->assertDontSee('admin-staff-shift-create', false)
            ->assertDontSee('admin-staff-exception-create', false);
        $this->assertPanelLink($staffShow->getContent(), route('admin.staff.schedule-exceptions.create', $staffProfile, false));
        $this->assertPanelLink($staffShow->getContent(), route('admin.staff.schedule-exceptions.edit', [$staffProfile, $scheduleException], false));

        foreach ([
            route('admin.staff.create', absolute: false),
            route('admin.services.edit', $service, false),
            route('admin.staff.schedule-exceptions.edit', [$staffProfile, $scheduleException], false),
        ] as $fallbackPage) {
            $this->actingAs($superAdmin)->get($fallbackPage)->assertOk()->assertSee('data-page-content', false);
        }
    }

    public function test_payment_forms_open_in_form_panels_while_completion_stays_contextual(): void
    {
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->receptionist()->create();
        $transaction = Transaction::factory()->for($admin, 'recorder')->create();
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_COMPLETED,
        ]);
        $completionAppointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_start_at' => now()->subHour(),
            'scheduled_end_at' => now()->subMinutes(15),
        ]);

        $paymentsIndex = $this->actingAs($admin)
            ->get(route('admin.transactions.index', absolute: false));
        $paymentsIndex
            ->assertOk()
            ->assertSee(route('admin.transactions.create', absolute: false), false)
            ->assertSee(route('admin.transactions.edit', $transaction, false), false)
            ->assertDontSee('admin-transaction-create', false)
            ->assertDontSee('admin-transaction-edit-'.$transaction->id, false);
        $this->assertPanelLink($paymentsIndex->getContent(), route('admin.transactions.create', absolute: false));
        $this->assertPanelLink($paymentsIndex->getContent(), route('admin.transactions.edit', $transaction, false));

        $receptionPayments = $this->actingAs($receptionist)
            ->get(route('reception.transactions.index', absolute: false));
        $receptionPayments
            ->assertOk()
            ->assertSee(route('reception.transactions.create', absolute: false), false)
            ->assertDontSee('reception-transaction-create', false);
        $this->assertPanelLink($receptionPayments->getContent(), route('reception.transactions.create', absolute: false));

        $this->actingAs($admin)
            ->get(route('admin.appointments.show', $appointment, false))
            ->assertOk()
            ->assertSee(route('admin.appointments.edit', $appointment, false), false)
            ->assertSee(route('admin.transactions.create', ['appointment_id' => $appointment], false), false)
            ->assertDontSee('admin-appointment-edit-'.$appointment->id, false)
            ->assertDontSee('admin-appointment-payment-'.$appointment->id, false);

        $this->actingAs($admin)
            ->get(route('admin.appointments.show', $completionAppointment, false))
            ->assertOk()
            ->assertSee('admin-appointment-completion-'.$completionAppointment->id, false);
    }

    public function test_appointment_payment_page_prefills_the_linked_record(): void
    {
        $admin = User::factory()->admin()->create();
        $appointment = Appointment::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.transactions.create', ['appointment_id' => $appointment], false))
            ->assertOk()
            ->assertSee('option value="'.$appointment->id.'" selected', false)
            ->assertSee('option value="'.$appointment->customer_profile_id.'" selected', false)
            ->assertSee('option value="'.$appointment->service_id.'" selected', false)
            ->assertSee('value="'.number_format((float) $appointment->expectedAmount(), 2, '.', '').'"', false);
    }

    private function assertPanelLink(string $html, string $path): void
    {
        $this->assertMatchesRegularExpression(
            '#<a data-panel-link data-turbo="false" aria-haspopup="dialog" href="[^"]*'.preg_quote(e($path), '#').'"#',
            $html,
            "Expected a form-panel link to {$path}."
        );
    }
}
