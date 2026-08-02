<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-eyebrow">{{ __('Therapist workspace') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Daily dashboard') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('See today’s assigned care, requests waiting for action, and recently completed services.') }}</p>
        </div>

        <div class="flex w-full flex-nowrap gap-2 sm:w-auto sm:gap-3">
            <a href="{{ route('staff.customers.index') }}" class="casa-button-secondary min-w-0 flex-1 px-2 sm:flex-none sm:px-[0.9rem]">{{ __('Customer lookup') }}</a>
            <a href="{{ route('staff.appointments.index') }}" class="casa-button-primary min-w-0 flex-1 px-2 sm:flex-none sm:px-[0.9rem]">{{ __('Open schedule') }}</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="casa-metric-grid grid gap-3 sm:grid-cols-3 sm:gap-4" data-metric-grid>
            <x-metric-card label="Assigned today" :value="$summary['assignedToday'] ?? 0" meta="Confirmed appointments" tone="green" />
            <x-metric-card label="Upcoming" :value="$summary['upcoming'] ?? 0" meta="Confirmed visits on your schedule" tone="gold" />
            <x-metric-card label="Completed" :value="$summary['completedToday'] ?? 0" meta="Services finished today" tone="brown" />
        </section>

        <x-app-card>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="casa-section-label">{{ __('My earnings') }}</p>
                    <h2 class="mt-2 text-xl font-extrabold text-casa-text">{{ __('Commission overview') }}</h2>
                    <p class="mt-2 text-sm text-casa-muted">{{ __('Your personal earnings from fully paid completed services.') }}</p>
                </div>
                <a href="{{ route('staff.commissions.index') }}" class="casa-button-secondary">{{ __('View commission history') }}</a>
            </div>

            <x-stat-strip
                class="mt-5"
                :items="[
                    ['label' => __('Pending commission'), 'value' => 'PHP '.number_format((float) $commissionTotals['pending'], 2), 'meta' => __('Awaiting settlement'), 'tone' => 'gold'],
                    ['label' => __('Paid commission'), 'value' => 'PHP '.number_format((float) $commissionTotals['paid'], 2), 'meta' => __('Externally recorded payouts'), 'tone' => 'green'],
                    ['label' => __('Net commission'), 'value' => 'PHP '.number_format((float) $commissionTotals['net'], 2), 'meta' => __('Earnings and adjustments'), 'tone' => 'brown'],
                ]"
            />
        </x-app-card>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.65fr)]">
            <x-app-card>
                <div class="flex flex-col gap-3 border-b border-casa-border pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="casa-section-label">{{ __('Service flow') }}</p>
                        <h2 class="mt-2 text-xl font-extrabold text-casa-text">{{ __('Today’s appointments') }}</h2>
                    </div>
                    <x-status-badge tone="success">{{ trans_choice(':count assigned|:count assigned', $summary['assignedToday'] ?? 0) }}</x-status-badge>
                </div>

                <div class="mt-5">
                    @if ($todayAppointments->isEmpty())
                        <x-empty-state title="{{ __('No appointments assigned today') }}" description="{{ __('Confirmed visits for today will appear here when appointments are scheduled.') }}" />
                    @else
                        <x-table-shell aria-label="{{ __('Today appointments') }}">
                            <thead class="text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                                <tr>
                                    <th class="px-4 py-3">{{ __('No.') }}</th>
                                    <th class="px-4 py-3">{{ __('Time') }}</th>
                                    <th class="px-4 py-3">{{ __('Customer') }}</th>
                                    <th class="px-4 py-3">{{ __('Service') }}</th>
                                    <th class="px-4 py-3">{{ __('Status') }}</th>
                                    <th class="px-4 py-3">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-casa-border text-sm">
                                @foreach ($todayAppointments as $appointment)
                                    <tr class="casa-table-row">
                                        <td class="px-4 py-4 font-semibold text-casa-text">{{ $appointment->appointment_number }}</td>
                                        <td class="px-4 py-4 font-semibold text-casa-text">{{ $appointment->scheduled_start_at?->format('g:i A') }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $appointment->customerProfile?->user?->name ?? __('Customer') }}</td>
                                        <td class="px-4 py-4 text-casa-muted">{{ $appointment->service?->name ?? __('Service') }}</td>
                                        <td class="px-4 py-4"><x-status-badge tone="success">{{ __(ucfirst($appointment->status)) }}</x-status-badge></td>
                                        <td class="px-4 py-4"><a href="{{ route('staff.appointments.show', $appointment) }}" class="font-bold text-casa-cacao hover:text-casa-cacao-dark" data-panel-link data-turbo="false">{{ __('Open') }}</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table-shell>
                    @endif
                </div>
            </x-app-card>

            <aside class="casa-dark-panel rounded-[24px] p-6 shadow-casa-card">
                <p class="text-[0.68rem] font-extrabold uppercase tracking-[0.16em] text-casa-brass-light">{{ __('Service rhythm') }}</p>
                <h2 class="mt-4 text-2xl font-extrabold text-white">{{ __('Confirm. Care. Record.') }}</h2>
                <p class="mt-4 text-sm leading-7 text-white/65">{{ __('Keep each visit moving through the same clear operational rhythm.') }}</p>
                <ol class="mt-6 space-y-3">
                    <li class="flex gap-3 rounded-xl border border-white/10 bg-white/[0.06] p-4"><span class="font-extrabold text-casa-brass-light">01</span><span class="text-sm text-white/78">{{ __('Review the requested time and treatment.') }}</span></li>
                    <li class="flex gap-3 rounded-xl border border-white/10 bg-white/[0.06] p-4"><span class="font-extrabold text-casa-brass-light">02</span><span class="text-sm text-white/78">{{ __('Open the customer context before service.') }}</span></li>
                    <li class="flex gap-3 rounded-xl border border-white/10 bg-white/[0.06] p-4"><span class="font-extrabold text-casa-brass-light">03</span><span class="text-sm text-white/78">{{ __('Complete the visit and record payment.') }}</span></li>
                </ol>
                <a href="{{ route('staff.transactions.index') }}" class="casa-button-secondary mt-6 w-full border-white/15 bg-white/10 text-white hover:bg-white/15 hover:text-white">{{ __('Open payments') }}</a>
            </aside>
        </section>
    </div>
</x-app-layout>
