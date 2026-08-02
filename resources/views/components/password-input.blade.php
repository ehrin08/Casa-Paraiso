@props(['disabled' => false])

<div class="relative" x-data="{ passwordVisible: false }">
    <input
        type="password"
        x-bind:type="passwordVisible ? 'text' : 'password'"
        @disabled($disabled)
        {{ $attributes->except('type')->class(['casa-input pr-12']) }}
    >

    <button
        type="button"
        class="absolute inset-y-0 right-0 flex min-h-11 min-w-11 items-center justify-center rounded-r-xl text-casa-muted transition hover:text-casa-primary focus:z-10 focus:outline-none focus:ring-4 focus:ring-casa-gold/35"
        x-on:click="passwordVisible = ! passwordVisible"
        x-bind:aria-label="passwordVisible ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
        x-bind:title="passwordVisible ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
        x-bind:aria-pressed="passwordVisible.toString()"
        @if ($attributes->has('id')) aria-controls="{{ $attributes->get('id') }}" @endif
    >
        <svg viewBox="0 0 24 24" class="size-5" fill="none" aria-hidden="true">
            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="12" cy="12" r="2.75" stroke="currentColor" stroke-width="1.8"/>
            <path x-show="passwordVisible" style="display: none" d="m4 4 16 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
    </button>
</div>
