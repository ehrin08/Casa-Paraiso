<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Commission detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $commission->staffProfile?->user?->name }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ ucfirst($commission->commission_type) }} &middot; {{ ucfirst($commission->status) }}</p>
        </div>
        <a href="{{ route('admin.commissions.index') }}" class="casa-button-secondary">{{ __('All commissions') }}</a>
    </x-slot>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <x-app-card>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="casa-section-label">{{ __('Commission') }}</dt>
                    <dd class="mt-2 text-2xl font-black">PHP {{ number_format((float) $commission->commission_amount, 2) }}</dd>
                </div>
                <div>
                    <dt class="casa-section-label">{{ __('Basis and rate') }}</dt>
                    <dd class="mt-2 font-semibold">PHP {{ number_format((float) $commission->basis_amount, 2) }} &middot; {{ number_format((float) $commission->commission_rate * 100, 0) }}%</dd>
                </div>
                <div>
                    <dt class="casa-section-label">{{ __('Appointment') }}</dt>
                    <dd class="mt-2"><a class="font-bold text-casa-primary" href="{{ route('admin.appointments.show', $commission->appointment) }}">{{ $commission->appointment?->appointment_number }}</a></dd>
                </div>
                <div>
                    <dt class="casa-section-label">{{ __('Transaction') }}</dt>
                    <dd class="mt-2"><a class="font-bold text-casa-primary" href="{{ route('admin.transactions.show', $commission->transaction) }}">{{ $commission->transaction?->transaction_number }}</a></dd>
                </div>
                <div>
                    <dt class="casa-section-label">{{ __('Earned') }}</dt>
                    <dd class="mt-2">{{ $commission->earned_at?->format('M d, Y g:i A') }}</dd>
                </div>
                <div>
                    <dt class="casa-section-label">{{ __('Payout') }}</dt>
                    <dd class="mt-2">{{ $commission->paid_at?->format('M d, Y') ?: __('Pending') }}</dd>
                </div>
                <div>
                    <dt class="casa-section-label">{{ __('Recorded by') }}</dt>
                    <dd class="mt-2">{{ $commission->paidBy?->name ?: '—' }}</dd>
                </div>
            </dl>
            @if($commission->notes)
                <p class="mt-5 whitespace-pre-line text-sm text-casa-muted">{{ $commission->notes }}</p>
            @endif
        </x-app-card>

        <x-app-card>
            @if($commission->status === \App\Models\TherapistCommission::STATUS_PENDING && (float) $commission->commission_amount !== 0.0)
                <p class="casa-section-label">{{ __('Record payout') }}</p>
                <form method="POST" action="{{ route('admin.commissions.pay', $commission) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <x-input-label for="paid_at" value="Payout date"/>
                        <input id="paid_at" name="paid_at" type="date" value="{{ old('paid_at', today()->toDateString()) }}" class="casa-input mt-2" required>
                        <x-input-error class="mt-2" :messages="$errors->get('paid_at')"/>
                    </div>
                    <div>
                        <x-input-label for="notes" value="Payout note (optional)"/>
                        <textarea id="notes" name="notes" class="casa-input mt-2" rows="4">{{ old('notes') }}</textarea>
                    </div>
                    <button class="casa-button-primary w-full">{{ __('Mark paid') }}</button>
                </form>
            @else
                <p class="casa-section-label">{{ __('Payout status') }}</p>
                <p class="mt-3 text-sm text-casa-muted">{{ $commission->status === 'paid' ? __('This payout record is immutable.') : __('Zero-value records do not require payout.') }}</p>
            @endif
        </x-app-card>
    </div>
</x-app-layout>
