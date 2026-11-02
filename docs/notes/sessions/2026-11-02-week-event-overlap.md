---
type: session-log
date: 2026-11-02
---

# Weekly schedule booking text overlap

## Task

Fix the admin and reception **Weekly therapist schedule** grid. In a 30-minute booking block, the status ("Confirmed") was drawn over the customer and service text.

## What Changed

- `resources/views/components/calendar/week-schedule.blade.php`: the time and status now share one header row. The service and therapist lines are marked `casa-week-event-detail`.
- `resources/css/appointments.css`: each event is a flex column, so the status no longer sits in an absolutely positioned overlay. The header row is one line tall, so a status that does not fit in a narrow lane wraps out of view instead of being cut off. Compact blocks hide the detail lines.
- `resources/js/calendar-week.js`: added `weekEventSize(event)`, which returns `is-compact` for bookings of 30 minutes or less. `weekEventClass` in `resources/js/app.js` appends it.
- `tests/js/calendar-week.test.mjs`: added a test for compact sizing and the class string.

## Verification

- `node --test tests/js/*.test.mjs`: all 106 tests passed, including the new compact-size test.
- `npm run build` in the app container passed.
- Browser check at 1366 px: sample 30-, 60- and 90-minute bookings were put into the calendar state, including two overlapping lanes. The live feed could not be used: the dev database is missing the in-progress `operating_hour_rules` migration from separate work. The 30-minute block shows "time · status / customer", the longer blocks show every line, and narrow lanes drop the status cleanly.
- Full `php artisan test`: 637 passed (6465 assertions), rerun on its own after earlier runs collided with another session's run on the shared `testing` database.

## Decisions

- The status stays visible but de-emphasized in the header row. Full details remain in the block's `title` and `aria-label` and on the detail page.

## Follow-ups

- Parallel sessions running `php artisan test` against the one `testing` database break each other's runs. Run the suite from one session at a time.
