import { trapBookingFocus } from './booking-dialog.js';

const initializedDialogs = new WeakSet();

export function initializeSettingsChartDialog(root = document) {
    const dialog = root.querySelector('[data-settings-chart-dialog]');
    if (!dialog || typeof dialog.showModal !== 'function' || initializedDialogs.has(dialog)) return;

    const disclosure = dialog.closest('details');
    const trigger = disclosure.querySelector('summary');
    const doc = dialog.ownerDocument;
    const initiallyOpen = disclosure.open;
    initializedDialogs.add(dialog);

    // The server renders an ordinary open dialog inside the disclosure for no-JS editing.
    dialog.removeAttribute('open');
    disclosure.open = false;
    dialog.dataset.enhanced = '';
    trigger.setAttribute('aria-haspopup', 'dialog');
    dialog.querySelectorAll('[data-settings-chart-close], .settings-chart-dialog-footer')
        .forEach(element => { element.hidden = false; });

    const open = () => {
        if (dialog.open) return;
        disclosure.open = true;
        dialog.showModal();
        doc.documentElement.classList.add('settings-chart-modal-open');
        dialog.querySelector('input:not([type="hidden"]):not([type="file"])')?.focus({ preventScroll: true });
    };
    const restorePage = () => {
        disclosure.open = false;
        doc.documentElement.classList.remove('settings-chart-modal-open');
        if (trigger.isConnected) trigger.focus({ preventScroll: true });
    };
    const close = () => {
        if (dialog.open) dialog.close();
        restorePage();
    };

    trigger.addEventListener('click', event => { event.preventDefault(); open(); });
    dialog.querySelectorAll('[data-settings-chart-close]').forEach(button => button.addEventListener('click', close));
    dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
    dialog.addEventListener('close', () => { if (!dialog.open) restorePage(); });
    dialog.addEventListener('keydown', event => { if (event.key === 'Tab') trapBookingFocus(event, dialog); });

    const outsidePanel = event => {
        const bounds = dialog.getBoundingClientRect();
        return event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right
            || event.clientY < bounds.top || event.clientY > bounds.bottom);
    };
    let backdropPressed = false;
    dialog.addEventListener('pointerdown', event => { backdropPressed = outsidePanel(event); });
    dialog.addEventListener('click', event => {
        if (backdropPressed && outsidePanel(event)) close();
        backdropPressed = false;
    });
    // Native validation must reveal the field even when the editor is closed.
    dialog.addEventListener('invalid', open, true);
    doc.addEventListener('turbo:before-cache', close);
    doc.defaultView?.addEventListener('pagehide', close, { once: true });
    if (initiallyOpen) open();
}
