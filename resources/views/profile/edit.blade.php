<x-app-layout>
    @php
        $initials = collect(preg_split('/\s+/', trim($user->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $roleLabel = match (true) {
            $user->isSuperAdmin() => __('Super administrator'),
            $user->isAdmin() => __('Administrator'),
            $user->isReceptionist() => __('Receptionist'),
            $user->isStaff() => __('Therapist'),
            default => __('Customer'),
        };
    @endphp
    <x-slot name="header">
        <div>
            <h1 class="profile-page-title font-display text-3xl font-black text-casa-text">My profile</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">Manage your personal information and account settings.</p>
        </div>
    </x-slot>

    <div class="profile-workspace">
        <section class="profile-panel profile-identity" aria-labelledby="profile-identity-name">
            <div class="profile-avatar" aria-hidden="true">{{ $initials }}</div>
            <div class="profile-identity-copy">
                <h2 id="profile-identity-name">{{ $user->name }}</h2>
                <p class="profile-role">{{ $roleLabel }}</p>
                <p class="profile-help">Manage your personal information and account security.</p>
            </div>
        </section>
        <div class="profile-form-grid">
            @include('profile.partials.update-profile-information-form')
            @include('profile.partials.update-password-form')
        </div>
        @if ($user->isCustomer())
            <div id="delete-account" class="profile-panel profile-deletion">
                @include('profile.partials.delete-user-form')
            </div>
        @endif
    </div>
</x-app-layout>
