<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\StaffProfile;
use App\Models\TherapistCommission;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TherapistCommission>
 */
class TherapistCommissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'staff_profile_id' => StaffProfile::factory(),
            'appointment_id' => Appointment::factory(),
            'transaction_id' => Transaction::factory(),
            'primary_transaction_id' => null,
            'adjusts_commission_id' => null,
            'commission_type' => TherapistCommission::TYPE_EARNING,
            'status' => TherapistCommission::STATUS_PENDING,
            'basis_amount' => 1000,
            'commission_rate' => 0.22,
            'commission_amount' => 220,
            'earned_at' => now(),
            'paid_at' => null,
            'paid_by' => null,
            'notes' => null,
        ];
    }
}
