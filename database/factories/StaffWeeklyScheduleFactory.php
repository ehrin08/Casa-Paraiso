<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\StaffWeeklySchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffWeeklySchedule>
 */
class StaffWeeklyScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'staff_profile_id' => StaffProfile::factory(),
            'day_of_week' => fake()->numberBetween(1, 6),
            'start_time' => '13:00:00',
            'end_time' => '21:00:00',
            'ends_next_day' => false,
            'is_available' => true,
        ];
    }
}
