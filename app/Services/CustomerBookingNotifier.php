<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\NewCustomerBookingNotification;
use Illuminate\Support\Facades\Notification;

class CustomerBookingNotifier
{
    public function __construct(private readonly BookingNotificationState $state) {}

    public function notify(Appointment $appointment): void
    {
        $appointment->loadMissing(['customerProfile.user', 'service', 'staffProfile.user']);

        $operationsUsers = User::query()
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_RECEPTIONIST])
            ->get();
        $therapist = $appointment->staffProfile?->user;

        $recipients = $operationsUsers
            ->when($therapist?->is_active, fn ($users) => $users->push($therapist))
            ->unique('id')
            ->values();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new NewCustomerBookingNotification($appointment));

            $recipients->each(fn (User $recipient) => $this->state->notifyChanged($recipient));
        }
    }
}
