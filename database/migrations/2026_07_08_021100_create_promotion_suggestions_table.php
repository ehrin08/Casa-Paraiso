<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promotion_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('rfm_segment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promotion_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('recency_days')->nullable();
            $table->integer('frequency_count')->nullable();
            $table->decimal('monetary_total', 10, 2)->nullable();
            $table->string('suggested_offer');
            $table->string('status')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_profile_id', 'status']);
            $table->index('rfm_segment_id');
            $table->index('promotion_rule_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_suggestions');
    }
};
