<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RETIREMENT_REASON = 'Pending workflow retired during appointment-state migration.';

    public function up(): void
    {
        DB::transaction(function (): void {
            $timestamp = now();
            $appointmentIds = DB::table('appointments')
                ->where('status', 'pending')
                ->lockForUpdate()
                ->pluck('id');

            if ($appointmentIds->isEmpty()) {
                return;
            }

            DB::table('appointments')
                ->whereIn('id', $appointmentIds)
                ->update([
                    'staff_profile_id' => null,
                    'scheduled_start_at' => null,
                    'scheduled_end_at' => null,
                    'status' => 'cancelled',
                    'confirmed_at' => null,
                    'completed_at' => null,
                    'cancelled_at' => $timestamp,
                    'cancelled_by' => null,
                    'updated_at' => $timestamp,
                ]);

            DB::table('appointment_status_logs')->insert(
                $appointmentIds->map(fn (int $id): array => [
                    'appointment_id' => $id,
                    'from_status' => 'pending',
                    'to_status' => 'cancelled',
                    'changed_by' => null,
                    'reason' => self::RETIREMENT_REASON,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])->all(),
            );
        });
    }

    public function down(): void
    {
        // This data retirement is forward-only; restore a database backup to recover pending records.
    }
};
