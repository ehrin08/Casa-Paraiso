<x-dropdown align="right" width="48">
    <x-slot name="trigger">
        <button type="button" class="inline-flex min-h-10 items-center rounded-full border border-casa-border bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.08em] text-casa-primary shadow-sm transition hover:border-casa-gold">
            {{ __('Actions') }}
        </button>
    </x-slot>

    <x-slot name="content">
        <div class="space-y-1">
            {{ $slot }}
        </div>
    </x-slot>
</x-dropdown>
