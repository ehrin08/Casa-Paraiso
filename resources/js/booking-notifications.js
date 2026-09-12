let subscribedUserId = null;
let latestSyncRequest = 0;
let lastAcknowledgedLocation = null;
let reconnectBound = false;

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

const syncCount = async () => {
    const root = notificationRoot();
    const countUrl = root?.dataset.bookingNotificationsCountUrl;

    if (!countUrl) {
        return;
    }

    const request = ++latestSyncRequest;

    try {
        const response = await window.axios.get(countUrl, { headers: { Accept: 'application/json' } });

        if (request === latestSyncRequest) {
            applyCount(response.data.count);
        }
    } catch {
        // The server-rendered badge remains available until the next successful sync.
    }
};

const acknowledgeSchedule = async () => {
    const root = notificationRoot();
    const readUrl = root?.dataset.bookingNotificationsReadUrl;
    const isSchedule = document.querySelector('[data-booking-schedule-link][aria-current="page"]');
    const locationKey = window.location.href;

    if (!readUrl || !isSchedule || lastAcknowledgedLocation === locationKey) {
        return;
    }

    lastAcknowledgedLocation = locationKey;

    try {
        const response = await window.axios.patch(readUrl, {}, {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        applyCount(response.data.count);
    } catch {
        lastAcknowledgedLocation = null;
    }
};

const subscribe = () => {
    const root = notificationRoot();
    const userId = root?.dataset.bookingNotificationsUserId;

    if (!userId || !window.Echo) {
        return;
    }

    if (subscribedUserId !== userId) {
        if (subscribedUserId) {
            window.Echo.leave(`users.${subscribedUserId}.booking-notifications`);
        }

        subscribedUserId = userId;
        window.Echo.private(`users.${userId}.booking-notifications`)
            .listen('.booking-notifications.changed', syncCount);
    }

    if (!reconnectBound) {
        window.Echo.connector.pusher.connection.bind('connected', syncCount);
        reconnectBound = true;
    }
};

export const initializeBookingNotifications = () => {
    subscribe();
    void syncCount();
    void acknowledgeSchedule();
};
