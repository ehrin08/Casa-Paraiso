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
        Schema::create('rfm_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('recency_min_days')->nullable();
            $table->integer('recency_max_days')->nullable();
            $table->integer('frequency_min')->nullable();
            $table->integer('frequency_max')->nullable();
            $table->decimal('monetary_min', 10, 2)->nullable();
            $table->decimal('monetary_max', 10, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfm_segments');
    }
};
