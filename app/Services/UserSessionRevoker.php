<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserSessionRevoker
{
    public function revoke(User $user, ?string $exceptSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $sessions = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id);

        if (filled($exceptSessionId)) {
            $sessions->where('id', '!=', $exceptSessionId);
        }

        $sessions->delete();
    }
}
