<section id="team-card" class="profile-panel profile-form-panel profile-team-card" aria-labelledby="profile-team-card-title">
    <header>
        <h2 id="profile-team-card-title" class="font-display text-lg font-black text-casa-text">Public team card</h2>
        <p class="mt-1 text-sm leading-6 text-casa-muted">
            Your photo also appears as your avatar in the workspace.
            @if ($user->show_on_org_chart && $user->org_chart_tier)
                It is currently shown on the Casa Paraiso website.
            @else
                An administrator decides whether it is shown on the website.
            @endif
        </p>
    </header>
    @if (session('status') === 'team-card-updated')
        <p class="mt-3 text-sm font-semibold text-casa-success" role="status">Team card saved.</p>
    @endif
    <form method="post" action="{{ route('profile.team-card.update') }}" enctype="multipart/form-data" data-turbo="false" class="profile-form">
        @csrf @method('patch')
        <x-team-photo-field :user="$user" />
        <div>
            <x-input-label for="public_title" value="Public title (optional)" />
            <x-text-input id="public_title" name="public_title" type="text" class="mt-1 block w-full" :value="old('public_title', $user->public_title)" maxlength="80" :placeholder="$user->roleLabel()" />
            <x-input-error class="mt-2" :messages="$errors->get('public_title')" />
        </div>
        <div>
            <x-input-label for="public_bio" value="Short introduction (optional)" />
            <textarea id="public_bio" name="public_bio" rows="3" maxlength="280" class="casa-input mt-1 block w-full">{{ old('public_bio', $user->public_bio) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('public_bio')" />
        </div>
        <div class="profile-form-actions"><x-primary-button>Save team card</x-primary-button></div>
    </form>
</section>
