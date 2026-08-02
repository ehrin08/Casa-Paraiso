<?php

namespace Database\Factories;

use App\Models\ApplicationSetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationSetting>
 */
class ApplicationSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_name' => 'Casa Paraiso Body and Wellness Spa',
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'business_address' => fake()->address(),
            'location_landmarks' => 'Near the town center.',
            'facebook_url' => 'https://www.facebook.com/61579320037378',
            'messenger_url' => 'https://m.me/61579320037378',
            'map_url' => 'https://www.google.com/maps/search/?api=1&query=Casa+Paraiso',
            'default_payment_method' => Transaction::METHOD_CASH,
            'updated_by' => User::factory()->admin(),
        ];
    }
}
