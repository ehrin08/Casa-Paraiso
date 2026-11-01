<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use Illuminate\Validation\ValidationException;

class CustomerBookingGuard
{
    public const RESTRICTION_ADMIN_BLOCK = 'admin_block';

    public const RESTRICTION_NO_SHOWS = 'no_shows';

    public const RESTRICTION_UPCOMING_CAP = 'upcoming_cap';

    /**
     * Returns the first rule that stops online self-booking, or null when the customer may book.
     */
    public function restriction(CustomerProfile $profile): ?string
    {
        if ($profile->isOnlineBookingBlocked()) {
            return self::RESTRICTION_ADMIN_BLOCK;
        }

        if ($this->noShowCount($profile) >= (int) config('casa.customer_booking_limits.no_show_threshold', 2)) {
            return self::RESTRICTION_NO_SHOWS;
        }

        if ($this->upcomingSelfBookingCount($profile) >= $this->maxUpcoming()) {
            return self::RESTRICTION_UPCOMING_CAP;
        }

        return null;
    }

    public function noShowCount(CustomerProfile $profile): int
    {
        return Appointment::query()
            ->where('customer_profile_id', $profile->id)
            ->where('status', Appointment::STATUS_NO_SHOW)
            ->where('scheduled_start_at', '>=', now()->subDays((int) config('casa.customer_booking_limits.no_show_lookback_days', 90)))
            ->count();
    }

    /**
     * Counts upcoming confirmed bookings the customer made themselves; staff-created bookings never count.
     */
    public function upcomingSelfBookingCount(CustomerProfile $profile): int
    {
        if (! $profile->user_id) {
            return 0;
        }

        return Appointment::query()
            ->where('customer_profile_id', $profile->id)
            ->where('created_by', $profile->user_id)
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where('scheduled_start_at', '>', now())
            ->count();
    }

    public function message(string $restriction): string
    {
        return match ($restriction) {
            self::RESTRICTION_UPCOMING_CAP => __('You already have :max upcoming online bookings. You can book again once one is completed or cancelled.', ['max' => $this->maxUpcoming()]),
            default => __('Online booking is paused for your account. Please call or visit the spa to book.'),
        };
    }

    /**
     * @throws ValidationException
     */
    public function assertCanBook(CustomerProfile $profile): void
    {
        $restriction = $this->restriction($profile);

        if ($restriction !== null) {
            throw ValidationException::withMessages(['booking' => $this->message($restriction)]);
        }
    }

    private function maxUpcoming(): int
    {
        return (int) config('casa.customer_booking_limits.max_upcoming', 2);
    }
}
