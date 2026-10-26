import { isBookingPanelUrl } from './booking-dialog.js';

// Create and edit pages that open in the centered form panel. Booking wizards keep their own presentation.
export const isFormPanelUrl = (url) => /\/(create|edit)\/?$/.test(url.pathname) && !isBookingPanelUrl(url);

export const panelReturnPath = (url) => `${url.pathname}${url.search}`;

// Mutating forms carry the panel URL so a validation redirect can reopen the same panel.
export function attachPanelReturn(root, url) {
    root.querySelectorAll('form').forEach((form) => {
        if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
        let input = form.querySelector('input[name="_panel"]');
        if (!input) {
            input = form.ownerDocument.createElement('input');
            input.type = 'hidden';
            input.name = '_panel';
            form.prepend(input);
        }
        input.value = panelReturnPath(url);
    });
}

export function isSafePanelPath(path, prefixes) {
    return typeof path === 'string'
        && path.startsWith('/')
        && !path.startsWith('//')
        && !path.includes('\\')
        && prefixes.some((prefix) => path.startsWith(prefix));
}

// The server marks the host after a failed panel submit; consume it once so a restored page never reopens it.
export function takePanelRestore(document, prefixes) {
    const host = document.querySelector('[data-panel-restore]');
    if (!host) return null;
    const path = host.getAttribute('data-panel-restore');
    host.removeAttribute('data-panel-restore');

    return isSafePanelPath(path, prefixes) ? new URL(path, document.location.origin) : null;
}
