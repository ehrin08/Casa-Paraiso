<section>
    <header>
        <h2 class="font-display text-lg font-black text-casa-text">
            {{ filled($user->password) ? __('Update Password') : __('Set a Password') }}
        </h2>

        <p class="mt-1 text-sm leading-6 text-casa-muted">
            @if (filled($user->password))
                {{ __('Ensure your account is using a long, random password to stay secure.') }}
            @else
                {{ __('Add a password so you can sign in with your verified email as well as Google.') }}
            @endif
        </p>
    </header>

    @if (filled($user->password) || $passwordSetupConfirmed)
        <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        @if (filled($user->password))
            <div>
                <x-input-label for="update_password_current_password" :value="__('Current Password')" />
                <x-password-input id="update_password_current_password" name="current_password" class="mt-1 block w-full" autocomplete="current-password" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>
        @else
            <div class="rounded-2xl border border-casa-green/25 bg-casa-green/10 px-4 py-3 text-sm leading-6 text-casa-green">
                {{ __('Google account confirmed. Choose your password within 10 minutes.') }}
            </div>
        @endif

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <x-password-input id="update_password_password" name="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <x-password-input id="update_password_password_confirmation" name="password_confirmation" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ filled($user->password) ? __('Save') : __('Set Password') }}</x-primary-button>
        </div>
        </form>
    @elseif (filled($user->google_id))
        <div class="mt-6 space-y-4">
            <p class="text-sm leading-6 text-casa-muted">
                {{ __('For your protection, confirm the Google account linked to this profile before creating a password.') }}
            </p>
            <x-input-error :messages="$errors->get('google')" />
            <x-input-error :messages="$errors->updatePassword->get('password')" />
            <a href="{{ route('profile.password.google') }}" class="inline-flex min-h-11 items-center rounded-xl bg-casa-primary px-4 py-2 text-sm font-bold text-white hover:bg-casa-primary-dark focus:outline-none focus:ring-4 focus:ring-casa-primary/20">
                {{ __('Confirm with Google') }}
            </a>
        </div>
    @else
        <div class="mt-6 space-y-4">
            <p class="text-sm leading-6 text-casa-muted">
                {{ __('Use the secure reset-password flow to create a password for this account.') }}
            </p>
            <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center rounded-xl border border-casa-primary px-4 py-2 text-sm font-bold text-casa-primary hover:bg-casa-primary/5 focus:outline-none focus:ring-4 focus:ring-casa-primary/20">
                {{ __('Email a Password Setup Link') }}
            </a>
        </div>
    @endif
</section>
