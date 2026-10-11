export const isCustomerModalUrl = (url) => /^\/admin\/customers\/\d+\/?$/.test(url.pathname) && url.searchParams.get('modal') === '1';

export function restoreCustomerDetail(document, render, setTrigger) {
    const recovery = document.querySelector('[data-customer-detail-recovery]');
    if (!recovery) return;

    const trigger = [...document.querySelectorAll('[data-customer-open]')]
        .find((element) => element.dataset.customerOpen === recovery.dataset.customerId);
    setTrigger(trigger || null);
    render(recovery.innerHTML);
    recovery.remove();
}

export function clearCustomerDetailRecovery(document) {
    document.querySelectorAll('[data-customer-detail-recovery]').forEach((element) => element.remove());
}
