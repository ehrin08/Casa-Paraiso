<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Admin payments') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Therapist commissions') }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ __('Review earnings and record externally settled payouts.') }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="casa-metric-grid grid gap-3 md:grid-cols-3" data-metric-grid>
            <x-metric-card label="Pending" value="PHP {{ number_format((float) $totals['pending'], 2) }}" meta="Awaiting settlement" tone="gold" />
            <x-metric-card label="Paid" value="PHP {{ number_format((float) $totals['paid'], 2) }}" meta="Recorded payouts" tone="green" />
            <x-metric-card label="Net" value="PHP {{ number_format((float) $totals['net'], 2) }}" meta="Earnings and adjustments" tone="brown" />
        </section>

        <x-app-card>
            <x-list-toolbar eyebrow="{{ __('Commissions') }}" title="{{ __('Commission records') }}" :count="$commissions->total()" :reset-url="route('admin.commissions.index')" :active-filters="collect(request()->only(['staff_profile_id', 'status', 'date_from', 'date_to']))->filter(fn ($value) => filled($value))->count()" :collapsible="true">
                <form method="GET" action="{{ route('admin.commissions.index') }}" class="casa-filter-grid lg:min-w-[52rem]">
                    <select name="staff_profile_id" class="casa-input"><option value="">{{ __('All therapists') }}</option>@foreach($staffProfiles as $staff)<option value="{{ $staff->id }}" @selected($staffProfileId === $staff->id)>{{ $staff->user?->name }}</option>@endforeach</select>
                    <select name="status" class="casa-input"><option value="">{{ __('All statuses') }}</option>@foreach(\App\Models\TherapistCommission::STATUSES as $option)<option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>@endforeach</select>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="casa-input" aria-label="{{ __('Earned from') }}">
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="casa-input" aria-label="{{ __('Earned through') }}">
                    <button class="casa-button-secondary">{{ __('Filter') }}</button>
                </form>
            </x-list-toolbar>

            <div class="mt-5">
                @if($commissions->isEmpty())
                    <x-empty-state title="{{ __('No commissions yet') }}" description="{{ __('Fully paid completed services will appear here.') }}" />
                @else
                    <x-table-shell label="{{ __('Therapist commission records') }}">
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted"><tr><th class="px-4 py-3">{{ __('Therapist') }}</th><th class="px-4 py-3">{{ __('Service') }}</th><th class="px-4 py-3">{{ __('Appointment') }}</th><th class="px-4 py-3">{{ __('Transaction') }}</th><th class="px-4 py-3">{{ __('Type') }}</th><th class="px-4 py-3">{{ __('Basis') }}</th><th class="px-4 py-3">{{ __('Rate') }}</th><th class="px-4 py-3">{{ __('Commission') }}</th><th class="px-4 py-3">{{ __('Earned') }}</th><th class="px-4 py-3">{{ __('Status') }}</th><th class="px-4 py-3">{{ __('Payout') }}</th><th class="px-4 py-3">{{ __('Action') }}</th></tr></thead>
                        <tbody class="divide-y divide-casa-border text-sm">@foreach($commissions as $commission)<tr class="casa-table-row"><td class="px-4 py-4 font-semibold">{{ $commission->staffProfile?->user?->name }}</td><td class="px-4 py-4 text-casa-muted">{{ $commission->appointment?->service?->name }}</td><td class="px-4 py-4 text-casa-muted">{{ $commission->appointment?->appointment_number }}</td><td class="px-4 py-4 text-casa-muted">{{ $commission->transaction?->transaction_number }}</td><td class="px-4 py-4 text-casa-muted">{{ ucfirst($commission->commission_type) }}</td><td class="px-4 py-4">PHP {{ number_format((float) $commission->basis_amount, 2) }}</td><td class="px-4 py-4">{{ number_format((float) $commission->commission_rate * 100, 0) }}%</td><td class="px-4 py-4 font-semibold">PHP {{ number_format((float) $commission->commission_amount, 2) }}</td><td class="px-4 py-4 text-casa-muted">{{ $commission->earned_at?->format('M d, Y') }}</td><td class="px-4 py-4"><x-status-badge>{{ ucfirst($commission->status) }}</x-status-badge></td><td class="px-4 py-4 text-casa-muted">{{ $commission->paid_at?->format('M d, Y') ?: '—' }}</td><td class="px-4 py-4"><a class="font-bold text-casa-primary" href="{{ route('admin.commissions.show', $commission) }}">{{ __('Open') }}</a></td></tr>@endforeach</tbody>
                    </x-table-shell>
                    <div class="mt-5">{{ $commissions->links() }}</div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
