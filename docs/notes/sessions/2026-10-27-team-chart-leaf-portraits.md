---
type: session-log
date: 2026-10-27
---

# Team chart leaf portraits

## Task

The user felt the arched portraits on the homepage team chart looked off. They asked for shape samples and chose the leaf shape: a square with two opposite corners strongly rounded.

## What Changed

- **`resources/css/landing.css`:**
  - `.landing-org-portrait` is now 1:1 with `border-radius: 42% 10%`. It was an arched 4:5 shape.
  - `.landing-org-node` uses the same radius, so the shadow and hover lift follow the shape.
  - The image `object-position` moved to `center 25%` so faces stay in the square crop.
  - `.landing-org-dialog-portrait` now has a fixed 11rem width, capped at 100%.
- **Docs:** the team chart paragraph in [BRAND_UI_GUIDE](../../BRAND_UI_GUIDE.md).

## Verification

- `npm run build` succeeded through the Docker wrapper.
- Browser pane, desktop: the chart portrait is 80×80 with the leaf radius. The dialog portrait is 176×176 with the leaf radius.
- Browser pane, 375px mobile: the portrait is 72×72 and the page has no horizontal overflow.
- Full `php artisan test`: 512 passed (5,659 assertions), run once nothing else was using the `testing` database. Earlier runs failed with schema errors because another session was running the suite in the same app container at the same time. Concurrent `RefreshDatabase` runs drop each other's tables in the shared `testing` database.
- `git diff --check`.

## Decisions

- The user compared six samples: arch (the old shape), soft square, rounded portrait, circle, leaf, and framed soft square. They chose the leaf.
- Tier widths are unchanged. The chart is shorter because the portraits are now square.

## Follow-ups

- None. Avoid running two full test suites at once against the shared `testing` database.
