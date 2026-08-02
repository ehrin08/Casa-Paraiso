<?php

namespace Database\Factories;

use App\Models\RfmSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfmSegment>
 */
class RfmSegmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'recency_min_days' => null,
            'recency_max_days' => fake()->numberBetween(7, 90),
            'frequency_min' => fake()->numberBetween(1, 3),
            'frequency_max' => null,
            'monetary_min' => null,
            'monetary_max' => null,
            'is_active' => true,
        ];
    }
}
