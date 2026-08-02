<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\PromotionSuggestion;
use App\Models\StaffAttendance;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetOperationalDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_change_operational_or_reference_data(): void
    {
        $appointment = Appointment::factory()->create();
        Transaction::factory()->create(['appointment_id' => $appointment->id]);
        $staff = $appointment->staffProfile()->firstOrFail();
        StaffAttendance::query()->create(['staff_profile_id' => $staff->id, 'attendance_date' => now()->toDateString()]);

        $this->artisan('casa:reset-operational-data')
            ->expectsOutputToContain('Dry run complete. No data was changed.')
            ->assertSuccessful();

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
        $this->assertDatabaseHas('transactions', ['appointment_id' => $appointment->id]);
        $this->assertDatabaseHas('staff_attendances', ['staff_profile_id' => $staff->id]);
        $this->assertDatabaseHas('users', ['id' => $staff->user_id]);
    }

    public function test_apply_removes_operational_rows_and_restores_linked_vouchers(): void
    {
        $voucher = PromotionSuggestion::factory()->create([
            'status' => PromotionSuggestion::STATUS_APPLIED,
            'applied_at' => now(),
        ]);
        $appointment = Appointment::factory()->create([
            'promotion_suggestion_id' => $voucher->id,
        ]);
        Feedback::factory()->create([
            'appointment_id' => $appointment->id,
            'customer_profile_id' => $appointment->customer_profile_id,
        ]);
        Transaction::factory()->create([
            'appointment_id' => $appointment->id,
            'customer_profile_id' => $appointment->customer_profile_id,
        ]);

        $staff = $appointment->staffProfile()->firstOrFail();
        StaffAttendance::query()->create(['staff_profile_id' => $staff->id, 'attendance_date' => now()->toDateString()]);
        $preservedVoucher = PromotionSuggestion::factory()->create(['status' => PromotionSuggestion::STATUS_SUGGESTED]);
        $preservedUserId = $staff->user_id;

        $this->artisan('casa:reset-operational-data', ['--apply' => true, '--yes' => true])
            ->assertSuccessful();

        foreach (['appointments', 'appointment_addons', 'appointment_status_logs', 'transactions', 'therapist_commissions', 'feedback', 'feedback_sentiment_runs', 'feedback_annotations', 'staff_attendances', 'staff_attendance_scan_requests', 'staff_attendance_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }

        $this->assertDatabaseHas('promotion_suggestions', [
            'id' => $voucher->id,
            'status' => PromotionSuggestion::STATUS_SUGGESTED,
            'applied_at' => null,
        ]);
        $this->assertDatabaseHas('promotion_suggestions', ['id' => $preservedVoucher->id, 'status' => PromotionSuggestion::STATUS_SUGGESTED]);
        $this->assertDatabaseHas('users', ['id' => $preservedUserId]);

        $newAppointment = Appointment::factory()->create();
        $this->assertGreaterThan($appointment->id, $newAppointment->id);
    }

    public function test_apply_requires_confirmation(): void
    {
        Appointment::factory()->create();

        $this->artisan('casa:reset-operational-data', ['--apply' => true])
            ->expectsConfirmation('Permanently delete the listed operational data and restore linked voucher reservations?', 'no')
            ->expectsOutputToContain('Reset cancelled. No data was changed.')
            ->assertSuccessful();

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_second_apply_is_idempotent(): void
    {
        $this->artisan('casa:reset-operational-data', ['--apply' => true, '--yes' => true])->assertSuccessful();
        $this->artisan('casa:reset-operational-data', ['--apply' => true, '--yes' => true])->assertSuccessful();

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('staff_attendances', 0);
    }
}
