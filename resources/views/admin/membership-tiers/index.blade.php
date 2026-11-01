<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Memberships') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Membership tiers') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Edit each card’s discounts, birthday treat, referral reward and validity. Changes apply to payments recorded from now on.') }}</p>
        </div>
        <a href="{{ route('admin.membership-tiers.create') }}" class="casa-button-primary">{{ __('Add tier') }}</a>
    </x-slot>

    <div class="grid gap-5 md:grid-cols-2" data-membership-tiers>
        @forelse ($tiers as $tier)
            <x-app-card>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-display text-2xl font-black text-casa-text">{{ $tier->name }}</h2>
                        <p class="text-casa-gold" aria-label="{{ trans_choice(':count star|:count stars', $tier->stars) }}">{{ str_repeat('★', $tier->stars) }}</p>
                    </div>
                    <x-status-badge :tone="$tier->is_active ? 'success' : 'dark'">{{ $tier->is_active ? __('Active') : __('Inactive') }}</x-status-badge>
                </div>
                <ul class="mt-4 space-y-1 text-sm leading-6">
                    @foreach ($tier->benefitLines() as $line)<li>✓ {{ $line }}</li>@endforeach
                </ul>
                <p class="mt-4 text-sm text-casa-muted">{{ trans_choice(':count active card|:count active cards', $tier->memberships_count) }} · {{ trans_choice('Valid :count month|Valid :count months', $tier->validity_months) }} · {{ __('Rank :rank', ['rank' => $tier->rank]) }}</p>
                <div class="mt-4"><a href="{{ route('admin.membership-tiers.edit', $tier) }}" class="font-bold text-casa-primary">{{ __('Edit :name', ['name' => $tier->name]) }}</a></div>
            </x-app-card>
        @empty
            <x-app-card><p class="text-sm text-casa-muted">{{ __('No membership tiers yet.') }}</p></x-app-card>
        @endforelse
    </div>
</x-app-layout>
