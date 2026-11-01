<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Whole business days the spa is closed, such as holidays. No one can book on them. */
    public function up(): void
    {
        Schema::create('closed_dates', function (Blueprint $table): void {
            $table->id();
            $table->date('closed_on')->unique();
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closed_dates');
    }
};
