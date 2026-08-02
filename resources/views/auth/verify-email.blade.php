<x-guest-layout>
    <x-slot name="title">{{ __('Verify your email') }}</x-slot>
    <x-slot name="subtitle">{{ __('Check your inbox for the verification link before continuing.') }}</x-slot>

    <div class="mb-4 text-sm leading-6 text-casa-muted">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="text-sm font-semibold text-casa-primary underline underline-offset-4 transition hover:text-casa-primary-dark">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
