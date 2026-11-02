---
type: session-log
date: 2026-11-02
---

# Customer double-booking guard

## Task

Stop a customer from holding two confirmed appointments at the same time with different therapists. Before this fix, overlap checks only ran per therapist.

## What Changed

- `app/Services/AppointmentWorkflow.php`: added `hasCustomerOverlap()`, which uses the same half-open interval test as `hasConfirmedOverlap()` but filters on `customer_profile_id`.
  - `autoBook()` computes the end time earlier. It rejects an overlap under the existing customer-profile lock, before choosing a therapist.
  - `scheduleTransaction()`, used for staff create and reschedule, locks the customer profile when one is set. It ignores the appointment being edited and rejects an overlap on `scheduled_start_at`.
- `tests/Feature/CustomerBookingSafeguardsTest.php`: four new tests.
  - An overlapping self-booking with another therapist is rejected.
  - Back-to-back and cancelled bookings are still allowed.
  - A staff (admin) overlapping booking is rejected.
  - A reschedule that overlaps only its own appointment succeeds.
- `docs/PROJECT_MEMORY.md`: recorded the rule.

## Verification

- `php artisan test --filter=CustomerBookingSafeguardsTest`: 11 passed.
- Full `php artisan test`: 641 passed (6508 assertions).
- `npm run build` passed. The only warning was the existing chunk-size notice.
- `./scripts/casa-docker.ps1 start` and `status`: the app, MariaDB and Mailpit are running, and MariaDB is healthy.

## Decisions

- The rule applies to every booking path, not only to online self-booking. One person cannot be in two treatment rooms at once.
- No migration was added. The check reuses the existing transactional locks.

## Follow-ups

- Existing overlapping appointments are not changed. If any exist, staff should review them.
