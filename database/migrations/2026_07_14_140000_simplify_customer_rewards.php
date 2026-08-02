<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfm_segments', function (Blueprint $table): void {
            $table->string('preset_key')->nullable()->after('name');
            $table->string('addon_code')->nullable()->after('description');
            $table->unique('preset_key');
            $table->index('addon_code');
        });

        Schema::table('promotion_suggestions', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('dismissed_at');
            $table->index('expires_at');
        });

        Schema::table('application_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('promotion_voucher_validity_days')->nullable()->default(90)->after('default_payment_method');
        });

        $now = now();
        $presets = config('casa.customer_rewards.presets', []);

        foreach ($presets as $preset) {
            $segment = DB::table('rfm_segments')
                ->where('preset_key', $preset['key'])
                ->orderBy('id')
                ->first();

            $segment ??= DB::table('rfm_segments')
                ->where('name', $preset['name'])
                ->orderBy('id')
                ->first();

            if (! $segment) {
                $segmentId = DB::table('rfm_segments')->insertGetId([
                    'name' => $preset['name'],
                    'preset_key' => $preset['key'],
                    'description' => $preset['description'],
                    'addon_code' => $preset['addon_code'],
                    'recency_min_days' => $preset['recency_min_days'],
                    'recency_max_days' => $preset['recency_max_days'],
                    'frequency_min' => $preset['frequency_min'],
                    'frequency_max' => $preset['frequency_max'],
                    'monetary_min' => $preset['monetary_min'],
                    'monetary_max' => $preset['monetary_max'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $effectiveRule = DB::table('promotion_rules')
                    ->where('rfm_segment_id', $segment->id)
                    ->where('is_active', true)
                    ->whereNotNull('addon_code')
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->first();

                $addonCode = $effectiveRule?->addon_code ?: $segment->addon_code ?: $preset['addon_code'];
                $isActive = (bool) $segment->is_active && ($effectiveRule !== null || $segment->addon_code !== null);

                DB::table('rfm_segments')->where('id', $segment->id)->update([
                    'name' => $preset['name'],
                    'preset_key' => $preset['key'],
                    'description' => $preset['description'],
                    'addon_code' => $addonCode,
                    'recency_min_days' => $preset['recency_min_days'],
                    'recency_max_days' => $preset['recency_max_days'],
                    'frequency_min' => $preset['frequency_min'],
                    'frequency_max' => $preset['frequency_max'],
                    'monetary_min' => $preset['monetary_min'],
                    'monetary_max' => $preset['monetary_max'],
                    'is_active' => $isActive,
                    'updated_at' => $now,
                ]);
                $segmentId = $segment->id;
            }
        }

        DB::table('promotion_suggestions')
            ->where('status', 'reviewed')
            ->update(['status' => 'suggested', 'updated_at' => $now]);

        DB::table('promotion_suggestions')
            ->where('status', 'suggested')
            ->whereNull('expires_at')
            ->update(['expires_at' => $now->copy()->addDays(90), 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::table('application_settings', function (Blueprint $table): void {
            $table->dropColumn('promotion_voucher_validity_days');
        });

        Schema::table('promotion_suggestions', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });

        Schema::table('rfm_segments', function (Blueprint $table): void {
            $table->dropUnique(['preset_key']);
            $table->dropIndex(['addon_code']);
            $table->dropColumn(['preset_key', 'addon_code']);
        });
    }
};
