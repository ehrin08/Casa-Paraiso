<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffProfile>
 */
class StaffProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->staff(),
            'staff_type' => StaffProfile::TYPE_THERAPIST,
            'position' => fake()->randomElement(['Spa Therapist', 'Senior Therapist', 'Wellness Specialist']),
            'specialization' => fake()->randomElement(['Massage therapy', 'Body treatments', 'Foot care']),
            'bio' => fake()->optional()->sentence(),
            'hire_date' => fake()->optional()->dateTimeBetween('-3 years', '-1 month'),
            'is_bookable' => true,
        ];
    }
}
