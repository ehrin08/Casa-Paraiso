<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class PasswordSetupConfirmation
{
    private const SESSION_KEY = 'google_reauthenticated_for_password_setup';

    public function store(Request $request, User $user): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'confirmed_at' => now()->timestamp,
        ]);
    }

    public function valid(Request $request, User $user): bool
    {
        $confirmation = $request->session()->get(self::SESSION_KEY);

        if (! is_array($confirmation)) {
            return false;
        }

        $confirmedAt = filter_var($confirmation['confirmed_at'] ?? null, FILTER_VALIDATE_INT);
        $ttl = max(1, (int) config('auth.profile_password_setup_reauth_ttl', 600));
        $now = now()->timestamp;

        return (int) ($confirmation['user_id'] ?? 0) === $user->id
            && $confirmedAt !== false
            && $confirmedAt <= $now
            && $confirmedAt >= $now - $ttl;
    }

    public function consume(Request $request, User $user): bool
    {
        $valid = $this->valid($request, $user);

        $request->session()->forget(self::SESSION_KEY);

        return $valid;
    }
}
