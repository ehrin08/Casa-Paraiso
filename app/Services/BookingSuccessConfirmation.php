<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Http\Request;

/**
 * One-time success pop-up shown after a booking is saved.
 */
class BookingSuccessConfirmation
{
    public const FLASH_KEY = 'booked_appointment_id';

    public function consume(Request $request): ?Appointment
    {
        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->pull(self::FLASH_KEY);
        $user = $request->user();

        if (! is_numeric($id) || ! $user) {
            return null;
        }

        $query = Appointment::query()->whereKey($id);

        if ($user->isCustomer()) {
            $profileId = $user->customerProfile?->id;

            if (! $profileId) {
                return null;
            }

            $query->where('customer_profile_id', $profileId);
        } elseif (! ($user->isAdmin() || $user->isReceptionist())) {
            return null;
        }

        return $query->with(['service', 'staffProfile.user', 'customerProfile.user'])->first();
    }

    public function detailUrl(Request $request, Appointment $appointment): string
    {
        $user = $request->user();

        return match (true) {
            $user->isCustomer() => route('customer.appointments.show', $appointment),
            $user->isReceptionist() => route('reception.appointments.show', $appointment),
            default => route('admin.appointments.show', $appointment),
        };
    }
}
