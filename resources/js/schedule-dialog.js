const installations = new WeakMap();
const enhancedForms = new WeakSet();

export function resetScheduleSubmission(root) {
    root.querySelectorAll('[data-schedule-original-label]').forEach((button) => {
        button.textContent = button.dataset.scheduleOriginalLabel;
        button.disabled = button.dataset.scheduleOriginalDisabled === 'true';
        delete button.dataset.scheduleOriginalLabel;
        delete button.dataset.scheduleOriginalDisabled;
    });
}

export const timeMinutes = (time, ending = false) => {
    if (ending && time === '00:00') return 1440;
    if (! /^\d{2}:\d{2}$/.test(time || '')) return NaN;
    const [hours, minutes] = time.split(':').map(Number);
    return hours < 24 && minutes < 60 ? hours * 60 + minutes : NaN;
};

export const durationLabel = (start, end) => {
    const minutes = timeMinutes(end, true) - timeMinutes(start);
    if (!Number.isFinite(minutes) || minutes <= 0) return 'Choose an end time after the start.';
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;
    return [hours ? `${hours} ${hours === 1 ? 'hour' : 'hours'}` : '', remainder ? `${remainder} minutes` : ''].filter(Boolean).join(' ');
};

export const isScheduleEditorUrl = (href, origin) => {
    try {
        const url = new URL(href, origin);
        return url.origin === origin && /^\/admin\/staff-schedule-roster\/(?:copy|\d+\/(?:bulk|review|shifts\/create|shifts\/\d+\/edit))$/.test(url.pathname);
    } catch { return false; }
};

function enhanceForms(root) {
    root.querySelectorAll('[data-schedule-submit]').forEach((form) => {
        if (enhancedForms.has(form)) return;
        enhancedForms.add(form);
        form.addEventListener('submit', () => {
            if (!form.checkValidity()) return;
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
                button.dataset.scheduleOriginalLabel = button.textContent;
                button.dataset.scheduleOriginalDisabled = String(button.disabled);
                button.disabled = true;
                button.textContent = form.action.endsWith('/bulk/review') ? 'Reviewing…' : (form.action.endsWith('/publish') ? 'Publishing…' : 'Saving…');
            });
        });
        const start = form.querySelector('[data-schedule-start]');
        const end = form.querySelector('[data-schedule-end]');
        const output = form.querySelector('[data-schedule-duration]');
        const updateTimes = (changed = false) => {
            if (!start || !end) return;
            [...end.options].forEach((option) => { option.disabled = timeMinutes(option.value, true) <= timeMinutes(start.value); });
            if (changed && end.selectedOptions[0]?.disabled) end.value = [...end.options].find((option) => !option.disabled)?.value ?? '00:00';
            if (output) output.textContent = durationLabel(start.value, end.value);
        };
        start?.addEventListener('change', () => updateTimes(true));
        end?.addEventListener('change', () => updateTimes());
        updateTimes();
        const mode = form.querySelector('[data-schedule-bulk-mode]');
        const updateMode = () => {
            const clear = mode?.value === 'clear';
            const times = form.querySelector('[data-schedule-times]');
            if (times) times.hidden = clear;
            [start, end].filter(Boolean).forEach((input) => { input.disabled = clear; });
        };
        mode?.addEventListener('change', updateMode);
        updateMode();
        form.querySelectorAll('[data-schedule-select-all]').forEach((button) => {
            button.hidden = false;
            button.addEventListener('click', () => {
                const choices = [...form.querySelectorAll('input[type="checkbox"]')].filter((input) => input.name === button.dataset.scheduleSelectAll && !input.disabled);
                const select = choices.some((input) => !input.checked);
                choices.forEach((input) => { input.checked = select; });
                button.textContent = select ? 'Clear selection' : 'Select all';
            });
        });
    });
}

