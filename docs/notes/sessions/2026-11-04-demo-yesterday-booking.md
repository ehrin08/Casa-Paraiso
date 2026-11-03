---
type: session-log
date: 2026-11-04
---

# Demo booking from yesterday

## Task

Insert a confirmed booking at 3:00 PM yesterday so a finished (completion) workflow can be demonstrated, first locally and then on the live site.

## What Changed

- Local MariaDB only: added appointment `APT-DEMO-YESTERDAY` (id 17), copied from `APT-DEMO-CONFIRMED`: Returning Customer, TETHYS FLOW, Demo Staff, 2026-11-03 15:00–16:00 Asia/Manila, status `confirmed`, plus one `confirmed` status-log row.
- Live (https://casaparaiso.site, release `casa-paraiso-redesign-20261104b`, via tinker over SSH): added `APT-0104` (id 238), numbered by `AppointmentNumberGenerator`: the `Walk-in (imported history)` profile, TETHYS FLOW, Christine Joy Borce, 2026-11-03 15:00–16:00 Manila, status `confirmed`, `internal_notes` "Demo booking for completion walkthrough", plus one status-log row, in one transaction. Live appointments went from 103 to 104.
- Inserted with `updateOrCreate` on the appointment number, so rerunning does not duplicate it. No code or schema changes.

## Verification

- Checked Demo Staff had no overlapping confirmed booking before inserting (0 clashes).
- Read the record back: status `confirmed`, one status log.
- Live: no overlapping confirmed booking for the therapist; a second run found the marked record and created nothing (total stayed 104). Not checked in the browser.

## Decisions

- Kept it `confirmed` (not `completed`) so the demo can mark it completed live; `confirmed → completed` is an allowed transition.

- On live, the walk-in profile was used instead of the one real customer account, so no real customer's history, rewards or notifications change.
- Completing it in the demo creates a real ₱649 transaction and a commission for that therapist, which will show in live reports.

## Follow-ups

- [ ] After the demo, decide whether to keep `APT-0104` and its payment in live reports or cancel it.
