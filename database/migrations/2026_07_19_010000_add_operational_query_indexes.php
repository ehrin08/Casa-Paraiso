<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->index(['status', 'scheduled_start_at'], 'appointments_status_scheduled_start_index');
            $table->index(['customer_profile_id', 'scheduled_start_at'], 'appointments_customer_scheduled_start_index');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->index('paid_at', 'transactions_paid_at_index');
            $table->index(['payment_status', 'paid_at'], 'transactions_payment_status_paid_at_index');
        });

        Schema::table('promotion_suggestions', function (Blueprint $table): void {
            $table->index(['status', 'expires_at'], 'promotion_suggestions_status_expires_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_suggestions', function (Blueprint $table): void {
            $table->dropIndex('promotion_suggestions_status_expires_at_index');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex('transactions_paid_at_index');
            $table->dropIndex('transactions_payment_status_paid_at_index');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex('appointments_status_scheduled_start_index');
            $table->dropIndex('appointments_customer_scheduled_start_index');
        });
    }
};
