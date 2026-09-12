<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;

class BookingNotificationsChanged implements ShouldBroadcastNow, ShouldRescue
{
    public function __construct(private readonly int $userId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->userId.'.booking-notifications');
    }

    public function broadcastAs(): string
    {
        return 'booking-notifications.changed';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
