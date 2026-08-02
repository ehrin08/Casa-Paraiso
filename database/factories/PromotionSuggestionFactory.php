<?php

namespace Database\Factories;

use App\Models\CustomerProfile;
use App\Models\PromotionRule;
use App\Models\PromotionSuggestion;
use App\Models\RfmSegment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionSuggestion>
 */
class PromotionSuggestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_profile_id' => CustomerProfile::factory(),
            'rfm_segment_id' => RfmSegment::factory(),
            'promotion_rule_id' => PromotionRule::factory(),
            'generation_key' => null,
            'recency_days' => fake()->numberBetween(1, 180),
            'frequency_count' => fake()->numberBetween(1, 12),
            'monetary_total' => fake()->randomFloat(2, 600, 15000),
            'suggested_offer' => 'Complimentary Hot Compress add-on voucher',
            'addon_code' => 'hot-compress',
            'status' => PromotionSuggestion::STATUS_SUGGESTED,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'applied_at' => null,
            'dismissed_at' => null,
            'expires_at' => now()->addDays(90),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PromotionSuggestion::STATUS_REVIEWED,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }
}
