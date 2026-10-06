export const isBookingPanelUrl = (url) => /^\/(admin|reception|customer)\/appointments\/(create|\d+\/edit)\/?$/.test(url.pathname);

export const isAppointmentPreviewUrl = (url) => /^\/admin\/appointments\/\d+\/?$/.test(url.pathname) && url.searchParams.get('preview') === '1';

export function visibleDialogControls(dialog) {
    return [...dialog.querySelectorAll('a[href], button, input:not([type="hidden"]), select, textarea, summary, [tabindex]')]
        .filter((element) => !element.matches(':disabled') && element.tabIndex >= 0 && element.getClientRects().length > 0 && !element.closest('[inert]'));
}

export function trapBookingFocus(event, dialog) {
    const controls = visibleDialogControls(dialog);
    const index = controls.indexOf(dialog.ownerDocument.activeElement);
    if (!controls.length) { event.preventDefault(); dialog.focus(); return; }
    if (index === -1 || (event.shiftKey && index === 0) || (!event.shiftKey && index === controls.length - 1)) {
        event.preventDefault();
        controls[event.shiftKey ? controls.length - 1 : 0].focus();
    }
}
