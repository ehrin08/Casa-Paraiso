<x-modal :name="$modalName" :show="true" maxWidth="4xl" :labelled-by="$modalName.'-title'" :transient="true" focusable>
    <div class="p-5 sm:p-6" data-feedback-prompt>
        <div class="mb-5">
            <p class="casa-eyebrow">{{ __('Your visit') }}</p>
            <h2 id="{{ $modalName }}-title" class="mt-2 font-editorial text-3xl font-semibold text-casa-text">{{ __('How was your visit?') }}</h2>
            <p class="mt-2 text-sm leading-6 text-casa-muted">{{ __('A short review helps the team care for you better. You can also share it later from My feedback.') }}</p>
        </div>
        @include('customer.feedback.partials.form', [
            'modalName' => $modalName,
            'appointments' => $appointments,
            'selectedAppointmentId' => $selectedAppointmentId,
            'laterFormId' => $modalName.'-later',
        ])
        <form id="{{ $modalName }}-later" method="POST" action="{{ route('customer.feedback.prompt.dismiss') }}" class="hidden">
            @csrf
        </form>
    </div>
</x-modal>
