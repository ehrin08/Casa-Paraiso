import { createPoller } from './live-poller';

const POLL_INTERVAL_MS = 15_000;
let lastAcknowledgedLocation = null;

const notificationRoot = () => document.querySelector('[data-booking-notifications]');

const applyCount = (count) => {
    const normalized = Math.max(0, Number.parseInt(String(count), 10) || 0);

    document.querySelectorAll('[data-booking-notification-badge]').forEach((badge) => {
        const value = badge.querySelector('[data-booking-notification-badge-value]');
        const visibleValue = normalized > 99 ? '99+' : String(normalized);

        badge.classList.toggle('hidden', normalized === 0);
        badge.setAttribute('aria-label', `${normalized} unread booking notification${normalized === 1 ? '' : 's'}`);

        if (value) {
            value.textContent = visibleValue;
        }
    });
};

const refreshCount = async (signal) => {
    const root = notificationRoot();
    const countUrl = root.dataset.bookingNotificationsCountUrl;
    const options = {
        signal,
        timeout: 10_000,
        headers: { Accept: 'application/json' },
    };
    const locationKey = window.location.href;
    const readUrl = root.dataset.bookingNotificationsReadUrl;
    const isSchedule = document.querySelector('[data-booking-schedule-link][aria-current="page"]');
    const shouldAcknowledge = readUrl && isSchedule && lastAcknowledgedLocation !== locationKey;

    // Acknowledge on entering a schedule; subsequent polls only read the count.
    // Serializing these requests prevents a stale count overwriting the acknowledgement.
    const response = shouldAcknowledge
        ? await window.axios.patch(readUrl, {}, {
            ...options,
            headers: {
                ...options.headers,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        })
        : await window.axios.get(countUrl, options);

    if (signal.aborted || root !== notificationRoot()) {
        return true;
    }

    // An expired session may redirect to HTML. Preserve the last known badge.
    if (!Number.isInteger(response.data?.count) || response.data.count < 0) {
        return false;
    }

    if (shouldAcknowledge) {
        lastAcknowledgedLocation = locationKey;
    }

    applyCount(response.data.count);

    return true;
};

const poller = createPoller({
    interval: POLL_INTERVAL_MS,
    run: refreshCount,
    canRun: () => Boolean(notificationRoot()?.dataset.bookingNotificationsCountUrl),
});

export const initializeBookingNotifications = () => {
    poller.start();
};

const forgetAcknowledgement = () => {
    lastAcknowledgedLocation = null;
};

document.addEventListener('turbo:before-visit', forgetAcknowledgement);
document.addEventListener('turbo:before-cache', forgetAcknowledgement);
