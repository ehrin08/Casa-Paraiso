# Live Updates Without Reloading (Task 9) Design

Date: 2026-11-02. Status: approved 2026-11-02. Source: [Planned Tasks](../../PLANNED_TASKS.md) Task 9.

## Intent

Staff keep the appointment queue, calendars, attendance and notifications open all day, but they see changes only after a full page reload. Their own saves also reload the whole page. This change makes those screens show other people's changes within about 15 seconds, and makes the user's own saves update in place.

## Goals

1. Appointment queues, calendars, admin attendance and notifications refresh in the background while the tab is visible.
2. Saves on those screens update the page in place and keep the scroll position.
3. The attendance scanner updates its status card without reloading.

## Non-goals

- Instant push. Hostinger shared hosting cannot run a WebSocket server, and the user chose not to pay for a hosted broadcast service.
- The workspace panel forms (create and edit appointment), which already have their own return flow.

## Anti-goals

- A refresh that loses what someone is typing, closes an open dialog, steals focus or jumps the scroll position.
- Any write that bypasses normal Laravel requests, CSRF, validation, or the transactional therapist capacity check.

## Approaches

| Approach | Trade-off |
| --- | --- |
| **Visible-page polling about every 15 seconds (chosen)** | Free and works on shared hosting. Changes appear within one polling interval. Builds on the existing booking-badge poller. |
| A hosted broadcast service (Pusher, Ably) | Instant, but it needs an account, credentials, a broadcast auth route and a monthly cost. |
| A self-hosted WebSocket server (Reverb) | Not possible on Hostinger shared hosting. |

## Design

- **Shared poller.** `resources/js/live-poller.js` generalizes the booking-badge loop into per-instance pollers: one request at a time, a pause on hidden tabs and during navigation, and a stop on authentication failure. The badge moves onto it at 15 seconds.
- **Change check.** `GET /live/state` returns short role-scoped fingerprints, built by `App\Services\LiveStateFingerprint`:
  - `appointments`: admin and reception see all appointments; a therapist sees their own; a customer sees their own.
  - `attendance`: admin only.
  - `notifications`: the user's own.

  Each fingerprint is the count, the highest id and the latest update. The endpoint renders no page.
- **Live regions.** Server-rendered blocks carry `data-live-region` and `data-live-scope`. When their scope's fingerprint changes, `resources/js/live-regions.js` fetches the current URL, parses it, and replaces each region's contents. It skips a region, and retries on the next tick, while focus is inside it, while it holds an edited form, or while the panel or a modal is open.
- **Calendars.** The operational and customer calendars get a silent `refresh()`. It keeps the current events if a request fails, never shows the loading state, and drops stale responses. It runs when the appointments fingerprint changes, and at least every 60 seconds.
- **Own saves.** The POST and PATCH forms on these screens opt into Turbo. Same-page redirects morph the page and preserve scroll. Without JavaScript the forms are unchanged.
- **Scanner.** After a successful scan, the status card refreshes in place instead of the page reloading.

## Left to the builder

The exact fingerprint queries; how regions are wrapped in each view; the Turbo fallback if Alpine components do not survive a morph.
