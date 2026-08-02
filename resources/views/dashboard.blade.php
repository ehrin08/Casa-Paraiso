<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Workspace') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Dashboard') }}</h1>
        </div>
    </x-slot>

    <x-app-card>
        {{ __("You're logged in!") }}
    </x-app-card>
</x-app-layout>
