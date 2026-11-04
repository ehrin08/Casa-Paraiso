---
type: session-log
date: 2026-11-04
---

# Therapist schedule times across the whole day

## Task

The user asked for therapist schedule times not to start at 1:00 PM, listing the whole day so shifts can follow the operating hours, and then to put the change live.

## What Changed

- `app/Services/TherapistSchedulePage.php`: Start lists 12:00 AM to 11:30 PM; End lists 12:30 AM to 11:30 PM, then 12:00 midnight.
- `app/Services/RosterShiftRules.php`: shifts no longer have to start at the default opening time; they still use 30-minute boundaries, end after they start, end by midnight and must not overlap.
- `StaffWeeklyScheduleRequest` and `StaffScheduleExceptionRequest`: the same opening-time rejection was removed.
- Form hints now say any half hour from 12:00 AM is allowed and only time inside operating hours is bookable.
- Booking is unchanged: `ScheduleWindowResolver` clamps each shift to the day's operating hours, special hours and partial closures.
- Tests: `WeeklyRosterTest` gains a full-day case (a 09:00–15:00 shift is bookable from 1:00 PM on default hours and from 10:00 AM on a special-hours day) and its invalid-time case uses 09:15.
- Commit `6edd954` (local, not pushed).
- Release package `output/hostinger-release/casa-paraiso-hostinger-20261104-003305-9c9ea9.zip` built and smoke-tested.

## Verification

- Targeted: schedule, roster, calendar, operating-hours and therapist layout tests (72 passed); `tests/js/schedule-dialog.test.mjs` (7 passed).
- Full suite 657 passed, `npm run build`, Docker startup through the wrapper, `git diff --check`.
- Not deployed: SSH to the Hostinger account (port 65002) was refused at release time, while https://casaparaiso.site/up returned 200.

## Decisions

- The whole day is allowed rather than tying the dropdowns to current operating hours, so schedules keep working when hours or special hours change.
- No migration, so the release needs no database dump; rollback is a link switch to `casa-paraiso-redesign-20261104c/public`.

## Follow-ups

- [ ] Re-enable SSH access in hPanel, then deploy the package as `casa-paraiso-redesign-20261104d/` (same steps as 20261104c) and repoint the hPanel cron.
- [ ] Push `redesign` and fast-forward `main` when ready.
