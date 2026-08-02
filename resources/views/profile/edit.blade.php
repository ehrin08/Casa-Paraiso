<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">Account settings</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">Profile</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">Keep your contact details and sign-in security current.</p>
        </div>
    </x-slot>

    <div class="grid gap-4 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <aside class="casa-card p-3">
            <div class="casa-nav-control casa-nav-control-active">Profile details</div>
            @if ($user->isCustomer())
                <a href="#delete-account" class="casa-nav-control">Delete account</a>
            @endif
        </aside>
        <div class="space-y-4">
            <x-app-card padding="p-4 sm:p-6"><div class="max-w-2xl">@include('profile.partials.update-profile-information-form')</div></x-app-card>
            <x-app-card padding="p-4 sm:p-6"><div class="max-w-2xl">@include('profile.partials.update-password-form')</div></x-app-card>
            @if ($user->isCustomer())
                <x-app-card padding="p-4 sm:p-6"><div id="delete-account" class="max-w-2xl">@include('profile.partials.delete-user-form')</div></x-app-card>
            @endif
        </div>
    </div>
</x-app-layout>
