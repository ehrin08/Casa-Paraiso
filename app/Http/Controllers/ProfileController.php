<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Services\PasswordSetupConfirmation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, PasswordSetupConfirmation $passwordSetupConfirmation): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'passwordSetupConfirmed' => $passwordSetupConfirmation->valid($request, $request->user()),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($data, $user): void {
            $user->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
            ]);

            if ($user->isCustomer()) {
                CustomerProfile::provisionFor($user)->update([
                    'address' => $data['address'] ?? null,
                    'contact_preference' => $data['contact_preference'] ?? null,
                ]);
            }
        });

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isCustomer() && $this->consumeValidDeletionConfirmation($request, $user), 403);

        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            abort_unless($lockedUser->isCustomer(), 403);

            $profile = CustomerProfile::withTrashed()
                ->where('user_id', $lockedUser->id)
                ->lockForUpdate()
                ->first();

            $profile?->anonymize();

            $lockedUser->forceFill([
                'name' => 'Deleted customer',
                'email' => "deleted-customer-{$lockedUser->id}@accounts.invalid",
                'google_id' => null,
                'phone' => null,
                'password' => null,
                'email_verified_at' => null,
                'is_active' => false,
                'remember_token' => null,
            ])->save();
        });

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    private function consumeValidDeletionConfirmation(Request $request, User $user): bool
    {
        $throttleKey = "profile-deletion|{$user->id}|{$request->ip()}";
        $password = $request->input('password');

        if (filled($user->password) && is_string($password) && $password !== '') {
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                throw ValidationException::withMessages([
                    'password' => 'Too many confirmation attempts. Please wait before trying again.',
                ]);
            }

            if (Hash::check($password, $user->password)) {
                RateLimiter::clear($throttleKey);

                return true;
            }

            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        $confirmation = $request->session()->pull('google_reauthenticated_for_deletion');

        if (! is_array($confirmation)) {
            if (filled($user->password)) {
                throw ValidationException::withMessages([
                    'password' => 'Enter your password to confirm account deletion.',
                ]);
            }

            return false;
        }

        $confirmedAt = filter_var($confirmation['confirmed_at'] ?? null, FILTER_VALIDATE_INT);
        $ttl = max(1, (int) config('auth.profile_deletion_reauth_ttl', 600));
        $now = now()->timestamp;

        return (int) ($confirmation['user_id'] ?? 0) === $user->id
            && $confirmedAt !== false
            && $confirmedAt <= $now
            && $confirmedAt >= $now - $ttl;
    }
}
