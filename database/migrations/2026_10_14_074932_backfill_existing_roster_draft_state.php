<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('staff_schedule_weeks')->whereNull('draft_initialized_at')
            ->whereIn('id', DB::table('staff_schedule_shifts')->select('staff_schedule_week_id')->where('version', 'draft'))
            ->update(['draft_initialized_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Draft rows are retained; the preceding schema migration removes this metadata on rollback.
    }
};
