<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-eyebrow">{{ __('Attendance station') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Automatic therapist attendance') }}</h1>
            <p class="mt-2 text-sm text-casa-muted">{{ __('Display the live QR. A valid therapist scan records time in or time out immediately.') }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl" x-data="attendanceStation({ initialQr: @js($qr), qrUrl: '{{ route('attendance-station.qr') }}' })" x-init="init()">
        <x-app-card>
            <div class="grid gap-6 md:grid-cols-[auto_1fr]">
                <div class="rounded-2xl bg-white p-4 shadow-casa-card"><canvas id="attendance-qr" width="260" height="260"></canvas></div>
                <div>
                    <p class="casa-section-label">{{ __('Live venue code') }}</p>
                    <h2 class="mt-2 font-display text-2xl font-black text-casa-text">{{ __('Refreshes every minute') }}</h2>
                    <p class="mt-3 text-sm text-casa-muted">{{ __('Time remaining:') }} <strong x-text="countdown"></strong></p>
                    <p x-show="error" x-text="error" class="mt-4 text-sm font-semibold text-red-700"></p>
                </div>
            </div>
        </x-app-card>
    </div>
</x-app-layout>
