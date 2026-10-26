const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function createDrawerStore(doc = document, schedule = (callback) => window.requestAnimationFrame(callback)) {
    return {
        isOpen: false,
        trigger: null,

        open(trigger = null) {
            if (this.isOpen) return;
            this.trigger = trigger;
            this.isOpen = true;
            doc.documentElement.classList.add('casa-drawer-open');
            schedule(() => {
                const drawer = doc.querySelector('[data-workspace-drawer]');
                const target = drawer?.querySelector('[aria-current="page"]') || drawer?.querySelector(focusableSelector);
                target?.focus({ preventScroll: true });
            });
        },

        close({ restoreFocus = true } = {}) {
            if (!this.isOpen) return;
            this.isOpen = false;
            doc.documentElement.classList.remove('casa-drawer-open');
            const trigger = this.trigger || doc.querySelector('[data-drawer-toggle]');
            this.trigger = null;
            if (restoreFocus) trigger?.focus?.({ preventScroll: true });
        },

        toggle(trigger = null) {
            if (this.isOpen) this.close();
            else this.open(trigger);
        },

        trapFocus(event, drawer) {
            if (!this.isOpen) return;
            const items = [...drawer.querySelectorAll(focusableSelector)];
            if (items.length === 0) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && doc.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && doc.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    };
}

export function initializeWorkspaceDrawer(Alpine) {
    // nextTick runs once the drawer is shown, unlike animation frames, which pause in background tabs.
    Alpine.store('drawer', createDrawerStore(document, (callback) => Alpine.nextTick(callback)));

    // Navigations replace the page, so the drawer starts closed and never restores stale focus.
    const reset = () => Alpine.store('drawer').close({ restoreFocus: false });
    document.addEventListener('turbo:before-cache', reset);
    document.addEventListener('turbo:before-render', reset);
}
