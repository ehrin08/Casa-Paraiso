---
type: session-log
date: 2026-10-30
---

# Task tracker sheet

## Task

List the pending tasks and make the owner's Google Sheet the task tracker.

## What Changed

- Added a `Tasks` tab to the "Casa Paraiso - Therapist Roster" Google Sheet through Claude in Chrome. The `Therapists` tab was not changed.
- Columns: ID, Task, Details, Status, Priority, Open decision, Source, Updated, Notes. The header row is bold and frozen, and text wraps.
- Status (D2:D100) has a drop-down with Not started, In progress, Blocked and Done. Priority (E2:E100) has High, Medium and Low.
- Styled in the brand palette from [BRAND_UI_GUIDE.md](../../BRAND_UI_GUIDE.md): a Casa Palm header with Casa Paper text, alternating Casa Paper and Casa Sand rows to row 50, Casa Border borders, and a Casa Palm sheet tab. The drop-down chips are coloured: Status uses grey, yellow, red and green; Priority uses red, orange and green.
- Renamed the spreadsheet to "Casa Paraiso Tracker". The `Therapists` and `Tasks` tabs stay in it.
- Rows T1–T5 come from [PLANNED_TASKS.md](../../PLANNED_TASKS.md). T6 is completing the therapist roster, and T7 is committing the roster session note.
- [PLANNED_TASKS.md](../../PLANNED_TASKS.md) now says the sheet holds live status and the file keeps the specifications.

## Verification

- Checked in Chrome: 7 task rows under the header, drop-downs work, the styling shows, and the `Therapists` tab still has its 5 rows.
- `git diff --check` on the changed docs.

## Decisions

- The tracker lives in the existing roster spreadsheet, as the owner chose, rather than in a new file.
- The priorities are suggestions; the owner can change them in the sheet.

## Follow-ups

- Update the sheet's Status and Updated columns as each task progresses, and move `PLANNED_TASKS.md` to `archive/` when all five tasks are done.
