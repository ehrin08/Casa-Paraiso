<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleDeletionController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(request()->user()->isCustomer(), 403);

        return Socialite::driver('google')
            ->redirectUrl(route('profile.deletion.google.callback'))
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless(request()->user()->isCustomer(), 403);

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(route('profile.deletion.google.callback'))
                ->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('profile.edit')->withErrors(['google' => 'Google confirmation was not completed.']);
        }

        if ((string) $googleUser->getId() !== (string) request()->user()->google_id) {
            return redirect()->route('profile.edit')->withErrors(['google' => 'Please confirm with the Google account linked to this profile.']);
        }

        request()->session()->put('google_reauthenticated_for_deletion', [
            'user_id' => request()->user()->id,
            'confirmed_at' => now()->timestamp,
        ]);

        return redirect()->route('profile.edit')->with('deletion_confirmed', true);
    }
}
