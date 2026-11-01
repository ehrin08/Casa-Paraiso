<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table): void {
            $table->timestamp('online_booking_blocked_at')->nullable()->after('notes');
            $table->string('online_booking_block_reason', 500)->nullable()->after('online_booking_blocked_at');
            $table->foreignId('online_booking_blocked_by')->nullable()->after('online_booking_block_reason')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('online_booking_blocked_by');
            $table->dropColumn(['online_booking_blocked_at', 'online_booking_block_reason']);
        });
    }
};
