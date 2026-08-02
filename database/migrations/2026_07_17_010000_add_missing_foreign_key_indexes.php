<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_settings', function (Blueprint $table): void {
            $table->index('updated_by');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->index('cancelled_by');
            $table->index('created_by');
            $table->index('preferred_staff_profile_id');
            $table->index('promotion_suggestion_id');
            $table->index('updated_by');
        });

        Schema::table('promotion_suggestions', function (Blueprint $table): void {
            $table->index('reviewed_by');
        });

        Schema::table('staff_schedule_exceptions', function (Blueprint $table): void {
            $table->index('created_by');
        });

        Schema::table('staff_schedule_weeks', function (Blueprint $table): void {
            $table->index('published_by');
        });

        Schema::table('therapist_commissions', function (Blueprint $table): void {
            $table->index('adjusts_commission_id');
            $table->index('appointment_id');
            $table->index('paid_by');
        });
    }

    public function down(): void
    {
        Schema::table('therapist_commissions', function (Blueprint $table): void {
            $table->dropIndex(['adjusts_commission_id']);
            $table->dropIndex(['appointment_id']);
            $table->dropIndex(['paid_by']);
        });

        Schema::table('staff_schedule_weeks', function (Blueprint $table): void {
            $table->dropIndex(['published_by']);
        });

        Schema::table('staff_schedule_exceptions', function (Blueprint $table): void {
            $table->dropIndex(['created_by']);
        });

        Schema::table('promotion_suggestions', function (Blueprint $table): void {
            $table->dropIndex(['reviewed_by']);
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['cancelled_by']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['preferred_staff_profile_id']);
            $table->dropIndex(['promotion_suggestion_id']);
            $table->dropIndex(['updated_by']);
        });

        Schema::table('application_settings', function (Blueprint $table): void {
            $table->dropIndex(['updated_by']);
        });
    }
};
