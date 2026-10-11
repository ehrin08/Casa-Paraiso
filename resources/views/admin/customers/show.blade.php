<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">{{ __('Customer detail') }}</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $customer->display_name }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">
                {{ $customer->customer_code }} · {{ $customer->display_email }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.appointments.create', ['customer_profile_id' => $customer->id]) }}" class="casa-button-primary">{{ __('Add appointment') }}</a>
            <a href="{{ route('admin.customers.index') }}" class="casa-button-secondary">{{ __('All customers') }}</a>
        </div>
    </x-slot>

    @include('admin.customers.partials.details')
</x-app-layout>
