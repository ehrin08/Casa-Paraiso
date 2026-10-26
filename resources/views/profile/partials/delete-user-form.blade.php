<section class="space-y-5">
    @php
        $deletionReauthTtl = max(1, (int) config('auth.profile_deletion_reauth_ttl', 600));
        $deletionReauthAmount = $deletionReauthTtl % 60 === 0 ? intdiv($deletionReauthTtl, 60) : $deletionReauthTtl;
        $deletionReauthUnit = $deletionReauthTtl % 60 === 0 ? 'minute' : 'second';
        $deletionModal = 'confirm-account-deletion';
        $deletionActive = old('_modal') === $deletionModal || session('deletion_confirmed');
    @endphp
    <header>
        <h2 class="font-display text-lg font-black text-casa-text">Delete account</h2>
        <p class="mt-1 text-sm leading-6 text-casa-muted">Deletion permanently removes your sign-in and personal profile details. Appointment, payment, feedback, and promotion records remain under a de-identified customer account so required business history is preserved.</p>
    </header>
    <button type="button" class="casa-danger-button" aria-haspopup="dialog" x-data="" x-on:click="$dispatch('open-modal', '{{ $deletionModal }}')">Delete account</button>

    <x-modal :name="$deletionModal" :show="(bool) $deletionActive" maxWidth="lg" labelled-by="account-deletion-title" centered focusable>
        <x-modal-shell title="Delete your account?" title-id="account-deletion-title" :close-modal="$deletionModal">
            <p class="text-sm leading-6 text-casa-muted">For your protection, confirm your identity before deletion. Google confirmation expires after {{ $deletionReauthAmount }} {{ \Illuminate\Support\Str::plural($deletionReauthUnit, $deletionReauthAmount) }}.</p>

            @if (session('deletion_confirmed'))
                <form method="post" action="{{ route('profile.destroy') }}" id="account-deletion-confirmed">
                    @csrf @method('delete')
                    <p class="text-sm font-bold text-casa-text">Google confirmed your identity.</p>
                </form>
            @elseif (filled($user->password))
                <form method="post" action="{{ route('profile.destroy') }}" id="account-deletion-password">
                    @csrf @method('delete')
                    <input type="hidden" name="_modal" value="{{ $deletionModal }}">
                    <x-input-label for="deletion_password" value="Confirm with your password" />
                    <x-password-input id="deletion_password" name="password" class="mt-1 block w-full" required autocomplete="current-password" />
                    <x-input-error class="mt-2" :messages="$deletionActive ? $errors->get('password') : []" />
                </form>
            @endif

            @if (filled($user->google_id) && ! session('deletion_confirmed'))
                <a href="{{ route('profile.deletion.google') }}" class="casa-button-secondary">Confirm with Google</a>
            @endif

            <x-slot name="footer">
                <button type="button" class="casa-button-secondary" x-on:click="$dispatch('close-modal', '{{ $deletionModal }}')">Keep my account</button>
                @if (session('deletion_confirmed'))
                    <button type="submit" form="account-deletion-confirmed" class="casa-danger-button">Delete and anonymize my account</button>
                @elseif (filled($user->password))
                    <button type="submit" form="account-deletion-password" class="casa-danger-button">Delete and anonymize my account</button>
                @endif
            </x-slot>
        </x-modal-shell>
    </x-modal>
</section>
