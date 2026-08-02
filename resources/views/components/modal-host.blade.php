<div data-panel-host data-turbo="false" class="casa-panel-host" aria-hidden="true">
    <div data-panel-backdrop class="casa-panel-backdrop"></div>

    <section
        class="casa-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="casa-panel-title"
        tabindex="-1"
    >
        <div class="casa-panel__bar">
            <div class="min-w-0">
                <p class="casa-section-label">{{ __('Workspace panel') }}</p>
                <h2 id="casa-panel-title" data-panel-title class="truncate font-display text-lg font-black text-casa-text">
                    {{ __('Loading') }}
                </h2>
            </div>

            <button type="button" data-panel-close class="casa-icon-button" aria-label="{{ __('Close panel') }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div data-panel-status class="casa-panel__status">
            {{ __('Loading workspace') }}
        </div>

        <div data-panel-content class="casa-panel__content"></div>
    </section>
</div>
