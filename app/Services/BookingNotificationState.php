<?php

namespace App\Services;

use App\Events\BookingNotificationsChanged;
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
        $updated = $user->unreadNotifications()
            ->where('type', NewCustomerBookingNotification::class)
            ->update(['read_at' => now()]);

        if ($updated > 0) {
            $this->notifyChanged($user);
        }

        return $this->unreadCount($user);
    }

    public function notifyChanged(User $user): void
    {
        event(new BookingNotificationsChanged($user->id));
    }
}
