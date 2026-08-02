<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_rules', function (Blueprint $table) {
            $table->string('addon_code')->nullable()->after('suggested_offer');
            $table->index('addon_code');
        });

        Schema::table('promotion_suggestions', function (Blueprint $table) {
            $table->string('addon_code')->nullable()->after('suggested_offer');
            $table->index('addon_code');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('promotion_suggestion_id')
                ->nullable()
                ->after('preferred_staff_profile_id')
                ->constrained()
                ->nullOnDelete();
        });

        $defaultAddons = [
            'New customer' => ['hot-compress', 'Hot Compress'],
            'Loyal customer' => ['back-massage-30', '30-Minute Back Massage'],
            'At-risk customer' => ['ventosa', 'Ventosa'],
            'High-value customer' => ['vip-room', 'VIP Room'],
            'Inactive customer' => ['hot-stone', 'Hot Stone'],
        ];

        foreach ($defaultAddons as $segmentName => [$addonCode, $addonName]) {
            $segmentId = DB::table('rfm_segments')->where('name', $segmentName)->value('id');

            if ($segmentId) {
                DB::table('promotion_rules')
                    ->where('rfm_segment_id', $segmentId)
                    ->whereNull('deleted_at')
                    ->update([
                        'addon_code' => $addonCode,
                        'suggested_offer' => 'Complimentary '.$addonName.' add-on voucher',
                    ]);

                DB::table('promotion_suggestions')
                    ->where('rfm_segment_id', $segmentId)
                    ->whereNull('addon_code')
                    ->update([
                        'addon_code' => $addonCode,
                        'suggested_offer' => 'Complimentary '.$addonName.' add-on voucher',
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_suggestion_id');
        });

        Schema::table('promotion_suggestions', function (Blueprint $table) {
            $table->dropIndex(['addon_code']);
            $table->dropColumn('addon_code');
        });

        Schema::table('promotion_rules', function (Blueprint $table) {
            $table->dropIndex(['addon_code']);
            $table->dropColumn('addon_code');
        });
    }
};
