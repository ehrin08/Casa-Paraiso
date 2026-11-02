---
type: session-log
date: 2026-11-02
---

# Operating hours replace closed dates

## Task

Turn the closed-dates page into an operating-hours page so admins control both times and dates: editable default hours, closures for a day or a range, special hours, partial-day closures, and an "until further notice" option, shown on a month calendar.

## What Changed

- Additive migration `2026_11_03_010000`:
  - adds `opens_at`/`closes_at` to `application_settings`;
  - creates `operating_hour_rules` (`closed`, `special_hours`, `partial_closure`; a null `ends_on` means until further notice);
  - copies every `closed_dates` row in as a one-day closure. `closed_dates` stays as rollback evidence.
- `ScheduleWindowResolver` now resolves each date from the default hours plus the rules. Precedence: closure, then special hours, then partial closures subtracted. Availability, booking checks, reschedules, staff calendars and roster templates all follow it. Default hours come from settings instead of config, and `closes_next_day` is gone.
- New `Admin\OperatingHoursController`, `OperatingHourRuleRequest`, `OperatingHoursRequest`, `OperatingHoursGuard` and the `admin/operating-hours` page:
  - a default hours form;
  - a rule form;
  - a month calendar;
  - a list of changes with End and Remove.
- `/admin/closed-dates` redirects permanently. `ClosedDateController`, its request, view and test are removed.
- Every change, including ending or removing a rule, is refused while it would leave confirmed appointments outside opening hours. The refusal lists those appointments.
- The settings card, the hours stat, the homepage hours and the validation messages now show the admin-set hours.
- `ApplicationSetting::updateCurrent()` starts a first row from the defaults, so the hours form can create it.
- Docs updated: AGENTS.md, PROJECT_MEMORY, MVP_SCOPE, SCREEN_FLOW, DATABASE_DESIGN.

## Verification

- `OperatingHoursTest`: 10 tests, 126 assertions. With the related suites (`OperationalBookingAvailabilityTest`, `AdminSettingsTest`, `AdminTherapistsLayoutTest`, `ExampleTest`, roster tests): 74 passed.
- Full `php artisan test` in an isolated `compose.testing.yaml` project: 636 passed, 1 failed.
  - The failure is `PaymentPricingTest::test_gold_member_gets_ten_percent_off_the_service_only`. It passed on 3 of 3 reruns, so it is flaky and unrelated to this change. A random factory birth date in November probably triggers the birthday perk.
- `npm run build` passed. The additive migration ran on the local database.
- Closed-date carry-over checked in an isolated database: a `closed_dates` row became a one-day `closed` rule, and the original row stayed.
- Browser checks on the local app:
  - the page renders, and a clicked date prefills the form;
  - the time fields and end-date field show and hide with the chosen options;
  - no horizontal overflow at 375 px.
- Runs against the shared `testing` database collided with another session's concurrent tests, which is why the isolated stack was used.

## Decisions

- Default hours are one time range for every day; the month calendar is the management view. This is what the user chose.
- "Indefinitely" means a rule without an end date.
- Changes that would strand confirmed appointments are blocked, not saved with a warning.
- Ending a started rule keeps it on record up to yesterday. Future rules are deleted.
- Special-hours rules cannot overlap each other. Closures may overlap anything and win.

## Follow-ups

- Deploying needs a private database dump before the additive migration, as in earlier releases. Live closed dates are copied in automatically.
- Stabilise `PaymentPricingTest` by giving its members a fixed birth date outside November.
