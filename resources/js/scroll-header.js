// Headers marked data-scroll-header slide away while scrolling down and return on scroll up.
export function nextHeaderHidden({ y, lastY, hidden, revealZone, threshold = 8 }) {
    if (y <= revealZone) return false;
    if (y - lastY > threshold) return true;
    if (lastY - y > threshold) return false;
    return hidden;
}

// An open menu, drawer or dropdown (aria-expanded) or keyboard focus keeps the header visible.
export function headerMustStay(header, doc = document) {
    return header.contains(doc.activeElement) || Boolean(header.querySelector('[aria-expanded="true"]'));
}

export function initializeScrollHeaders(doc = document, win = window) {
    let lastY = win.scrollY;
    let hidden = false;
    let frame = null;

    const headers = () => [...doc.querySelectorAll('[data-scroll-header]')];
    const apply = () => headers().forEach((header) => header.classList.toggle('is-scroll-hidden', hidden));

    const update = () => {
        frame = null;
        const y = Math.max(win.scrollY, 0);
        const list = headers();
        const revealZone = Math.max(0, ...list.map((header) => header.offsetHeight));
        const next = nextHeaderHidden({ y, lastY, hidden, revealZone });
        if (next !== hidden || Math.abs(y - lastY) > 8) lastY = y;
        hidden = next && !list.some((header) => headerMustStay(header, doc));
        apply();
    };

    const reveal = () => {
        lastY = Math.max(win.scrollY, 0);
        hidden = false;
        apply();
    };

    win.addEventListener('scroll', () => {
        if (frame === null) frame = win.requestAnimationFrame(update);
    }, { passive: true });
    doc.addEventListener('focusin', (event) => {
        if (event.target instanceof Element && event.target.closest('[data-scroll-header]')) reveal();
    });
    doc.addEventListener('turbo:load', reveal);
    win.addEventListener('pageshow', reveal);
}
