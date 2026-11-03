<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Payments') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Import service history') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ __('Load past services from a CSV file. Each row becomes a completed visit, a paid payment and the therapist\'s commission.') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.transactions.index') }}" class="casa-button-secondary">{{ __('All payments') }}</a>
            <a href="{{ route('admin.transactions.import.template') }}" class="casa-button-secondary" data-turbo="false">{{ __('Download template') }}</a>
        </div>
    </x-slot>

    @php
        $rate = rtrim(rtrim(number_format(config('casa.commissions.therapist_rate', 0.22) * 100, 2), '0'), '.');
        $help = [
            'reference' => __('A unique ID per row. Rows already imported with the same reference are skipped.'),
            'date' => __('Visit date as YYYY-MM-DD.'),
            'time' => __('Optional start time as HH:MM. Rows without a time follow each other from opening time, per therapist and day.'),
            'service' => __('Service name as listed on the Services page.'),
            'amount' => __('Amount paid in pesos.'),
            'therapist' => __('Full name, first name, or the name before the dot in the therapist\'s email.'),
            'commission' => __('Optional. Blank uses the standard :rate% rate.', ['rate' => $rate]),
            'customer' => __('Optional. Blank records the visit under the shared walk-in profile, which RFM rewards ignore.'),
            'payment_method' => __('Optional: :methods. Blank means Cash.', ['methods' => implode(', ', \App\Models\Transaction::PAYMENT_METHODS)]),
            'notes' => __('Optional, up to 500 characters.'),
        ];
    @endphp

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <x-app-card>
            <form method="POST" action="{{ route('admin.transactions.import.upload') }}" enctype="multipart/form-data" data-turbo="false" class="space-y-5">
                @csrf
                <div>
                    <label for="import-file" class="text-sm font-bold text-casa-text">{{ __('CSV file') }}</label>
                    <input id="import-file" name="file" type="file" accept=".csv,text/csv" required class="casa-input mt-2" aria-describedby="import-file-help">
                    <p id="import-file-help" class="mt-2 text-sm text-casa-muted">
                        {{ __('UTF-8 CSV, up to :size MB and :rows rows. Nothing is saved until you review the rows and confirm.', [
                            'size' => round(config('casa.imports.max_kilobytes', 2048) / 1024, 1),
                            'rows' => number_format(config('casa.imports.max_rows', 2000)),
                        ]) }}
                    </p>
                    <x-input-error :messages="$errors->get('file')" />
                </div>

                <button type="submit" class="casa-button-primary">{{ __('Check file') }}</button>
            </form>
        </x-app-card>

        <x-app-card>
            <h2 class="font-display text-xl font-black text-casa-text">{{ __('Columns') }}</h2>
            <dl class="mt-4 space-y-3 text-sm leading-6">
                @foreach ($columns as $column)
                    <div>
                        <dt class="font-bold text-casa-text">
                            <code>{{ $column }}</code>
                            @if (in_array($column, $required, true))
                                <span class="text-xs font-semibold text-casa-muted">{{ __('required') }}</span>
                            @endif
                        </dt>
                        <dd class="text-casa-muted">{{ $help[$column] }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-app-card>
    </div>
</x-app-layout>
