<x-app-layout>
    <x-slot name="header">
        <div class="commissions-heading">
            <h1 class="font-display text-3xl font-black text-casa-text">{{ __('Commission details') }}</h1>
            <p>{{ __('Review the service earning and its recorded payout.') }}</p>
        </div>
        <a href="{{ route('admin.commissions.index') }}" class="casa-button-secondary">{{ __('All commissions') }}</a>
    </x-slot>

    <div class="commissions-workspace commissions-standalone" data-commissions-workspace>
        <x-commission-detail :commission="$commission" />
    </div>
</x-app-layout>
