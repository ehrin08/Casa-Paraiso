<?php

namespace App\Services;

use App\Models\CustomerProfile;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleIdentity
{
    public function resolve(SocialiteUser $googleUser): User
    {
        $email = strtolower(trim((string) $googleUser->getEmail()));
        $googleId = trim((string) $googleUser->getId());
        $verified = filter_var(data_get($googleUser, 'user.verified_email', false), FILTER_VALIDATE_BOOL);

        if ($email === '' || $googleId === '' || ! $verified) {
            throw new DomainException('Casa Paraiso requires a verified Google email address.');
        }

        return DB::transaction(function () use ($googleUser, $email, $googleId): User {
            $superAdminEmail = strtolower((string) config('auth.super_admin_email'));
            $byGoogleId = User::query()->where('google_id', $googleId)->lockForUpdate()->first();
            $byEmail = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();

            if ($byGoogleId && $byEmail && ! $byGoogleId->is($byEmail)) {
                throw new DomainException('This Google identity is already linked to another account.');
            }

            $user = $byGoogleId ?? $byEmail ?? new User;

            if ($user->exists && ! $user->is_active) {
                throw new DomainException('This account is inactive. Please contact the super administrator.');
            }

            if ($user->exists && $user->google_id && $user->google_id !== $googleId) {
                throw new DomainException('This email is linked to a different Google identity.');
            }

            $user->fill([
                'name' => trim((string) $googleUser->getName()) ?: ($user->name ?: Str::before($email, '@')),
                'email' => $email,
                'google_id' => $googleId,
                'role' => $email === $superAdminEmail
                    ? User::ROLE_SUPER_ADMIN
                    : ($user->role ?: User::ROLE_CUSTOMER),
                'is_active' => true,
            ]);
            $user->email_verified_at = $user->email_verified_at ?? now();
            $user->save();

            if ($user->isCustomer()) {
                CustomerProfile::provisionFor($user);
            }

            return $user;
        });
    }
}
