@props([
    'eyebrow' => null,
    'title' => null,
    'count' => null,
    'resetUrl' => null,
    'activeFilters' => 0,
    'collapsible' => false,
])

@php
    $filterPanelId = 'list-filters-'.Illuminate\Support\Str::uuid();
    $activeFilters = (int) $activeFilters;
@endphp

<div
    @if ($collapsible) x-data="{ filtersOpen: @js($activeFilters > 0) }" @endif
    data-active-filters="{{ $activeFilters }}"
    {{ $attributes->merge(['class' => 'casa-list-toolbar']) }}
>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="casa-section-label">{{ $eyebrow }}</p>
        @endif

        @if ($title)
            <h2 class="mt-2 text-xl font-extrabold text-casa-text">{{ $title }}</h2>
        @endif

        @if ($count !== null || ($resetUrl && $activeFilters > 0) || isset($meta))
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @if ($count !== null)
                    <span class="casa-filter-chip">{{ trans_choice(':count record|:count records', (int) $count) }}</span>
                @endif

                @if ($resetUrl && $activeFilters > 0)
                    <a href="{{ $resetUrl }}" class="casa-filter-chip hover:border-casa-gold hover:text-casa-primary">
                        {{ __('Clear filters') }}
                    </a>
                @endif

                @isset($meta)
                    {{ $meta }}
                @endisset
            </div>
        @endif
    </div>

    <div class="w-full lg:w-auto">
        @if ($collapsible)
            <button
                type="button"
                class="casa-button-secondary w-full justify-between lg:hidden"
                aria-controls="{{ $filterPanelId }}"
                x-bind:aria-expanded="filtersOpen"
                x-on:click="filtersOpen = ! filtersOpen"
                data-filter-toggle
            >
                <span>{{ __('Filters') }}</span>
                @if ($activeFilters > 0)
                    <span class="grid min-h-6 min-w-6 place-items-center rounded-full bg-casa-palm px-1.5 text-sm text-white">{{ $activeFilters }}</span>
                @endif
            </button>

            <div
                id="{{ $filterPanelId }}"
                class="mt-3 hidden lg:mt-0 lg:block"
                x-bind:class="filtersOpen ? '!block' : ''"
                data-filter-panel
            >
                {{ $slot }}
            </div>
        @else
            {{ $slot }}
        @endif
    </div>
</div>
