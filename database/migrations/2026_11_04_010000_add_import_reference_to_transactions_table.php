<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rows loaded from a service-history CSV keep their file reference, so importing the same
     * file again skips rows that are already in the system.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('import_reference', 64)->nullable()->unique()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropUnique(['import_reference']);
            $table->dropColumn('import_reference');
        });
    }
};
