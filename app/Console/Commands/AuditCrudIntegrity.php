<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\PromotionSuggestion;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AuditCrudIntegrity extends Command
{
    protected $signature = 'casa:audit-crud-integrity';

    protected $description = 'Run a read-only audit for orphaned CRUD relationships and invalid status metadata.';

    public function handle(): int
    {
        $checks = [
            'Active appointments assigned to deleted or missing staff' => $this->activeAppointmentsWithUnavailableStaff(),
            'Appointments with unsupported statuses' => Appointment::query()
                ->whereNotIn('status', Appointment::STATUSES),
            'Confirmed appointments missing assignment or confirmation metadata' => Appointment::query()
                ->where('status', Appointment::STATUS_CONFIRMED)
                ->where(fn ($query) => $query
                    ->whereNull('staff_profile_id')
                    ->orWhereNull('scheduled_start_at')
                    ->orWhereNull('scheduled_end_at')
                    ->orWhereNull('confirmed_at')),
            'Completed appointments missing schedule or completion metadata' => Appointment::query()
                ->where('status', Appointment::STATUS_COMPLETED)
                ->where(fn ($query) => $query
                    ->whereNull('staff_profile_id')
                    ->orWhereNull('scheduled_start_at')
                    ->orWhereNull('scheduled_end_at')
                    ->orWhereNull('completed_at')),
            'No-show appointments missing schedule or assignment metadata' => Appointment::query()
                ->where('status', Appointment::STATUS_NO_SHOW)
                ->where(fn ($query) => $query
                    ->whereNull('staff_profile_id')
                    ->orWhereNull('scheduled_start_at')
                    ->orWhereNull('scheduled_end_at')),
            'Cancelled appointments missing cancellation metadata' => Appointment::query()
                ->where('status', Appointment::STATUS_CANCELLED)
                ->whereNull('cancelled_at'),
            'Scheduled appointments outside 30-minute start intervals' => $this->appointmentsOutsideStartIntervals(),
            'Paid transactions missing method or paid date' => Transaction::query()
                ->where('payment_status', Transaction::PAYMENT_PAID)
                ->where(fn ($query) => $query->whereNull('payment_method')->orWhereNull('paid_at')),
            'Unpaid or void transactions retaining payment metadata' => Transaction::query()
                ->whereIn('payment_status', [Transaction::PAYMENT_UNPAID, Transaction::PAYMENT_VOID])
                ->where(fn ($query) => $query->whereNotNull('payment_method')->orWhereNotNull('paid_at')),
            'Promotion suggestions with inconsistent terminal timestamps' => PromotionSuggestion::query()
                ->where(function ($query): void {
                    $query
                        ->where(fn ($query) => $query
                            ->where('status', PromotionSuggestion::STATUS_APPLIED)
                            ->where(fn ($query) => $query->whereNull('applied_at')->orWhereNotNull('dismissed_at')))
                        ->orWhere(fn ($query) => $query
                            ->where('status', PromotionSuggestion::STATUS_DISMISSED)
                            ->where(fn ($query) => $query->whereNull('dismissed_at')->orWhereNotNull('applied_at')))
                        ->orWhere(fn ($query) => $query
                            ->where('status', PromotionSuggestion::STATUS_SUGGESTED)
                            ->where(fn ($query) => $query->whereNotNull('applied_at')->orWhereNotNull('dismissed_at')));
                }),
            'Multiple available customer rewards for one customer' => DB::table('promotion_suggestions')
                ->select('customer_profile_id')
                ->where('status', PromotionSuggestion::STATUS_SUGGESTED)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->groupBy('customer_profile_id')
                ->havingRaw('COUNT(*) > 1'),
        ];

        $issues = 0;

        foreach ($checks as $label => $query) {
            $count = $this->count($query);

            if ($count === 0) {
                $this->components->info("{$label}: 0");

                continue;
            }

            $issues += $count;
            $this->components->warn("{$label}: {$count}");
        }

        if ($issues > 0) {
            $this->newLine();
            $this->error("CRUD integrity audit found {$issues} issue(s). No data was changed.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('CRUD integrity audit passed. No data was changed.');

        return self::SUCCESS;
    }

    private function activeAppointmentsWithUnavailableStaff(): Builder
    {
        return DB::table('appointments')
            ->select('appointments.id')
            ->leftJoin('staff_profiles', 'staff_profiles.id', '=', 'appointments.staff_profile_id')
            ->whereIn('appointments.status', [Appointment::STATUS_CONFIRMED])
            ->whereNotNull('appointments.staff_profile_id')
            ->where(fn ($query) => $query
                ->whereNull('staff_profiles.id')
                ->orWhereNotNull('staff_profiles.deleted_at'));
    }

    private function appointmentsOutsideStartIntervals(): EloquentBuilder
    {
        $query = Appointment::query()->whereNotNull('scheduled_start_at');

        if (DB::connection()->getDriverName() === 'sqlite') {
            return $query->whereRaw("CAST(strftime('%M', scheduled_start_at) AS INTEGER) % 30 <> 0");
        }

        return $query->whereRaw('MOD(EXTRACT(MINUTE FROM scheduled_start_at), 30) <> 0');
    }

    private function count(mixed $query): int
    {
        $baseQuery = $query instanceof EloquentBuilder ? $query->toBase() : $query;

        return (int) DB::query()->fromSub($baseQuery, 'integrity_check')->count();
    }
}
