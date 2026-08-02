<?php

namespace Tests\Feature;

use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_integrity_audit_passes_for_consistent_records(): void
    {
        Appointment::factory()->confirmed()->create();

        $this->artisan('casa:audit-crud-integrity')
            ->assertSuccessful();
    }

    public function test_integrity_audit_fails_without_changing_orphaned_records(): void
    {
        $appointment = Appointment::factory()->confirmed()->create();
        $staffProfileId = $appointment->staff_profile_id;

        $appointment->staffProfile()->firstOrFail()->delete();

        $this->artisan('casa:audit-crud-integrity')
            ->expectsOutputToContain('Active appointments assigned to deleted or missing staff: 1')
            ->assertFailed();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'staff_profile_id' => $staffProfileId,
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
    }

    public function test_integrity_audit_rejects_retired_pending_statuses_without_changing_records(): void
    {
        $appointment = Appointment::factory()->create(['status' => 'pending']);

        $this->artisan('casa:audit-crud-integrity')
            ->expectsOutputToContain('Appointments with unsupported statuses: 1')
            ->assertFailed();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'pending',
        ]);
    }

    public function test_integrity_audit_detects_starts_outside_the_thirty_minute_interval(): void
    {
        Appointment::factory()->confirmed()->create([
            'scheduled_start_at' => now()->addDay()->setTime(15, 15),
            'scheduled_end_at' => now()->addDay()->setTime(16, 15),
        ]);

        $this->artisan('casa:audit-crud-integrity')
            ->expectsOutputToContain('Scheduled appointments outside 30-minute start intervals: 1')
            ->assertFailed();
    }
}
