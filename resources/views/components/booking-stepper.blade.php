<nav class="booking-stepper" aria-label="{{ __('Booking steps') }}">
    @foreach ([1 => __('Treatment details'), 2 => __('Date and time'), 3 => __('Review and save')] as $number => $label)
        <button type="button" x-on:click="goToStep({{ $number }})" x-bind:disabled="{{ $number }} > furthestStep" x-bind:aria-current="step === {{ $number }} ? 'step' : null" x-bind:class="{ 'is-current': step === {{ $number }}, 'is-complete': step > {{ $number }} }">
            <span class="booking-step-number">{{ $number }}</span><span>{{ $label }}</span>
        </button>
    @endforeach
</nav>
