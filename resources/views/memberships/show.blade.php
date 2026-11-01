<x-app-layout>
    @php $lifecycle = $membership->lifecycle(); @endphp
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Membership card') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $membership->card_number }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ $membership->customerProfile?->display_name }} · {{ $membership->tier?->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($membership->status === \App\Models\CustomerMembership::STATUS_ACTIVE)
                <x-confirm-action :action="route($routePrefix.'memberships.renew', $membership)" method="PATCH" :label="__('Renew')" button-class="casa-button-primary" :confirm-title="__('Renew this card?')" :confirm-message="__('The card is extended by :months from its current valid-until date, or from today if it has expired.', ['months' => trans_choice(':count month|:count months', $membership->tier?->validity_months ?? 12)])" :confirm-button="__('Renew')" />
                @if (request()->user()->isAdmin())
                    <x-confirm-action :action="route('admin.memberships.cancel', $membership)" method="PATCH" :label="__('Cancel card')" button-class="casa-button-secondary" :confirm-title="__('Cancel this card?')" :confirm-message="__('Member benefits stop immediately. Past payments keep their recorded benefits.')" :confirm-button="__('Cancel card')" tone="danger" />
                @endif
            @endif
            <a href="{{ route($routePrefix.'memberships.index') }}" class="casa-button-secondary">{{ __('All memberships') }}</a>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]" data-membership-detail>
        <x-app-card>
            <x-input-error :messages="$errors->get('membership')" />
            <dl class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Status') }}</dt><dd class="mt-2"><x-status-badge :tone="match ($lifecycle) { 'active' => 'success', 'expired' => 'warning', default => 'dark' }">{{ ucfirst($lifecycle) }}</x-status-badge></dd></div>
                <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Tier') }}</dt><dd class="mt-2 font-semibold">{{ $membership->tier?->name }} <span aria-hidden="true" class="text-casa-gold">{{ str_repeat('★', $membership->tier?->stars ?? 0) }}</span></dd></div>
                <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Issued') }}</dt><dd class="mt-2 font-semibold">{{ $membership->issued_at->format('M d, Y') }} · {{ $membership->issuer?->name }}</dd></div>
                <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Valid until') }}</dt><dd class="mt-2 font-semibold">{{ $membership->valid_until->format('M d, Y') }}</dd></div>
                <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Referred by') }}</dt><dd class="mt-2 font-semibold">{{ $membership->referrer?->display_name ?? __('No referral') }}</dd></div>
                <div class="rounded-2xl bg-casa-bg p-4"><dt class="casa-section-label">{{ __('Birthday treats used') }}</dt><dd class="mt-2 font-semibold">{{ $membership->perkUsages->count() }}</dd></div>
            </dl>
            @if ($membership->notes)
                <p class="mt-5 whitespace-pre-line text-sm leading-6 text-casa-muted">{{ $membership->notes }}</p>
            @endif
            @if ($membership->referralCredits->isNotEmpty())
                <h2 class="mt-6 font-display text-lg font-black">{{ __('Referral credits from this card') }}</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($membership->referralCredits as $credit)
                        <li class="flex justify-between gap-3 rounded-2xl bg-casa-bg px-4 py-3"><span>{{ $credit->customerProfile?->display_name }} · PHP {{ number_format((float) $credit->amount, 2) }}</span><span class="text-casa-muted">{{ $credit->used_at ? __('Used :date', ['date' => $credit->used_at->format('M d, Y')]) : __('Unused') }}</span></li>
                    @endforeach
                </ul>
            @endif
        </x-app-card>
        <x-app-card>
            <p class="casa-section-label">{{ __('Benefits') }}</p>
            <ul class="mt-3 space-y-2 text-sm leading-6">
                @forelse ($membership->tier?->benefitLines() ?? [] as $line)
                    <li>✓ {{ $line }}</li>
                @empty
                    <li class="text-casa-muted">{{ __('No benefits configured for this tier.') }}</li>
                @endforelse
            </ul>
        </x-app-card>
    </div>
</x-app-layout>
