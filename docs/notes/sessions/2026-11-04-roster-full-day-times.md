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
- Live release `casa-paraiso-redesign-20261104d`, uploaded to the private `.casa-deploy-20261104d/` folder:
  - its `.env` is a mode-600 copy of the 20261104c release's (never displayed);
  - its `storage/app/public` is a symlink to `casa-paraiso/storage/app/public`, and `public/storage` links to that;
  - config, route and view caches were built;
  - `public_html` was switched atomically to it.

## Verification

- Targeted: schedule, roster, calendar, operating-hours and therapist layout tests (72 passed); `tests/js/schedule-dialog.test.mjs` (7 passed).
- Full suite 657 passed, `npm run build`, Docker startup through the wrapper, `git diff --check`.
- SSH (port 65002) was refused from the first network; it worked after the user switched networks.
- Host: `sha256sum -c` passed. No pending migrations. `casa:audit-crud-integrity` passed. `casa:check-hosting` first failed on the public storage link, because the extracted release had no `public/storage`; after creating it, every check passed.
- Live: `/`, `/login` and `/up` return 200. `/admin/staff-schedules` redirects guests to login. `/.env` returns 403 and `/composer.json` returns 404. No log files were written. The served `TherapistSchedulePage.php` has the full-day time list.

## Decisions

- The whole day is allowed rather than tying the dropdowns to current operating hours, so schedules keep working when hours or special hours change.
- No migration, so the release needs no database dump; rollback is a link switch to `casa-paraiso-redesign-20261104c/public`.

## Follow-ups

- [ ] Repoint the hPanel cron to `casa-paraiso-redesign-20261104d/artisan schedule:run`.
- [ ] Delete the ZIP and checksum in `.casa-deploy-20261104d/` once the release is settled.
- [ ] Push `redesign` and fast-forward `main` when ready.
- Future releases: create `public/storage` (linking to the release's `storage/app/public`) before running `casa:check-hosting`.
- Rollback: from the domain directory, `ln -s "$PWD/casa-paraiso-redesign-20261104c/public" public_html.new && mv -T public_html.new public_html`.
