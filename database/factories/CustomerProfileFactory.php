<?php

namespace Database\Factories;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProfile>
 */
class CustomerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'customer_code' => 'CP-'.fake()->unique()->numerify('#####'),
            'birth_date' => fake()->optional()->dateTimeBetween('-65 years', '-18 years'),
            'address' => fake()->optional()->address(),
            'contact_preference' => fake()->randomElement(array_keys(CustomerProfile::CONTACT_PREFERENCES)),
            'notes' => fake()->optional()->sentence(),
            'first_visit_at' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
