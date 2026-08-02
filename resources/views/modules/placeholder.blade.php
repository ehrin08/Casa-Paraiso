<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ $eyebrow }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $title }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ $description }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <x-status-badge tone="warning">{{ $status }}</x-status-badge>
            @isset($actionRoute)
                <a href="{{ route($actionRoute) }}" class="casa-button-secondary">
                    {{ $actionLabel ?? __('Back') }}
                </a>
            @endisset
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="casa-dark-panel rounded-[24px] p-6 shadow-casa-card sm:p-8">
            <div class="max-w-3xl">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-casa-gold">{{ __('Workspace ready') }}</p>
                <h2 class="mt-3 font-display text-2xl font-black text-white">{{ $title }}</h2>
                <p class="mt-3 text-sm leading-7 text-casa-bg/80">
                    {{ __('This area is protected, styled, and ready for the module workflow.') }}
                </p>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            @foreach ($cards ?? [] as $card)
                <div class="casa-card-compact p-5">
                    <p class="text-xs font-black uppercase tracking-[0.12em] text-casa-muted">{{ $card['label'] }}</p>
                    <p class="mt-3 font-display text-xl font-black text-casa-text">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </section>

        <x-empty-state
            title="{{ __('Module workflow pending') }}"
            description="{{ __('Operational records will appear here as this module is connected to the next implementation phase.') }}"
        />
    </div>
</x-app-layout>
