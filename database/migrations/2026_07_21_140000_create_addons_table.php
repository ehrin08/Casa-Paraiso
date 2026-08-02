<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        foreach ([
            ['code' => 'ventosa', 'name' => 'Ventosa', 'price' => 200, 'duration_minutes' => 0],
            ['code' => 'hot-compress', 'name' => 'Hot Compress', 'price' => 200, 'duration_minutes' => 0],
            ['code' => 'hot-stone', 'name' => 'Hot Stone', 'price' => 200, 'duration_minutes' => 0],
            ['code' => 'back-massage-30', 'name' => '30-Minute Back Massage', 'price' => 299, 'duration_minutes' => 30],
            ['code' => 'vip-room', 'name' => 'VIP Room', 'price' => 200, 'duration_minutes' => 0],
        ] as $addon) {
            DB::table('addons')->insert(array_merge($addon, ['created_at' => now(), 'updated_at' => now(), 'is_active' => true]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
