@props(['commission', 'returnFilters' => null])

@php
    $money = fn ($amount) => '₱'.number_format((float) $amount, (float) $amount === floor((float) $amount) ? 0 : 2);
    $earned = $commission->earned_at?->timezone('Asia/Manila');
    $visit = $commission->appointment?->scheduled_start_at?->timezone('Asia/Manila');
    $hasPayoutErrors = $errors->hasAny(['paid_at', 'notes', 'status', 'return_to', 'return_filters', 'return_filters.*']);
@endphp

<aside id="commission-detail" class="commissions-detail" aria-labelledby="payout-details-heading" data-selected-commission="{{ $commission->id }}">
    <div class="commissions-detail-heading">
        <h2 id="payout-details-heading">{{ __('Payout details') }}</h2>
        <span class="commissions-status commissions-status--{{ $commission->status }}">{{ ucfirst($commission->status) }}</span>
    </div>
    <div class="commissions-identity">
        <h3>{{ $commission->staffProfile?->user?->name ?: __('Therapist unavailable') }}</h3>
        @if (filled($commission->staffProfile?->user?->email))
            <p>{{ $commission->staffProfile->user->email }}</p>
        @endif
    </div>
    <dl class="commissions-detail-summary">
        <div><dt>{{ __('Earned date') }}</dt><dd>{{ $earned?->format('M j, Y g:i A') ?: '—' }}</dd></div>
        <div><dt>{{ __('Record type') }}</dt><dd>{{ ucfirst($commission->commission_type) }}</dd></div>
    </dl>

    <section class="commissions-breakdown" aria-labelledby="commission-breakdown-heading">
        <h3 id="commission-breakdown-heading">{{ __('Service breakdown') }}</h3>
        <x-table-shell class="commissions-breakdown-table" label="{{ __('Scrollable commission service breakdown') }}">
            <thead><tr><th scope="col">{{ __('Service') }}</th><th scope="col">{{ __('Basis') }}</th><th scope="col">{{ __('Rate') }}</th><th scope="col">{{ __('Commission') }}</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $commission->appointment?->service?->name ?: __('Service unavailable') }}@if ($visit)<time datetime="{{ $visit->toIso8601String() }}">{{ $visit->format('M j, Y g:i A') }}</time>@endif</td>
                    <td>{{ $money($commission->basis_amount) }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $commission->commission_rate * 100, 2), '0'), '.') }}%</td>
                    <td>{{ $money($commission->commission_amount) }}</td>
                </tr>
            </tbody>
            <tfoot><tr><th scope="row" colspan="3">{{ __('Total commission') }}</th><td>{{ $money($commission->commission_amount) }}</td></tr></tfoot>
        </x-table-shell>
    </section>

    <dl class="commissions-source-details">
        <div>
            <dt>{{ __('Appointment') }}</dt>
            <dd>@if ($commission->appointment)<a href="{{ route('admin.appointments.show', $commission->appointment) }}">{{ $commission->appointment->appointment_number }}</a>@else — @endif</dd>
        </div>
        <div>
            <dt>{{ __('Transaction') }}</dt>
            <dd>@if ($commission->transaction)<a href="{{ route('admin.transactions.show', $commission->transaction) }}">{{ $commission->transaction->transaction_number }}</a>@else — @endif</dd>
        </div>
        <div><dt>{{ __('Payout date') }}</dt><dd>{{ $commission->paid_at?->timezone('Asia/Manila')->format('M j, Y') ?: __('Pending') }}</dd></div>
        <div><dt>{{ __('Recorded by') }}</dt><dd>{{ $commission->paidBy?->name ?: '—' }}</dd></div>
        @if ($commission->adjustedCommission)
            <div><dt>{{ __('Adjusts commission') }}</dt><dd><a href="{{ route('admin.commissions.show', $commission->adjustedCommission) }}">#{{ $commission->adjustedCommission->id }}</a></dd></div>
        @endif
        @if ($commission->adjustments->isNotEmpty())
            <div><dt>{{ __('Related adjustments') }}</dt><dd class="commissions-related-adjustments">@foreach ($commission->adjustments as $adjustment)<a href="{{ route('admin.commissions.show', $adjustment) }}">#{{ $adjustment->id }} · {{ $money($adjustment->commission_amount) }} · {{ ucfirst($adjustment->status) }}</a>@endforeach</dd></div>
        @endif
    </dl>
    @if (filled($commission->notes))
        <div class="commissions-notes"><h3>{{ __('Payout note') }}</h3><p>{{ $commission->notes }}</p></div>
    @endif

    <x-input-error :messages="$errors->get('status')" />
    @if ($commission->status === \App\Models\TherapistCommission::STATUS_PENDING && (float) $commission->commission_amount !== 0.0)
        <details class="commissions-payout" @if ($hasPayoutErrors) open @endif>
            <summary class="casa-button-primary"><x-payment-icon name="wallet" />{{ __('Mark as paid') }}</summary>
            <form method="POST" action="{{ route('admin.commissions.pay', $commission) }}" class="commissions-payout-form">
                @csrf
                @method('PATCH')
                @if ($returnFilters !== null)
                    <input type="hidden" name="return_to" value="index">
                    @foreach ($returnFilters as $key => $value)
                        <input type="hidden" name="return_filters[{{ $key }}]" value="{{ $value }}">
                    @endforeach
                @endif
                <p>{{ __('Record an externally settled payout. This action does not transfer funds.') }}</p>
                <div class="commissions-field">
                    <label for="commission-paid-at">{{ __('Payout date') }}</label>
                    <input id="commission-paid-at" name="paid_at" type="date" value="{{ old('paid_at', today('Asia/Manila')->toDateString()) }}" max="{{ today('Asia/Manila')->toDateString() }}" class="casa-input" required>
                    <x-input-error :messages="$errors->get('paid_at')" />
                </div>
                <div class="commissions-field">
                    <label for="commission-payout-notes">{{ __('Payout note (optional)') }}</label>
                    <textarea id="commission-payout-notes" name="notes" class="casa-input" rows="3" maxlength="5000">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" />
                </div>
                <x-input-error :messages="$errors->get('return_to')" />
                <x-input-error :messages="$errors->get('return_filters')" />
                <button type="submit" class="casa-button-primary">{{ __('Confirm payout') }}</button>
            </form>
        </details>
    @else
        <p class="commissions-payout-state">{{ $commission->status === 'paid' ? __('This payout record is immutable.') : __('Zero-value records do not require payout.') }}</p>
    @endif
</aside>
