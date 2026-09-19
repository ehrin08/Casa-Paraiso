const POLL_INTERVAL_MS = 30_000;
let pollTimer = null;
let activeRequest = null;
let generation = 0;
let lastAcknowledgedLocation = null;
let navigating = false;

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

const stopPolling = () => {
    generation += 1;
    window.clearTimeout(pollTimer);
    pollTimer = null;
    activeRequest?.abort();
    activeRequest = null;
};

const refreshCount = async () => {
    const root = notificationRoot();
    const countUrl = root?.dataset.bookingNotificationsCountUrl;

    if (!countUrl || document.hidden || navigating || activeRequest) {
        return;
    }

    const requestGeneration = generation;
    const controller = new AbortController();
    activeRequest = controller;
    const options = {
        signal: controller.signal,
        timeout: 10_000,
        headers: { Accept: 'application/json' },
    };
    const locationKey = window.location.href;
    const readUrl = root.dataset.bookingNotificationsReadUrl;
    const isSchedule = document.querySelector('[data-booking-schedule-link][aria-current="page"]');
    const shouldAcknowledge = readUrl && isSchedule && lastAcknowledgedLocation !== locationKey;
    let shouldContinue = true;

    try {
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

        if (requestGeneration !== generation || root !== notificationRoot()) {
            return;
        }

        // An expired session may redirect to HTML. Preserve the last known badge.
        if (!Number.isInteger(response.data?.count) || response.data.count < 0) {
            shouldContinue = false;
            return;
        }

        if (shouldAcknowledge) {
            lastAcknowledgedLocation = locationKey;
        }

        applyCount(response.data.count);
    } catch (error) {
        shouldContinue = ![401, 403, 419].includes(error.response?.status);
    } finally {
        if (requestGeneration === generation) {
            activeRequest = null;

            if (shouldContinue && !document.hidden && !navigating && root === notificationRoot()) {
                pollTimer = window.setTimeout(refreshCount, POLL_INTERVAL_MS);
            }
        }
    }
};

const restartPolling = () => {
    stopPolling();
    void refreshCount();
};

export const initializeBookingNotifications = () => {
    navigating = false;
    restartPolling();
};

document.addEventListener('visibilitychange', restartPolling);
window.addEventListener('online', restartPolling);
window.addEventListener('pageshow', restartPolling);
window.addEventListener('pagehide', stopPolling);

const pauseForNavigation = () => {
    navigating = true;
    lastAcknowledgedLocation = null;
    stopPolling();
};

document.addEventListener('turbo:before-visit', pauseForNavigation);
document.addEventListener('turbo:before-cache', pauseForNavigation);