export function createScheduleDialog({ dialog, content, fetchHtml, enhance = () => {}, focus = () => {}, onClose = () => {} }) {
    let request = 0;
    let abort = null;
    let trigger = null;
    return {
        async open(url, element) {
            const version = ++request;
            abort?.abort();
            abort = new AbortController();
            trigger = element;
            if (!dialog.open) dialog.showModal();
            content.innerHTML = '<section class="schedules-editor"><header class="schedules-editor-header"><h2 id="schedule-editor-title">Loading schedule editor…</h2><button type="button" class="casa-icon-button" data-schedule-dialog-close aria-label="Close schedule editor">×</button></header><div class="schedules-editor-body" role="status">Loading…</div></section>';
            try {
                const html = await fetchHtml(url, abort.signal);
                if (version !== request || !dialog.open) return;
                content.innerHTML = html;
                enhance(content);
                focus(content);
            } catch (error) {
                if (version !== request || error?.name === 'AbortError' || !dialog.open) return;
                const message = dialog.ownerDocument.createElement('p');
                message.setAttribute('role', 'alert');
                message.textContent = 'The editor could not be loaded. Open the full page to continue.';
                const link = dialog.ownerDocument.createElement('a');
                link.href = url;
                link.textContent = 'Open full editor';
                link.className = 'casa-button-secondary';
                const body = content.querySelector('.schedules-editor-body');
                body.replaceChildren(message, link);
            }
        },
        close() { if (dialog.open) dialog.close(); },
        reset() {
            request++;
            abort?.abort();
            abort = null;
            content.replaceChildren();
            if (trigger?.isConnected) trigger.focus({ preventScroll: true });
            trigger = null;
            onClose();
        },
    };
}

export function initializeScheduleDialog(doc = document) {
    installations.get(doc)?.();
    enhanceForms(doc);
    const restoreSubmission = () => resetScheduleSubmission(doc);
    doc.defaultView.addEventListener('pageshow', restoreSubmission);
    const dialog = doc.querySelector('#therapist-schedule-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') {
        installations.set(doc, () => doc.defaultView.removeEventListener('pageshow', restoreSubmission));
        return;
    }
    const content = dialog.querySelector('[data-schedule-dialog-content]');
    const controller = createScheduleDialog({
        dialog, content,
        fetchHtml: async (href, signal) => {
            const url = new URL(href, doc.location.origin);
            url.searchParams.set('modal', '1');
            const response = await fetch(url, { signal, headers: { 'X-Casa-Panel': '1', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok || response.redirected) throw new Error('Editor unavailable');
            return response.text();
        },
        enhance: enhanceForms,
        focus: (root) => (root.querySelector('.schedules-editor-body select:not(:disabled), .schedules-editor-body input:not([type="hidden"]):not(:disabled)') ?? root.querySelector('[data-schedule-dialog-close]'))?.focus(),
        onClose: () => doc.body.classList.remove('schedules-dialog-open'),
    });
    const click = (event) => {
        const close = event.target.closest?.('[data-schedule-dialog-close], [data-schedule-dialog-cancel]');
        if (close && dialog.contains(close)) { event.preventDefault(); controller.close(); return; }
        const link = event.target.closest?.('a[data-schedule-editor]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
            || !isScheduleEditorUrl(link.href, doc.location.origin)) return;
        event.preventDefault();
        doc.body.classList.add('schedules-dialog-open');
        controller.open(link.href, link);
    };
    const close = () => controller.reset();
    const teardown = () => { controller.close(); controller.reset(); };
    doc.addEventListener('click', click);
    dialog.addEventListener('close', close);
    doc.addEventListener('turbo:before-cache', teardown);
    doc.defaultView.addEventListener('pagehide', teardown);
    installations.set(doc, () => {
        teardown();
        doc.removeEventListener('click', click);
        dialog.removeEventListener('close', close);
        doc.removeEventListener('turbo:before-cache', teardown);
        doc.defaultView.removeEventListener('pagehide', teardown);
        doc.defaultView.removeEventListener('pageshow', restoreSubmission);
    });
}
