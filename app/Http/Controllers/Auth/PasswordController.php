<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordSetupConfirmation;
use App\Services\UserSessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(
        Request $request,
        PasswordSetupConfirmation $passwordSetupConfirmation,
        UserSessionRevoker $sessionRevoker
    ): RedirectResponse {
        $user = $request->user();
        $settingInitialPassword = blank($user->password);

        if ($settingInitialPassword && ! $passwordSetupConfirmation->valid($request, $user)) {
            $exception = ValidationException::withMessages([
                'password' => 'Confirm your linked Google account before setting a password.',
            ]);
            $exception->errorBag = 'updatePassword';

            throw $exception;
        }

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => $settingInitialPassword
                ? ['prohibited']
                : ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        if ($settingInitialPassword && ! $passwordSetupConfirmation->consume($request, $user)) {
            $exception = ValidationException::withMessages([
                'password' => 'Your Google confirmation expired. Confirm your account again.',
            ]);
            $exception->errorBag = 'updatePassword';

            throw $exception;
        }

        DB::transaction(function () use ($user, $validated, $settingInitialPassword): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($settingInitialPassword && filled($lockedUser->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'A password already exists. Refresh the page and enter your current password.',
                ]);
            }

            $lockedUser->forceFill([
                'password' => Hash::make($validated['password']),
                'remember_token' => Str::random(60),
            ])->save();
        });

        $sessionRevoker->revoke($user, $request->session()->getId());
        $request->session()->regenerate();

        return back()->with('status', $settingInitialPassword ? 'password-set' : 'password-updated');
    }
}
