<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_schedule_weeks', function (Blueprint $table) {
            $table->id();
            $table->date('week_start_date')->unique();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('staff_schedule_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_schedule_week_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->string('version', 16);
            $table->date('schedule_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('ends_next_day')->default(false);
            $table->timestamps();
            $table->index(['staff_schedule_week_id', 'version']);
            $table->index(['staff_profile_id', 'schedule_date', 'version'], 'staff_roster_shift_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_schedule_shifts');
        Schema::dropIfExists('staff_schedule_weeks');
    }
};
