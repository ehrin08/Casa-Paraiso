---
type: session-log
date: 2026-11-02
---

# Live updates without reloading (Task 9)

## Task

Build Task 9 from [PLANNED_TASKS.md](../../PLANNED_TASKS.md). The user chose:
- both kinds of update (other people's changes appear, and their own saves update in place);
- on the appointment queues, calendars, attendance and notifications;
- with visible-page polling about every 15 seconds.

## What Changed

- [Design](../../superpowers/specs/2026-11-02-live-updates-design.md) committed first.
- **Shared poller.** `resources/js/live-poller.js` (`createPoller`) keeps one request at a time, pauses on hidden tabs and Turbo visits, and stops on an expired session. `booking-notifications.js` now uses it, polling every 15 seconds instead of 30.
- **Change check.** `GET /live/state` (`live.state`, `LiveStateController`, `App\Services\LiveStateFingerprint`) returns short role-scoped fingerprints for `appointments`, `attendance` and `notifications`, with `no-store`.
- **Region swaps.** `resources/js/live-regions.js` (`window.casaLiveState`) refetches the current URL and swaps `data-live-region` blocks whose scope changed. It waits while focus is inside the block, its form has been edited, or the panel or a modal is open. The regions are:
  - the admin queue and today counts (the completion dialogs moved inside the queue region);
  - the reception counts;
  - the notification tabs and list;
  - the admin attendance records;
  - the staff attendance status card.
- **Calendars.** The operational and customer calendars got a silent `refresh()`. It runs on an appointments change, or at least every 60 seconds, and keeps the events if a request fails. The queue's hidden `mode` inputs read the calendar's `data-calendar-mode`, so a swapped queue keeps the chosen mode.
- **Scanner.** It updates the status card instead of reloading.
- **Turbo saves.** Queue Finish and No-show, notification open and read-all, and attendance corrections submit through Turbo:
  - `x-confirm-action` takes a new `turbo` prop.
  - `<x-live-saves />` adds `turbo-refresh-scroll` preserve through a new `head` slot in the app layout.
- **Tests.** New `LiveStateTest`, `LiveRegionMarkupTest`, `tests/js/live-poller.test.mjs` and `tests/js/live-regions.test.mjs`, plus calendar refresh cases in `calendar-week.test.mjs` and `customer-appointment-calendar.test.mjs`. The booking badge test now expects 15 seconds.
- **Docs.** Updated [Project Memory](../../PROJECT_MEMORY.md), [Tech Stack](../../TECH_STACK.md), [Planned Tasks](../../PLANNED_TASKS.md) and [HOME](../../HOME.md).

## Verification

- 55 related feature tests passed, and so did all 106 JS tests.
- Full gate:
  - `php artisan test` passed 637 tests on an idle container. An earlier run failed 30 tests because another session reset the shared `testing` database mid-run.
  - `npm run build` passed.
  - The Docker `app`, `mariadb` and `mailpit` containers are running, and `/up` returns 200.
- In the browser as the local admin:
  - `/live/state` was called once at load, then every 15 seconds.
  - After a demo appointment's timestamp was touched, the queue was swapped within one poll, and Finish still opened a single completion dialog.
  - A Turbo same-page save kept the page state and scroll position.

## Decisions

- Turbo morphing (`turbo-refresh-method=morph`) was tried and rejected. After a morph refresh, the workspace drawer stuck open. A plain Turbo replace with `turbo-refresh-scroll` preserve kept the scroll position and left Alpine working.
- The first `pageshow` no longer restarts polling; only a back/forward cache restore does. This avoids duplicate requests on load.
- Live state starts on `turbo:load` only, which also fires on the first page load.

## Follow-ups

- [ ] Commit the implementation. Other sessions edited the same checkout at the same time (operating hours, the attendance camera prompt, week-event sizing, anonymous feedback). Their hunks share lines with this task in `resources/js/app.js`, `resources/views/staff/attendance.blade.php` and `tests/js/calendar-week.test.mjs`, so the work was left uncommitted for the owner to decide. Only the design doc (`e6b1e89`) is committed.
- A real Finish, No-show or attendance correction through Turbo was not exercised in the browser, to avoid changing local business data. Feature tests cover the markup.
