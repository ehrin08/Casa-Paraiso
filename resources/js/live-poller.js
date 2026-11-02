const AUTH_LOST_STATUSES = [401, 403, 419];

/**
 * Visible-page HTTP polling shared by the booking badge, live regions and calendars.
 * Requests never overlap, hidden tabs and Turbo visits pause polling, and an expired
 * session stops it. `run(signal)` returns false to stop; it must ignore its result
 * once `signal.aborted` is true.
 */
export const createPoller = ({ interval, run, canRun = () => true, win = window, doc = document }) => {
    let timer = null;
    let controller = null;
    let stopped = true;
    let navigating = false;
    let pending = false;

    const cancel = () => {
        win.clearTimeout(timer);
        timer = null;
        controller?.abort();
        controller = null;
        pending = false;
    };

    const tick = async () => {
        timer = null;

        if (stopped || doc.hidden || navigating || controller || !canRun()) {
            return;
        }

        const current = new AbortController();
        controller = current;
        let keepGoing = true;

        try {
            keepGoing = (await run(current.signal)) !== false;
        } catch (error) {
            keepGoing = !AUTH_LOST_STATUSES.includes(error?.response?.status);
        } finally {
            if (controller === current) {
                controller = null;

                if (!keepGoing) {
                    stopped = true;
                } else if (!stopped && !doc.hidden && !navigating) {
                    if (pending) {
                        pending = false;
                        void tick();
                    } else {
                        timer = win.setTimeout(tick, interval);
                    }
                }
            }
        }
    };

    const restart = () => {
        if (stopped) {
            return;
        }

        cancel();
        void tick();
    };

    // The first pageshow follows the initial start; only a back/forward cache restore needs a restart.
    const restartOnRestore = (event) => {
        if (event?.persisted !== false) {
            restart();
        }
    };

    const pauseForNavigation = () => {
        navigating = true;
        cancel();
    };

    const listeners = [
        [doc, 'visibilitychange', restart],
        [win, 'online', restart],
        [win, 'pageshow', restartOnRestore],
        [win, 'pagehide', cancel],
        [doc, 'turbo:before-visit', pauseForNavigation],
        [doc, 'turbo:before-cache', pauseForNavigation],
    ];

    listeners.forEach(([target, name, listener]) => target.addEventListener(name, listener));

    return {
        start() {
            stopped = false;
            navigating = false;
            cancel();
            void tick();
        },
        stop() {
            stopped = true;
            cancel();
        },
        refreshNow() {
            if (stopped) {
                return;
            }

            if (controller) {
                pending = true;
                return;
            }

            win.clearTimeout(timer);
            void tick();
        },
        destroy() {
            stopped = true;
            cancel();
            listeners.forEach(([target, name, listener]) => target.removeEventListener?.(name, listener));
        },
    };
};
