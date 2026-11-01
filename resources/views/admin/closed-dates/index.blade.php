<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Settings') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ __('Closed dates') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">{{ __('Days the spa is closed, such as holidays. No one can book on a closed date: not customers, reception or admins.') }}</p>
        </div>
        <a href="{{ route('admin.settings.index') }}" class="casa-button-secondary">{{ __('Back to settings') }}</a>
    </x-slot>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]" data-closed-dates>
        <x-app-card>
            <h2 class="text-lg font-black text-casa-text">{{ __('Close a date') }}</h2>
            <p class="mt-1 text-sm leading-6 text-casa-muted">{{ __('A date with confirmed appointments cannot be closed. Move or cancel those appointments first.') }}</p>
            <form method="POST" action="{{ route('admin.closed-dates.store') }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <x-input-label for="closed_on" :value="__('Date')" />
                    <x-text-input id="closed_on" name="closed_on" type="date" class="mt-2 block w-full" value="{{ old('closed_on') }}" min="{{ now(config('casa.business_hours.timezone'))->toDateString() }}" required />
                    <x-input-error class="mt-2" :messages="$errors->get('closed_on')" />
                </div>
                @if ($conflicts->isNotEmpty())
                    <ul class="space-y-2 text-sm" data-closed-date-conflicts>
                        @foreach ($conflicts as $conflict)
                            <li class="rounded-casa-control bg-casa-bg px-3 py-2">
                                <a href="{{ route('admin.appointments.show', $conflict['id']) }}" class="font-bold text-casa-primary">{{ $conflict['time'] }} · {{ $conflict['customer'] }}</a>
                                <span class="block text-casa-muted">{{ __('With :therapist', ['therapist' => $conflict['therapist']]) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div>
                    <x-input-label for="reason" :value="__('Reason')" />
                    <x-text-input id="reason" name="reason" class="mt-2 block w-full" value="{{ old('reason') }}" maxlength="255" placeholder="{{ __('For example: All Saints Day') }}" required />
                    <p class="mt-1 text-xs text-casa-muted">{{ __('The booking calendar marks the date as Closed and shows this reason on hover.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('reason')" />
                </div>
                <button type="submit" class="casa-button-primary">{{ __('Close this date') }}</button>
            </form>
        </x-app-card>

        <x-app-card>
            <h2 class="text-lg font-black text-casa-text">{{ __('Upcoming closed dates') }}</h2>
            <div class="mt-4">
                @if ($closedDates->isEmpty())
                    <x-empty-state title="{{ __('No closed dates') }}" description="{{ __('The spa is open every day until you close a date.') }}" />
                @else
                    <x-table-shell label="{{ __('Scrollable closed dates') }}">
                        <thead class="bg-casa-bg text-left text-xs font-black uppercase tracking-[0.1em] text-casa-muted">
                            <tr>
                                <th class="px-4 py-3" scope="col">{{ __('Date') }}</th>
                                <th class="px-4 py-3" scope="col">{{ __('Reason') }}</th>
                                <th class="px-4 py-3" scope="col">{{ __('Added by') }}</th>
                                <th class="px-4 py-3" scope="col">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-casa-border text-sm">
                            @foreach ($closedDates as $closedDate)
                                <tr class="casa-table-row" data-closed-date="{{ $closedDate->closed_on->toDateString() }}">
                                    <td class="px-4 py-4 font-bold text-casa-text">{{ $closedDate->closed_on->format('D, M j, Y') }}</td>
                                    <td class="px-4 py-4">{{ $closedDate->reason }}</td>
                                    <td class="px-4 py-4 text-casa-muted">{{ $closedDate->creator?->name ?? '—' }}</td>
                                    <td class="px-4 py-4">
                                        <x-confirm-action :action="route('admin.closed-dates.destroy', $closedDate)" method="DELETE" :label="__('Reopen')" :confirm-title="__('Reopen :date?', ['date' => $closedDate->closed_on->format('M j')])" :confirm-message="__('Customers and staff will be able to book on this date again.')" :confirm-button="__('Reopen')" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                    <div class="mt-5">{{ $closedDates->links() }}</div>
                @endif
            </div>
        </x-app-card>
    </div>
</x-app-layout>
