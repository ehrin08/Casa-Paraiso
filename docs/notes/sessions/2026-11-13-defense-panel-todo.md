---
type: session-log
date: 2026-11-13
---

# Defense panel revisions recorded as to-do

## Task

Record the defense panel's revision list (Nov 2026) as open to-do items, without starting the work.

## What Changed

- Confirmed the list is held in [Defense Panel Revisions](../../manuscript/2026-11-10-defense-panel-revisions.md) with all five items unchecked: one main font plus one highlight font, consistent colors with AI-looking leftovers removed, "Booking" removed from the title, Chapter IV revised in full, and no tables split across pages.
- Kept its link in [Docs Home](../../HOME.md) under Manuscript.

## Verification

- `git diff --check` on the changed docs.

## Decisions

- The checklist stays the single to-do location; no work on the items has started.
- `docs/REVISIONS-TODO.md` is an untracked stub pointing to the checklist; it was deleted on 2026-11-15.

## Follow-ups

- App fonts and colors: the app currently loads Manrope and Cormorant Garamond (`resources/views/layouts/app.blade.php`, `guest.blade.php`); several pages use the editorial font for headings.
- Title change, Chapter IV revision and table page breaks are in the Google Doc manuscript.
