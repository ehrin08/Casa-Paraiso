<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\NewCustomerBookingNotification;

class BookingNotificationState
{
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()
            ->where('type', NewCustomerBookingNotification::class)
            ->count();
    }

    public function acknowledge(User $user): int
    {
        $user->unreadNotifications()
            ->where('type', NewCustomerBookingNotification::class)
            ->update(['read_at' => now()]);

        return $this->unreadCount($user);
    }
}
