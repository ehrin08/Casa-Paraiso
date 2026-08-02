<x-guest-layout :fit-desktop-viewport="true">
    <x-slot name="eyebrow">Your calm doorway</x-slot>
    <x-slot name="title">Welcome to Casa Paraiso</x-slot>
    <x-slot name="subtitle">Sign in with your email and password or continue securely with Google.</x-slot>

    @if (session('auth_notice'))
        <div class="mb-5 rounded-2xl border border-casa-border bg-casa-sand px-4 py-3 text-sm leading-6 text-casa-text" role="status">
            {{ session('auth_notice') }}
        </div>
    @endif

    @if ($errors->has('google'))
        <div class="mb-5 rounded-2xl border border-red-300 bg-red-50 px-4 py-3 text-sm leading-6 text-red-800" role="alert">
            {{ $errors->first('google') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <div class="flex items-center justify-between gap-4">
                <x-input-label for="password" value="Password" />
                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-casa-primary hover:text-casa-primary-dark">Forgot password?</a>
            </div>
            <x-password-input id="password" class="mt-1 block w-full" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <label class="inline-flex min-h-[44px] cursor-pointer items-center gap-3 py-2 text-sm font-semibold text-casa-muted">
            <input type="checkbox" name="remember" class="size-5 rounded border-casa-border text-casa-primary focus:ring-casa-gold">
            <span>Remember me</span>
        </label>
        <x-primary-button class="w-full justify-center">Sign in</x-primary-button>
    </form>

    <div class="my-6 flex items-center gap-3" aria-hidden="true"><span class="h-px flex-1 bg-casa-border"></span><span class="text-xs font-bold uppercase tracking-wider text-casa-muted">or</span><span class="h-px flex-1 bg-casa-border"></span></div>

    <a href="{{ route('auth.google.redirect') }}"
       class="group flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl bg-casa-primary px-5 py-4 text-sm font-extrabold text-white shadow-casa-lift transition duration-200 hover:bg-casa-primary-dark focus:outline-none focus:ring-4 focus:ring-casa-gold/35">
        <span class="grid h-8 w-8 place-items-center rounded-full bg-white" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5">
                <path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.4-.18-2.07H12v3.92h5.38a4.6 4.6 0 0 1-2 3.02v2.54h3.24c1.9-1.75 2.98-4.33 2.98-7.41Z"/>
                <path fill="#34A853" d="M12 22c2.7 0 4.97-.9 6.62-2.43l-3.24-2.54c-.9.6-2.05.96-3.38.96-2.61 0-4.82-1.76-5.61-4.13H3.05v2.62A10 10 0 0 0 12 22Z"/>
                <path fill="#FBBC05" d="M6.39 13.86A6.02 6.02 0 0 1 6.08 12c0-.65.11-1.28.31-1.86V7.52H3.05A10 10 0 0 0 2 12c0 1.61.39 3.14 1.05 4.48l3.34-2.62Z"/>
                <path fill="#EA4335" d="M12 6.01c1.47 0 2.79.51 3.83 1.5l2.87-2.88A9.63 9.63 0 0 0 12 2a10 10 0 0 0-8.95 5.52l3.34 2.62C7.18 7.77 9.39 6.01 12 6.01Z"/>
            </svg>
        </span>
        <span>Continue with Google</span>
        <span class="transition-transform duration-200 group-hover:translate-x-1" aria-hidden="true">→</span>
    </a>

    <p class="mt-5 text-center text-sm leading-6 text-casa-muted">
        By continuing, you agree to Casa Paraiso’s transparent data practices.
        <a href="{{ route('privacy-policy') }}" class="font-bold text-casa-primary underline decoration-casa-gold underline-offset-4 transition hover:text-casa-primary-dark focus:outline-none focus:ring-2 focus:ring-casa-gold focus:ring-offset-2">Read the Security &amp; Privacy Policy</a>.
    </p>

    <details data-login-instructions class="group mt-6 overflow-hidden rounded-2xl border border-casa-border bg-casa-paper">
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-bold text-casa-text transition hover:bg-casa-sand/55 [&::-webkit-details-marker]:hidden">
            <span>Sign-in instructions</span>
            <svg viewBox="0 0 20 20" class="size-4 shrink-0 text-casa-primary transition-transform duration-200 motion-reduce:transition-none group-open:rotate-180" fill="none" aria-hidden="true">
                <path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </summary>
        <div class="grid gap-3 border-t border-casa-border bg-casa-sand/30 p-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-casa-border bg-casa-paper p-4">
                <p class="text-xs font-extrabold uppercase tracking-wider text-casa-primary">Guests</p>
                <p class="mt-2 text-sm leading-6 text-casa-muted">Sign in with your email and password, or continue with your verified Google account.</p>
            </div>
            <div class="rounded-2xl border border-casa-border bg-casa-paper p-4">
                <p class="text-xs font-extrabold uppercase tracking-wider text-casa-primary">Team</p>
                <p class="mt-2 text-sm leading-6 text-casa-muted">Use your pre-authorized email. Select “Forgot password?” to establish your first password.</p>
            </div>
        </div>
    </details>

</x-guest-layout>
