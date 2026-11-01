<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'back-massage';

    /**
     * The client sells the PHP 299 back massage on its own, so it becomes a bookable service
     * next to the add-on of the same name. Only installed catalogs (with GAIA TOUCH) are changed;
     * fresh databases get it from the seeder. Safe to rerun: existing rows are left alone.
     */
    public function up(): void
    {
        if (! DB::table('services')->where('slug', 'gaia-touch')->exists()) {
            return;
        }

        $now = now();
        $serviceId = DB::table('services')->where('slug', self::SLUG)->value('id');

        if ($serviceId === null) {
            $serviceId = DB::table('services')->insertGetId([
                'name' => '30-Minute Back Massage',
                'slug' => self::SLUG,
                'description' => 'A focused 30-minute massage for the back, shoulders and neck.',
                'duration_minutes' => 30,
                'price' => 299.00,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Therapists who already give the signature full body massage can give the back massage.
        $therapists = DB::table('staff_services')
            ->join('services', 'services.id', '=', 'staff_services.service_id')
            ->where('services.slug', 'gaia-touch')
            ->pluck('staff_services.staff_profile_id');
        $assigned = DB::table('staff_services')->where('service_id', $serviceId)->pluck('staff_profile_id');

        DB::table('staff_services')->insert($therapists->diff($assigned)->unique()->map(fn ($staffProfileId) => [
            'staff_profile_id' => $staffProfileId,
            'service_id' => $serviceId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());
    }

    public function down(): void
    {
        $serviceId = DB::table('services')->where('slug', self::SLUG)->value('id');

        // Keep the service once it has bookings or payments, so history is never lost.
        if ($serviceId === null
            || DB::table('appointments')->where('service_id', $serviceId)->exists()
            || DB::table('transactions')->where('service_id', $serviceId)->exists()) {
            return;
        }

        DB::table('staff_services')->where('service_id', $serviceId)->delete();
        DB::table('services')->where('id', $serviceId)->delete();
    }
};
