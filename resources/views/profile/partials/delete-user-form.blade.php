<section class="space-y-5">
    @php
        $deletionReauthTtl = max(1, (int) config('auth.profile_deletion_reauth_ttl', 600));
        $deletionReauthAmount = $deletionReauthTtl % 60 === 0 ? intdiv($deletionReauthTtl, 60) : $deletionReauthTtl;
        $deletionReauthUnit = $deletionReauthTtl % 60 === 0 ? 'minute' : 'second';
    @endphp
    <header>
        <h2 class="font-display text-lg font-black text-casa-text">Delete account</h2>
        <p class="mt-1 text-sm leading-6 text-casa-muted">Deletion permanently removes your sign-in and personal profile details. Appointment, payment, feedback, and promotion records remain under a de-identified customer account so required business history is preserved.</p>
        <p class="mt-2 text-sm leading-6 text-casa-muted">For your protection, confirm your identity before deletion. Google confirmation expires after {{ $deletionReauthAmount }} {{ \Illuminate\Support\Str::plural($deletionReauthUnit, $deletionReauthAmount) }}.</p>
    </header>
    @if (filled($user->password))
        <form method="post" action="{{ route('profile.destroy') }}" class="space-y-3">
            @csrf @method('delete')
            <div>
                <x-input-label for="deletion_password" value="Confirm with your password" />
                <x-password-input id="deletion_password" name="password" class="mt-1 block w-full" required autocomplete="current-password" />
                <x-input-error class="mt-2" :messages="$errors->get('password')" />
            </div>
            <button class="inline-flex min-h-11 items-center rounded-xl border border-red-700 px-4 py-2 text-sm font-bold text-red-800 hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-200">Delete and anonymize my account</button>
        </form>
    @endif
    @if (filled($user->google_id))
        <a href="{{ route('profile.deletion.google') }}" class="inline-flex min-h-11 items-center rounded-xl bg-red-700 px-4 py-2 text-sm font-bold text-white hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-200">Confirm with Google</a>
    @endif
    @if (session('deletion_confirmed'))
        <form method="post" action="{{ route('profile.destroy') }}">
            @csrf @method('delete')
            <button class="inline-flex min-h-11 items-center rounded-xl border border-red-700 px-4 py-2 text-sm font-bold text-red-800 hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-200">Delete and anonymize my account</button>
        </form>
    @endif
</section>
