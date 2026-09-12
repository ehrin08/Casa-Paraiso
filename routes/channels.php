<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}.booking-notifications', function (User $user, int $userId): bool {
    return (string) $user->getAuthIdentifier() === (string) $userId
        && $user->is_active
        && $user->hasVerifiedEmail()
        && ($user->isAdmin() || $user->isReceptionist() || $user->isStaff());
});
