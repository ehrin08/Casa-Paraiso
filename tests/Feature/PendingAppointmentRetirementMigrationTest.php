<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatusLog;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingAppointmentRetirementMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_appointments_are_cancelled_once_with_a_system_audit_log(): void
    {
        $customer = CustomerProfile::factory()->create();
        $service = Service::factory()->create();
        $preferredStaff = StaffProfile::factory()->create();
        $appointment = Appointment::factory()
            ->for($customer)
            ->for($service)
            ->for($preferredStaff, 'preferredStaffProfile')
            ->create([
                'status' => 'pending',
                'staff_profile_id' => null,
                'requested_start_at' => now()->addDay()->setTime(14, 0),
                'scheduled_start_at' => null,
                'scheduled_end_at' => null,
                'confirmed_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
            ]);

        $migration = require database_path('migrations/2026_07_14_000000_retire_pending_appointment_status.php');
        $migration->up();

        $retired = $appointment->fresh();
        $this->assertSame(Appointment::STATUS_CANCELLED, $retired->status);
        $this->assertNull($retired->staff_profile_id);
        $this->assertNull($retired->scheduled_start_at);
        $this->assertNull($retired->scheduled_end_at);
        $this->assertNull($retired->confirmed_at);
        $this->assertNull($retired->completed_at);
        $this->assertNotNull($retired->cancelled_at);
        $this->assertNull($retired->cancelled_by);
        $this->assertSame($preferredStaff->id, $retired->preferred_staff_profile_id);

        $this->assertDatabaseHas('appointment_status_logs', [
            'appointment_id' => $appointment->id,
            'from_status' => 'pending',
            'to_status' => Appointment::STATUS_CANCELLED,
            'changed_by' => null,
            'reason' => 'Pending workflow retired during appointment-state migration.',
        ]);

        $migration->up();

        $this->assertSame(1, AppointmentStatusLog::query()
            ->where('appointment_id', $appointment->id)
            ->where('reason', 'Pending workflow retired during appointment-state migration.')
            ->count());
    }
}
