import { createPoller } from './live-poller';

export const LIVE_POLL_INTERVAL_MS = 15_000;

const scopesOf = (region) => (region.dataset.liveScope || '').split(/\s+/).filter(Boolean);

/**
 * One page-wide `/live/state` poller. Subscribers hear which scopes changed on every poll,
 * and `[data-live-region]` blocks whose scope changed are refetched from the current URL.
 * A region is left alone while someone is using it and retried on the next poll.
 */
export const createLiveState = ({
    win = window,
    doc = document,
    get = (url, options) => win.axios.get(url, options),
    fetchPage = async (url, signal) => {
        const response = await win.fetch(url, {
            signal,
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest', 'X-Casa-Live': '1' },
        });

        return response.ok && !response.redirected ? response.text() : null;
    },
    parse = (html) => new win.DOMParser().parseFromString(html, 'text/html'),
    interval = LIVE_POLL_INTERVAL_MS,
} = {}) => {
    const subscribers = new Set();
    const pending = new Set();
    let fingerprints = null;

    const stateUrl = () => doc.querySelector('meta[name="casa-live-state-url"]')?.getAttribute('content') || '';
    const regions = () => [...doc.querySelectorAll('[data-live-region]')];

    const isBusy = (region) => {
        const active = doc.activeElement;

        if (active && active !== doc.body && region.contains(active)) {
            return true;
        }

        if (region.querySelector('form[data-live-dirty]')) {
            return true;
        }

        if (doc.querySelector('[data-panel-host].is-open')) {
            return true;
        }

        return Boolean(win.Alpine?.store?.('casaModal')?.active);
    };

    const swapRegions = async (signal, names) => {
        const targets = regions().filter((region) => names.has(region.dataset.liveRegion));

        if (targets.length === 0) {
            return;
        }

        if (targets.some(isBusy)) {
            targets.forEach((region) => pending.add(region.dataset.liveRegion));
            return;
        }

        const html = await fetchPage(win.location.href, signal);

        if (signal.aborted || html === null) {
            return;
        }

        const fresh = parse(html);

        targets.forEach((region) => {
            const name = region.dataset.liveRegion;
            const replacement = fresh.querySelector(`[data-live-region="${name}"]`);

            pending.delete(name);

            if (!replacement || !region.isConnected) {
                return;
            }

            region.innerHTML = replacement.innerHTML;
            region.dispatchEvent(new win.CustomEvent('casa:live-region-updated', { bubbles: true }));
        });
    };

    const run = async (signal) => {
        const response = await get(stateUrl(), { signal, timeout: 10_000, headers: { Accept: 'application/json' } });

        if (signal.aborted) {
            return true;
        }

        const next = response.data?.scopes;

        // An expired session may redirect to HTML; stop instead of comparing nonsense.
        if (!next || typeof next !== 'object') {
            return false;
        }

        const changed = fingerprints === null
            ? []
            : Object.keys(next).filter((scope) => fingerprints[scope] !== next[scope]);

        fingerprints = next;
        subscribers.forEach((callback) => callback(changed));

        const names = new Set(pending);
        regions()
            .filter((region) => scopesOf(region).some((scope) => changed.includes(scope)))
            .forEach((region) => names.add(region.dataset.liveRegion));

        await swapRegions(signal, names);

        return true;
    };

    const poller = createPoller({
        interval,
        run,
        canRun: () => Boolean(stateUrl()) && (subscribers.size > 0 || regions().some((region) => scopesOf(region).length > 0)),
        win,
        doc,
    });

    doc.addEventListener('input', (event) => {
        event.target?.closest?.('[data-live-region] form')?.setAttribute('data-live-dirty', '');
    });

    return {
        start() {
            fingerprints = null;
            pending.clear();
            poller.start();
        },
        stop: () => poller.stop(),
        subscribe(callback) {
            subscribers.add(callback);

            return () => subscribers.delete(callback);
        },
        /** Refetch the named regions now, for example after the user's own action. */
        async refreshRegions(names) {
            const controller = new AbortController();
            await swapRegions(controller.signal, new Set(names));
        },
    };
};
