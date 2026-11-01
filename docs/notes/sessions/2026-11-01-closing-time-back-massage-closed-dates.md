---
type: session-log
date: 2026-11-01
---

# 11 PM Closing, Back Massage Service and Closed Dates (Task 8)

## Task

Build Task 8 items 1–3 after the client confirmed two facts: the spa closes at 11:00 PM, and the PHP 299 back massage can be booked on its own. Also add admin-managed closed dates for holidays.

## What Changed

- **Closing time:**
  - `config/casa.php` `business_hours` now closes at `23:00`, with `closes_next_day` false and the window "1:00 PM to 11:00 PM".
  - The booking wording on the landing page, the booking calendar, the week schedule label and the workflow error now says 11:00 PM.
  - `RosterShiftRules::withinShiftHours` keeps therapist shifts allowed until midnight, because shifts are work hours. The resolver clamps bookable time to 11 PM, so effective-hour summaries now read 11:00 PM.
- **Back massage:**
  - Data migration `2026_11_02_000000_add_back_massage_service` adds `back-massage` (30 min, ₱299) to installed catalogs and assigns GAIA TOUCH therapists.
  - It was also added to `service_packages` and to the seeded demo therapist.
  - `ServiceFactory` skips it, so random test services stay full-length.
- **Closed dates:**
  - Migration `2026_11_02_010000_create_closed_dates_table` and model `ClosedDate`.
  - `ScheduleWindowResolver::closedReason()` / `closedReasonsBetween()` make `effectiveWindows()` empty on a closed date.
  - Month availability leaves closed dates out of the store dates and returns them in a new `closed_dates` field (date → reason). The booking calendar labels those days Closed.
  - The admin page (`Admin\ClosedDateController`, `ClosedDateRequest`, `admin/closed-dates/index`) is linked from Settings. Saving refuses a date that has confirmed appointments and lists them with links.
- Tests:
  - Updated slot, roster-summary and landing expectations to the 11 PM close.
  - Added `BackMassageServiceMigrationTest`, `ClosedDatesTest`, and a 30-minute last-start check.
- Docs: [MVP_SCOPE.md](../../MVP_SCOPE.md), [DATABASE_DESIGN.md](../../DATABASE_DESIGN.md), [SCREEN_FLOW.md](../../SCREEN_FLOW.md), [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md), [PLANNED_TASKS.md](../../PLANNED_TASKS.md) and `AGENTS.md`.

## Verification

- Targeted scheduling, roster, closed-date and back-massage tests pass.
- Full Laravel suite: 625 passed.
  - `MembershipTest` failed once with "not instantiable" in a full run, then passed on its own and in the next full run.
- All 95 JS tests pass, and `npm run build` succeeded.
- `casa-docker.ps1 start` succeeded. The two additive migrations were applied to the local database: the service was added with 2 therapists.
- Browser, signed in as the seeded local admin:
  - Closing 2 Nov (it has a confirmed 3:00 PM booking) was refused, with the booking listed.
  - Closing 25 Dec worked. The booking calendar showed it as Closed, and other days close at 23:00.
  - The test closure was then removed.

## Decisions

- Treatments must finish by 11:00 PM, the same rule as before with an earlier close.
- A closed date cannot be saved while confirmed appointments exist on it. Saving locks therapist rows first, so no booking can slip in.
- The back massage is a data migration, so the local copy and Hostinger match. It only changes installed catalogs, and fresh databases use the seeder.
- The week grid stays 1 PM–midnight, so older late appointments remain visible.

## Follow-ups

- Deploy to Hostinger: take a database dump, then run the two 2026-11-02 migrations. Close 1–2 November there only if the history matters (past dates cannot be added through the form).
- Task 8 items 4–7 (promos, contact details, business name, Messenger) remain with the client.
