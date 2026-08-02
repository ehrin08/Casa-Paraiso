<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffService>
 */
class StaffServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'staff_profile_id' => StaffProfile::factory(),
            'service_id' => Service::factory(),
        ];
    }
}
