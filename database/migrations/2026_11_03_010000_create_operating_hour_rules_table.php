<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-managed default hours plus dated rules: closures, special hours and partial closures.
     * A rule with no end date lasts until further notice. Existing closed dates are copied over;
     * the closed_dates table stays untouched as rollback evidence.
     */
    public function up(): void
    {
        Schema::table('application_settings', function (Blueprint $table): void {
            $table->time('opens_at')->nullable()->after('gift_voucher_combinable');
            $table->time('closes_at')->nullable()->after('opens_at');
        });

        Schema::create('operating_hour_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['starts_on', 'ends_on']);
        });

        if (Schema::hasTable('closed_dates')) {
            DB::table('closed_dates')->orderBy('id')->get()->each(fn (object $closed) => DB::table('operating_hour_rules')->insert([
                'type' => 'closed',
                'starts_on' => $closed->closed_on,
                'ends_on' => $closed->closed_on,
                'reason' => $closed->reason,
                'created_by' => $closed->created_by,
                'created_at' => $closed->created_at,
                'updated_at' => $closed->updated_at,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_hour_rules');

        Schema::table('application_settings', function (Blueprint $table): void {
            $table->dropColumn(['opens_at', 'closes_at']);
        });
    }
};
