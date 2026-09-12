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
        Schema::table('application_settings', function (Blueprint $table): void {
            $table->text('company_history')->nullable()->after('map_url');
            $table->json('organization_chart')->nullable()->after('company_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_settings', function (Blueprint $table): void {
            $table->dropColumn(['company_history', 'organization_chart']);
        });
    }
};
