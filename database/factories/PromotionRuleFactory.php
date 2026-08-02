<?php

namespace Database\Factories;

use App\Models\PromotionRule;
use App\Models\RfmSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionRule>
 */
class PromotionRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rfm_segment_id' => RfmSegment::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'suggested_offer' => 'Complimentary Hot Compress add-on voucher',
            'addon_code' => 'hot-compress',
            'is_active' => true,
        ];
    }
}
