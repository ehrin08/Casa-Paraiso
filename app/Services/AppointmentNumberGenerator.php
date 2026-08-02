<?php

namespace App\Services;

use App\Models\Appointment;

class AppointmentNumberGenerator
{
    public function next(): string
    {
        $sequence = 1;

        do {
            $number = 'APT-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Appointment::query()->where('appointment_number', $number)->exists());

        return $number;
    }
}
