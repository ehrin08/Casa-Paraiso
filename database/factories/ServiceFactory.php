<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $package = fake()->randomElement(config('casa.service_packages'));
        $name = $package['name'].' '.fake()->unique()->numberBetween(1, 9999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $package['description'],
            'duration_minutes' => $package['duration_minutes'],
            'price' => $package['price'],
            'is_active' => true,
        ];
    }
}
