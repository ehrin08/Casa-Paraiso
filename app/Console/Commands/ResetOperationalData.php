<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class ResetOperationalData extends Command
{
    protected $signature = 'casa:reset-operational-data
        {--connection= : Database connection to inspect and reset (defaults to the application connection)}
        {--apply : Apply the reset; without this flag the command is read-only}
        {--yes : Skip the interactive confirmation after --apply}';

    protected $description = 'Dry-run or reset appointment, payment, feedback, commission, and attendance data while preserving accounts and reference data.';

    private const RESETTABLES = [
        'feedback_annotations',
        'feedback_sentiment_runs',
        'feedback',
        'therapist_commissions',
        'transactions',
        'appointment_addons',
        'appointment_status_logs',
        'appointments',
        'staff_attendance_events',
        'staff_attendance_scan_requests',
        'staff_attendances',
    ];

    public function handle(): int
    {
        $connectionName = $this->option('connection') ?: config('database.default');
        $connection = DB::connection($connectionName);

        if ($connection->getDriverName() !== 'mysql') {
            $this->error('This operational reset requires a MySQL connection. No data was changed.');

            return self::FAILURE;
        }

        $counts = $this->counts($connection);

        $this->info("Connection: {$connectionName}");
        $this->table(['Table', 'Rows'], collect($counts)
            ->map(fn (int $count, string $table): array => [$table, $count])
            ->values()
            ->all());

        if (! $this->option('apply')) {
            $this->info('Dry run complete. No data was changed. Use --apply after taking a verified backup/export.');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('Permanently delete the listed operational data and restore linked voucher reservations?')) {
            $this->warn('Reset cancelled. No data was changed.');

            return self::SUCCESS;
        }

        $connection->transaction(function () use ($connection): void {
            $voucherIds = $connection->table('appointments')
                ->whereNotNull('promotion_suggestion_id')
                ->pluck('promotion_suggestion_id');

            $connection->table('promotion_suggestions')
                ->whereIn('id', $voucherIds)
                ->where('status', 'applied')
                ->update(['status' => 'suggested', 'applied_at' => null, 'updated_at' => now()]);

            foreach (['feedback_annotations', 'feedback_sentiment_runs', 'feedback', 'therapist_commissions', 'transactions', 'appointment_addons', 'appointment_status_logs'] as $table) {
                $connection->table($table)->delete();
            }

            $connection->table('appointments')->delete();

            foreach (['staff_attendance_events', 'staff_attendance_scan_requests', 'staff_attendances'] as $table) {
                $connection->table($table)->delete();
            }

        });

        $this->info('Operational data reset completed. Accounts and reference data were preserved.');

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function counts(Connection $connection): array
    {
        return collect(self::RESETTABLES)
            ->mapWithKeys(fn (string $table): array => [$table => $connection->table($table)->count()])
            ->all();
    }
}
