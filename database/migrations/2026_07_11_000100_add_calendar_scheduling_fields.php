<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('preferred_staff_profile_id')
                ->nullable()
                ->after('staff_profile_id')
                ->constrained('staff_profiles')
                ->nullOnDelete();
        });

        Schema::table('staff_weekly_schedules', function (Blueprint $table) {
            $table->boolean('ends_next_day')->default(false)->after('end_time');
        });

        Schema::table('staff_schedule_exceptions', function (Blueprint $table) {
            $table->boolean('ends_next_day')->default(false)->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_staff_profile_id');
        });

        Schema::table('staff_weekly_schedules', function (Blueprint $table) {
            $table->dropColumn('ends_next_day');
        });

        Schema::table('staff_schedule_exceptions', function (Blueprint $table) {
            $table->dropColumn('ends_next_day');
        });
    }
};
