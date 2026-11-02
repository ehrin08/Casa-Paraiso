---
type: session-log
date: 2026-11-02
---

# Fully anonymous feedback

## Task

The user asked for anonymous customer reviews to be fully anonymous. Until now `feedback.is_anonymous` hid the customer from therapists only, and admins still saw the name, email and an Anonymous badge. The user chose to hide the name from every role and to apply this to existing anonymous reviews too.

## What Changed

- `Feedback::staffDisplayName()` is now `Feedback::displayName()`, which returns "Anonymous customer" for any viewer. New `Feedback::identified()` scope limits a query to named feedback.
- Admin feedback list and detail:
  - The list and detail show "Anonymous customer" with no email or phone.
  - Name search skips anonymous rows, and sorting by customer treats them as a blank name.
  - The badge tooltip says "The customer chose to stay anonymous."
- The admin dashboard's latest feedback card uses `displayName()`.
- The admin appointment page shows "Anonymous feedback submitted" with no link, because the appointment identifies the customer.
- The admin customer page and the customers report leave anonymous feedback out of the history and the feedback count.
- Reports:
  - The feedback report table and CSV show "Anonymous customer". Name search and sort follow the same rules as the feedback list.
  - The CSV column `Hidden From Therapist` is renamed `Anonymous`.
- Customer form: the checkbox is now "Submit anonymously". Its help text says nobody at Casa Paraiso, managers included, can see the name or follow up. The customer's own list shows an "Anonymous" badge.
- No migration. The record stays linked to the customer and appointment for eligibility and one review per appointment.
- Docs updated: [DATABASE_DESIGN.md](../../DATABASE_DESIGN.md), [SCREEN_FLOW.md](../../SCREEN_FLOW.md), [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md), [PLANNED_TASKS.md](../../PLANNED_TASKS.md).

## Verification

- `CustomerFeedbackAnonymityTest` covers the admin side. It checks the list, sort, search, detail, dashboard, appointment page, customer page, report screen and CSV.
- Another session was running the full suite on the shared `testing` database at the same time, and the two runs collided (lock waits and missing columns).
  - Tests were therefore run on a temporary `testing_feedback` database with a temporary phpunit config. Both were removed afterwards.
  - The related tests (feedback, report, customer, dashboard) passed: 191 tests.
- The full suite on that database ran 637 tests with 9 failures, all expected from the temporary setup.
  - `DatabaseTestIsolationTest` failed because it requires the database name `testing`.
  - `RosterConcurrencyTest` failed because its worker processes use the default `testing` database.
- After the other session's runs finished, the full suite was rerun with `php artisan test` on the normal `testing` database. All 637 tests passed.
- `npm run build` passed. The Docker containers are up and `/up` returns 200. `git diff --check` is clean.

## Decisions

- Anonymous now means hidden from everyone, including admins and super admins. This reverses the 2026-10-30 choice, at the user's request.
- Existing anonymous reviews are covered too, so no new flag is needed.

## Follow-ups

- None. Two sessions running tests at once collide on the shared `testing` database, so run the suite in one session at a time.
