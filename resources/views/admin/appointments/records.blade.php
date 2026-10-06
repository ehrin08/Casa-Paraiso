<x-app-layout>
    <x-slot name="header">
        <div><h1 class="font-display text-3xl font-black text-casa-text">{{ __('All appointments') }}</h1><p class="mt-2 text-sm text-casa-muted">{{ __('Search visits and review appointment history. Dates and times are in Asia/Manila.') }}</p></div>
        <a href="{{ route('admin.appointments.index') }}" class="casa-button-secondary">{{ __('Back to appointments') }}</a>
    </x-slot>
    <x-app-card>
        <x-list-toolbar title="{{ __('Appointment records') }}" :count="$appointments->total()" :reset-url="route('admin.appointments.records')" :active-filters="collect($filters)->filter(fn ($value) => filled($value))->count()" :collapsible="true">
            <form method="GET" action="{{ route('admin.appointments.records') }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <label class="block"><span class="casa-label">{{ __('Search') }}</span><input name="q" type="search" maxlength="150" value="{{ $filters['q'] ?? '' }}" class="casa-input mt-1" placeholder="{{ __('Appointment number or customer') }}"></label>
                <label class="block"><span class="casa-label">{{ __('Status') }}</span><select name="status" class="casa-input mt-1"><option value="">{{ __('All statuses') }}</option>@foreach (\App\Models\Appointment::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></label>
                <label class="block"><span class="casa-label">{{ __('Therapist') }}</span><select name="staff_profile_id" class="casa-input mt-1"><option value="">{{ __('All therapists') }}</option>@foreach ($staffProfiles as $staff)<option value="{{ $staff->id }}" @selected((string) ($filters['staff_profile_id'] ?? '') === (string) $staff->id)>{{ $staff->user?->name ?: __('Therapist unavailable') }}</option>@endforeach</select></label>
                <label class="block"><span class="casa-label">{{ __('From date') }}</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="casa-input mt-1"></label>
                <label class="block"><span class="casa-label">{{ __('Through date') }}</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="casa-input mt-1"></label>
                <button type="submit" class="casa-button-primary self-end">{{ __('Apply filters') }}</button>
            </form>
        </x-list-toolbar>
        @if ($errors->any())<div role="alert" class="mt-3 text-sm text-red-700">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <div class="mt-4">
            @if ($appointments->isEmpty())
                <x-empty-state title="{{ __('No matching appointments') }}" description="{{ __('Try another customer, date range, or status.') }}" />
            @else
                <x-table-shell label="{{ __('All appointment records') }}">
                    <thead class="bg-casa-bg text-left text-sm text-casa-muted"><tr>@foreach (['Appointment', 'Scheduled time', 'Customer', 'Service', 'Therapist', 'Status', 'Action'] as $heading)<th scope="col" class="px-3 py-3">{{ __($heading) }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-casa-border text-sm">
                        @foreach ($appointments as $appointment)
                            <tr class="casa-table-row">
                                <td class="px-3 py-3 font-semibold">{{ $appointment->appointment_number }}</td>
                                <td class="px-3 py-3 whitespace-nowrap">{{ $appointment->scheduled_start_at?->copy()->timezone('Asia/Manila')->format('M j, Y g:i A') ?: __('Not scheduled') }}</td>
                                <td class="px-3 py-3">{{ $appointment->customerProfile?->display_name ?: __('Customer unavailable') }}</td>
                                <td class="px-3 py-3">{{ $appointment->service?->name ?: __('Service unavailable') }}</td>
                                <td class="px-3 py-3">{{ $appointment->staffProfile?->user?->name ?: __('Unassigned') }}</td>
                                <td class="px-3 py-3"><x-status-badge>{{ ucfirst(str_replace('_', ' ', $appointment->status)) }}</x-status-badge></td>
                                <td class="px-3 py-3"><a href="{{ route('admin.appointments.show', $appointment) }}" class="casa-button-secondary">{{ __('View') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table-shell>
                <div class="mt-3">{{ $appointments->links() }}</div>
            @endif
        </div>
    </x-app-card>
</x-app-layout>
