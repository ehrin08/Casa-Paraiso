@if ($paginator->total() > 0)
    <nav
        role="navigation"
        aria-label="{{ __('Pagination Navigation') }}"
        class="casa-pagination"
        data-pagination
    >
        <p
            class="text-sm font-semibold text-casa-muted"
            data-pagination-range
            data-first="{{ $paginator->firstItem() }}"
            data-last="{{ $paginator->lastItem() }}"
            data-total="{{ $paginator->total() }}"
        >
            {{ __('Showing') }}
            <span class="font-extrabold text-casa-text">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
            {{ __('of') }}
            <span class="font-extrabold text-casa-text">{{ $paginator->total() }}</span>
        </p>

        <div class="casa-pagination-mobile flex w-full items-center justify-between gap-2 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="casa-pagination-control opacity-45" aria-disabled="true" aria-label="{{ __('Previous page') }}">{{ __('Previous') }}</span>
            @else
                <a class="casa-pagination-control" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Previous page') }}">{{ __('Previous') }}</a>
            @endif

            <span class="casa-pagination-status text-sm font-bold text-casa-muted">
                {{ __('Page :current of :last', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}
            </span>

            @if ($paginator->hasMorePages())
                <a class="casa-pagination-control" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next page') }}">{{ __('Next') }}</a>
            @else
                <span class="casa-pagination-control opacity-45" aria-disabled="true" aria-label="{{ __('Next page') }}">{{ __('Next') }}</span>
            @endif
        </div>

        <div class="casa-pagination-pages hidden items-center gap-1 sm:flex">
            @if ($paginator->onFirstPage())
                <span class="casa-pagination-control opacity-45" aria-disabled="true" aria-label="{{ __('Previous page') }}">‹</span>
            @else
                <a class="casa-pagination-control" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Previous page') }}">‹</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="casa-pagination-control border-transparent bg-transparent text-casa-muted" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="casa-pagination-control border-casa-palm bg-casa-palm text-white" aria-current="page" aria-label="{{ __('Page :page', ['page' => $page]) }}">{{ $page }}</span>
                        @else
                            <a class="casa-pagination-control" href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="casa-pagination-control" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next page') }}">›</a>
            @else
                <span class="casa-pagination-control opacity-45" aria-disabled="true" aria-label="{{ __('Next page') }}">›</span>
            @endif
        </div>
    </nav>
@endif
