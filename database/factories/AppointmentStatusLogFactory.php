<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentStatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentStatusLog>
 */
class AppointmentStatusLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'from_status' => null,
            'to_status' => Appointment::STATUS_CONFIRMED,
            'changed_by' => User::factory()->staff(),
            'reason' => fake()->optional()->sentence(),
        ];
    }
}
