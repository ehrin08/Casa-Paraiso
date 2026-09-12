<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewCustomerBookingNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Appointment $appointment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string|null> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
            'appointment_number' => $this->appointment->appointment_number,
            'customer_name' => $this->appointment->customerProfile?->user?->name,
            'service_name' => $this->appointment->service?->name,
            'therapist_name' => $this->appointment->staffProfile?->user?->name,
            'scheduled_start_at' => $this->appointment->scheduled_start_at?->toIso8601String(),
        ];
    }
}
