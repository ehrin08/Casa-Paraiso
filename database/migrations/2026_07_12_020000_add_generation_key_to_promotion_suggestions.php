<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_suggestions', function (Blueprint $table) {
            $table->char('generation_key', 64)
                ->nullable()
                ->unique()
                ->after('promotion_rule_id');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_suggestions', function (Blueprint $table) {
            $table->dropUnique(['generation_key']);
            $table->dropColumn('generation_key');
        });
    }
};
