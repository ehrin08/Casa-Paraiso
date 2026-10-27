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
        Schema::table('users', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('phone');
            $table->string('public_title', 80)->nullable()->after('photo_path');
            $table->string('public_bio', 280)->nullable()->after('public_title');
            $table->string('org_chart_tier', 20)->nullable()->after('public_bio');
            $table->unsignedSmallInteger('org_chart_order')->default(0)->after('org_chart_tier');
            $table->boolean('show_on_org_chart')->default(false)->after('org_chart_order');
            $table->index(['show_on_org_chart', 'org_chart_tier', 'org_chart_order'], 'users_org_chart_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_org_chart_index');
            $table->dropColumn(['photo_path', 'public_title', 'public_bio', 'org_chart_tier', 'org_chart_order', 'show_on_org_chart']);
        });
    }
};
