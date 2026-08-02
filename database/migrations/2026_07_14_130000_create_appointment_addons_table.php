<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('addon_code');
            $table->string('addon_name');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->timestamps();

            $table->unique(['appointment_id', 'addon_code']);
            $table->index('addon_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_addons');
    }
};
