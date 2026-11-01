<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Default quintile score rules for the original presets, in priority order. */
    private const DEFAULT_RULES = [
        'high_value' => ['r' => [4, 5], 'f' => [4, 5], 'm' => [4, 5], 'description' => 'Top two-fifths for recency, visits and spending (R 4–5, F 4–5, M 4–5).'],
        'loyal' => ['r' => [3, 5], 'f' => [4, 5], 'm' => [null, null], 'description' => 'Frequent visitors (F 4–5) who still visit fairly recently (R 3–5).'],
        'at_risk' => ['r' => [1, 2], 'f' => [3, 5], 'm' => [null, null], 'description' => 'Used to visit often (F 3–5) but have not returned lately (R 1–2).'],
        'inactive' => ['r' => [1, 1], 'f' => [null, null], 'm' => [null, null], 'description' => 'The least recent fifth of customers (R 1).'],
        'new_customer' => ['r' => [5, 5], 'f' => [1, 1], 'm' => [null, null], 'description' => 'The most recent fifth with a single paid visit (R 5, F 1).'],
    ];

    public function up(): void
    {
        Schema::table('rfm_segments', function (Blueprint $table): void {
            $table->unsignedInteger('priority')->default(0)->after('preset_key')->index();
            foreach (['r', 'f', 'm'] as $metric) {
                $table->unsignedTinyInteger("{$metric}_score_min")->nullable();
                $table->unsignedTinyInteger("{$metric}_score_max")->nullable();
            }
        });

        Schema::table('promotion_suggestions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('r_score')->nullable()->after('monetary_total');
            $table->unsignedTinyInteger('f_score')->nullable()->after('r_score');
            $table->unsignedTinyInteger('m_score')->nullable()->after('f_score');
        });

        $priority = 0;

        foreach (self::DEFAULT_RULES as $key => $rule) {
            $priority += 10;
            DB::table('rfm_segments')->where('preset_key', $key)->update([
                'priority' => $priority,
                'description' => $rule['description'],
                'r_score_min' => $rule['r'][0], 'r_score_max' => $rule['r'][1],
                'f_score_min' => $rule['f'][0], 'f_score_max' => $rule['f'][1],
                'm_score_min' => $rule['m'][0], 'm_score_max' => $rule['m'][1],
                'updated_at' => now(),
            ]);
        }

        DB::table('rfm_segments')
            ->where(fn ($query) => $query->whereNull('preset_key')->orWhereNotIn('preset_key', array_keys(self::DEFAULT_RULES)))
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$priority): void {
                $priority += 10;
                DB::table('rfm_segments')->where('id', $id)->update(['priority' => $priority]);
            });
    }

    public function down(): void
    {
        Schema::table('promotion_suggestions', function (Blueprint $table): void {
            $table->dropColumn(['r_score', 'f_score', 'm_score']);
        });

        Schema::table('rfm_segments', function (Blueprint $table): void {
            $table->dropIndex(['priority']);
            $table->dropColumn(['priority', 'r_score_min', 'r_score_max', 'f_score_min', 'f_score_max', 'm_score_min', 'm_score_max']);
        });
    }
};
