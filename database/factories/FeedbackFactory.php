<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_profile_id' => CustomerProfile::factory(),
            'appointment_id' => Appointment::factory(),
            'service_id' => Service::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->optional()->sentence(),
            'sentiment_label' => Feedback::SENTIMENT_POSITIVE,
            'sentiment_score' => fake()->randomFloat(2, 0.5, 1),
            'submitted_at' => now(),
        ];
    }
}
