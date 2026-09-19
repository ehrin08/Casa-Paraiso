const preferenceKey = 'casa.sidebar.collapsed';

export function initializeSidebar(Alpine) {
    let hideTimer;
    let animationTimer;
    const desktop = window.matchMedia('(min-width: 1024px)');

    Alpine.store('sidebar', {
        collapsed: document.documentElement.dataset.sidebarCollapsed === 'true',
        tooltip: null,

        toggle() {
            this.hideTooltip();
            document.documentElement.classList.add('casa-sidebar-animating');
            this.collapsed = !this.collapsed;
            document.documentElement.dataset.sidebarCollapsed = String(this.collapsed);
            try {
                window.localStorage.setItem(preferenceKey, String(this.collapsed));
            } catch {
                // The sidebar still works when browser storage is unavailable.
            }
            window.clearTimeout(animationTimer);
            animationTimer = window.setTimeout(() => {
                document.documentElement.classList.remove('casa-sidebar-animating');
            }, 180);
        },

        showTooltip(event) {
            const control = event.target.closest('[data-sidebar-label]');
            if (!control || !this.collapsed || !desktop.matches) return;
            this.keepTooltip();
            const bounds = control.getBoundingClientRect();
            const sidebar = control.closest('[data-desktop-sidebar]').getBoundingClientRect();
            this.tooltip = {
                label: control.dataset.sidebarLabel,
                top: Math.max(28, Math.min(window.innerHeight - 28, bounds.top + bounds.height / 2)),
                left: sidebar.right + 8,
            };
        },

        keepTooltip() {
            window.clearTimeout(hideTimer);
        },

        deferHideTooltip() {
            this.keepTooltip();
            hideTimer = window.setTimeout(() => this.hideTooltip(), 120);
        },

        hideTooltip() {
            this.keepTooltip();
            this.tooltip = null;
        },
    });

    const sidebar = () => Alpine.store('sidebar');
    const resetTransientState = () => {
        sidebar().hideTooltip();
        document.documentElement.classList.remove('casa-sidebar-animating');
    };
    window.addEventListener('resize', resetTransientState);
    document.addEventListener('scroll', () => sidebar().hideTooltip(), true);
    document.addEventListener('turbo:before-cache', resetTransientState);
    document.addEventListener('turbo:before-render', resetTransientState);
    document.addEventListener('turbo:load', () => {
        document.documentElement.dataset.sidebarCollapsed = String(sidebar().collapsed);
    });
}
