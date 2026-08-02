<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_settings', function (Blueprint $table): void {
            $table->text('location_landmarks')->nullable()->after('business_address');
            $table->string('facebook_url', 2048)->nullable()->after('location_landmarks');
            $table->string('messenger_url', 2048)->nullable()->after('facebook_url');
            $table->string('map_url', 2048)->nullable()->after('messenger_url');
        });

        DB::table('application_settings')->update([
            'business_address' => 'Barangay Cuta East, Santa Teresita, Batangas, Philippines',
            'location_landmarks' => 'In front of Alfamart and PLDT; in the same building as BDO Network Bank.',
            'facebook_url' => 'https://www.facebook.com/61579320037378',
            'messenger_url' => 'https://m.me/61579320037378',
            'map_url' => 'https://www.google.com/maps/search/?api=1&query=Casa+Paraiso+Body+%26+Wellness+Spa%2C+Cuta+East%2C+Santa+Teresita%2C+Batangas',
        ]);
    }

    public function down(): void
    {
        Schema::table('application_settings', function (Blueprint $table): void {
            $table->dropColumn(['location_landmarks', 'facebook_url', 'messenger_url', 'map_url']);
        });
    }
};
