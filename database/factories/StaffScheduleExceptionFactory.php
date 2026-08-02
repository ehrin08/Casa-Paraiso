<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffScheduleException>
 */
class StaffScheduleExceptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'staff_profile_id' => StaffProfile::factory(),
            'exception_date' => fake()->dateTimeBetween('now', '+2 months'),
            'exception_type' => StaffScheduleException::TYPE_UNAVAILABLE,
            'start_time' => null,
            'end_time' => null,
            'ends_next_day' => false,
            'reason' => fake()->optional()->sentence(),
            'created_by' => User::factory()->admin(),
        ];
    }
}
