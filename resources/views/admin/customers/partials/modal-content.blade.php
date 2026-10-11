<div data-customer-detail class="space-y-6">
    <header class="flex flex-col gap-4 border-b border-casa-border pb-5 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="casa-section-label">{{ __('Customer detail') }}</p>
            <h1 class="mt-2 break-words font-display text-3xl font-black text-casa-text">{{ $customer->display_name }}</h1>
            <p class="mt-2 break-words text-sm leading-6 text-casa-muted">{{ $customer->customer_code }} · {{ $customer->display_email }}</p>
        </div>
        <a href="{{ route('admin.appointments.create', ['customer_profile_id' => $customer->id]) }}" class="casa-button-primary shrink-0" data-no-turbo>{{ __('Add appointment') }}</a>
    </header>
    @include('admin.customers.partials.details', ['isModal' => true])
</div>
