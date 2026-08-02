@props([
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-[20px] border border-dashed border-casa-border bg-casa-paper p-8 text-center']) }}>
    <div class="mx-auto grid size-12 place-items-center rounded-full bg-casa-green/12 text-casa-green">
        <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M5 13.5C5 8.81 8.81 5 13.5 5H19v5.5C19 15.19 15.19 19 10.5 19H5v-5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M6.5 17.5 15 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
    </div>
    <h3 class="mt-4 text-lg font-extrabold text-casa-text">{{ $title }}</h3>
    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-casa-muted">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-5">
            {{ $action }}
        </div>
    @endisset
</div>
