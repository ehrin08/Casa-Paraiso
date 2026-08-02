<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordSetupConfirmation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GooglePasswordSetupController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(blank($request->user()->password) && filled($request->user()->google_id), 403);

        return Socialite::driver('google')
            ->redirectUrl(route('profile.password.google.callback'))
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request, PasswordSetupConfirmation $confirmation): RedirectResponse
    {
        $user = $request->user();

        abort_unless(blank($user->password) && filled($user->google_id), 403);

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route('profile.password.google.callback'))
                ->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('profile.edit')->withErrors([
                'google' => 'Google confirmation was not completed. Please try again.',
            ]);
        }

        if ((string) $googleUser->getId() !== (string) $user->google_id) {
            return redirect()->route('profile.edit')->withErrors([
                'google' => 'Please confirm with the Google account linked to this profile.',
            ]);
        }

        $confirmation->store($request, $user);

        return redirect()->route('profile.edit')->with('status', 'password-identity-confirmed');
    }
}
