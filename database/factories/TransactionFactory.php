<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'transaction_number' => 'TRX-'.fake()->unique()->numerify('######'),
            'customer_profile_id' => CustomerProfile::factory(),
            'appointment_id' => Appointment::factory(),
            'service_id' => Service::factory(),
            'amount' => fake()->randomFloat(2, 600, 2500),
            'payment_status' => Transaction::PAYMENT_PAID,
            'payment_method' => Transaction::METHOD_CASH,
            'paid_at' => now(),
            'recorded_by' => User::factory()->staff(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
