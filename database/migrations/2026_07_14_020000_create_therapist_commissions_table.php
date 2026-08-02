<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('therapist_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('primary_transaction_id')->nullable()->unique()->constrained('transactions')->restrictOnDelete();
            $table->foreignId('adjusts_commission_id')->nullable()->constrained('therapist_commissions')->restrictOnDelete();
            $table->string('commission_type', 30)->index();
            $table->string('status', 30)->default('pending')->index();
            $table->decimal('basis_amount', 10, 2);
            $table->decimal('commission_rate', 5, 4);
            $table->decimal('commission_amount', 10, 2);
            $table->dateTime('earned_at')->index();
            $table->dateTime('paid_at')->nullable()->index();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['staff_profile_id', 'status', 'earned_at'], 'commissions_staff_status_earned_index');
            $table->index(['transaction_id', 'commission_type'], 'commissions_transaction_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('therapist_commissions');
    }
};
