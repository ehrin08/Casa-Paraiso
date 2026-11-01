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
        // Tests rely on the full-length packages; the 30-minute back massage is created explicitly.
        $package = fake()->randomElement(collect(config('casa.service_packages'))->where('slug', '!=', 'back-massage')->values()->all());
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
