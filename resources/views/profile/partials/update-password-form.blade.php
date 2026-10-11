<section class="profile-panel profile-form-panel" aria-labelledby="profile-password-title">
    <header>
        <h2 id="profile-password-title" class="font-display text-lg font-black text-casa-text">
            {{ filled($user->password) ? __('Password') : __('Set a Password') }}
        </h2>

        <p class="mt-1 text-sm leading-6 text-casa-muted">
            @if (filled($user->password))
                {{ __('Update your password to keep your account secure.') }}
            @else
                {{ __('Add a password so you can sign in with your verified email as well as Google.') }}
            @endif
        </p>
    </header>

    @if (filled($user->password) || $passwordSetupConfirmed)
        <form method="post" action="{{ route('password.update') }}" class="profile-form">
        @csrf
        @method('put')

        @if (filled($user->password))
            <div>
                <x-input-label for="update_password_current_password" :value="__('Current Password')" />
                <x-password-input id="update_password_current_password" name="current_password" class="mt-1 block w-full" autocomplete="current-password" :placeholder="__('Enter current password')" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>
        @else
            <div class="rounded-2xl border border-casa-green/25 bg-casa-green/10 px-4 py-3 text-sm leading-6 text-casa-green">
                {{ __('Google account confirmed. Choose your password within 10 minutes.') }}
            </div>
        @endif

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <x-password-input id="update_password_password" name="password" class="mt-1 block w-full" autocomplete="new-password" :placeholder="__('Enter new password')" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <x-password-input id="update_password_password_confirmation" name="password_confirmation" class="mt-1 block w-full" autocomplete="new-password" :placeholder="__('Confirm new password')" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="profile-form-actions">
            <x-primary-button>{{ filled($user->password) ? __('Update password') : __('Set Password') }}</x-primary-button>
        </div>
        </form>
    @elseif (filled($user->google_id))
        <div class="profile-password-setup">
            <p class="text-sm leading-6 text-casa-muted">
                {{ __('For your protection, confirm the Google account linked to this profile before creating a password.') }}
            </p>
            <x-input-error :messages="$errors->get('google')" />
            <x-input-error :messages="$errors->updatePassword->get('password')" />
            <a href="{{ route('profile.password.google') }}" class="casa-button-primary profile-setup-action">
                {{ __('Confirm with Google') }}
            </a>
        </div>
    @else
        <div class="profile-password-setup">
            <p class="text-sm leading-6 text-casa-muted">
                {{ __('Use the secure reset-password flow to create a password for this account.') }}
            </p>
            <a href="{{ route('password.request') }}" class="casa-button-secondary profile-setup-action">
                {{ __('Email a Password Setup Link') }}
            </a>
        </div>
    @endif
</section>
