<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Import service history') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Review rows') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ $upload['name'] }}</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.transactions.import.create') }}" class="casa-button-secondary">{{ __('Choose another file') }}</a>
        </div>
    </x-slot>

    @php
        $money = fn ($amount) => '₱'.number_format((float) $amount, 2);
        $percent = fn ($rate) => rtrim(rtrim(number_format($rate * 100, 2), '0'), '.').'%';
        $errorCount = count($result['errors']);
        $newRows = collect($result['rows'])->reject(fn ($row) => $row['already_imported']);
    @endphp

    <div class="space-y-6" data-import-preview>
        <x-stat-strip :items="[
            ['label' => __('New services'), 'value' => $newCount, 'meta' => __('Will be imported'), 'tone' => 'green'],
            ['label' => __('Already imported'), 'value' => $skipCount, 'meta' => __('Skipped by reference'), 'tone' => 'dark'],
            ['label' => __('Rows with errors'), 'value' => $errorCount, 'meta' => __('Fix these and upload again'), 'tone' => $errorCount ? 'brown' : 'dark'],
            ['label' => __('Revenue'), 'value' => $money($newRows->sum('amount')), 'meta' => __('Commission :amount', ['amount' => $money($newRows->sum('commission'))]), 'tone' => 'gold'],
        ]" />

        @if ($result['file_errors'] !== [] || $result['errors'] !== [])
            <x-app-card role="alert" data-import-errors>
                <h2 class="font-display text-xl font-black text-casa-text">{{ __('Nothing can be imported yet') }}</h2>
                <p class="mt-1 text-sm text-casa-muted">{{ __('Line numbers count the header as line 1.') }}</p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm leading-6 text-casa-text">
                    @foreach ($result['file_errors'] as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                    @foreach ($result['errors'] as $line => $messages)
                        <li><span class="font-bold">{{ __('Line :line:', ['line' => $line]) }}</span> {{ implode(' ', $messages) }}</li>
                    @endforeach
                </ul>
            </x-app-card>
        @endif

        @if ($summary->isNotEmpty())
            <x-app-card>
                <h2 class="font-display text-xl font-black text-casa-text">{{ __('Totals per therapist') }}</h2>
                <x-table-shell :label="__('Totals per therapist')" class="mt-4">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Therapist') }}</th>
                            <th scope="col">{{ __('Services') }}</th>
                            <th scope="col">{{ __('Amount') }}</th>
                            <th scope="col">{{ __('Commission') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary as $total)
                            <tr data-import-therapist-total>
                                <td>{{ $total['therapist'] }}</td>
                                <td>{{ $total['visits'] }}</td>
                                <td>{{ $money($total['amount']) }}</td>
                                <td>{{ $money($total['commission']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table-shell>
            </x-app-card>
        @endif

        @if ($canImport)
            <x-app-card>
                <form method="POST" action="{{ route('admin.transactions.import.store') }}" data-turbo="false" class="space-y-4" x-data="{ paid: {{ old('commissions_paid', '1') ? 'true' : 'false' }} }">
                    @csrf
                    <input type="hidden" name="token" value="{{ $upload['token'] }}">
                    <input type="hidden" name="commissions_paid" value="0">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="commissions_paid" value="1" x-model="paid" @checked(old('commissions_paid', '1')) class="mt-1 rounded border-casa-border text-casa-primary focus:ring-casa-gold">
                        <span>
                            <span class="font-bold text-casa-text">{{ __('Commissions were already paid out') }}</span>
                            <span class="block text-casa-muted">{{ __('Records the commissions as paid instead of pending.') }}</span>
                        </span>
                    </label>
                    <div x-show="paid">
                        <label for="commissions-paid-on" class="text-sm font-bold text-casa-text">{{ __('Payout date') }}</label>
                        <input id="commissions-paid-on" type="date" name="commissions_paid_on" value="{{ old('commissions_paid_on', $paidOn) }}" max="{{ today()->toDateString() }}" class="casa-input mt-2 max-w-xs">
                        <x-input-error :messages="$errors->get('commissions_paid_on')" />
                    </div>
                    <button type="submit" class="casa-button-primary">{{ trans_choice('{1} Import 1 service|[2,*] Import :count services', $newCount, ['count' => $newCount]) }}</button>
                </form>
            </x-app-card>
        @endif

        @if ($result['rows'] !== [])
            <x-app-card>
                <h2 class="font-display text-xl font-black text-casa-text">{{ __('Rows') }}</h2>
                <x-table-shell :label="__('Rows to import')" class="mt-4">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Line') }}</th>
                            <th scope="col">{{ __('Reference') }}</th>
                            <th scope="col">{{ __('Visit') }}</th>
                            <th scope="col">{{ __('Service') }}</th>
                            <th scope="col">{{ __('Therapist') }}</th>
                            <th scope="col">{{ __('Customer') }}</th>
                            <th scope="col">{{ __('Amount') }}</th>
                            <th scope="col">{{ __('Commission') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result['rows'] as $row)
                            <tr data-import-row>
                                <td>{{ $row['line'] }}</td>
                                <td>{{ $row['reference'] }}</td>
                                <td>{{ \Carbon\CarbonImmutable::parse($row['starts_at'])->format('M j, Y g:i A') }}</td>
                                <td>{{ $row['service_name'] }}</td>
                                <td>{{ $row['therapist_name'] }}</td>
                                <td>{{ $row['customer'] !== '' ? $row['customer'] : __('Walk-in') }}</td>
                                <td>{{ $money($row['amount']) }}</td>
                                <td>{{ $money($row['commission']) }} <span class="text-casa-muted">({{ $percent($row['commission_rate']) }})</span></td>
                                <td>{{ $row['already_imported'] ? __('Already imported') : __('New') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table-shell>
            </x-app-card>
        @endif
    </div>
</x-app-layout>
