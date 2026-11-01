---
type: session-log
date: 2026-11-01
---

# Subtle animations (Task 6 / T8)

## Task

Add subtle, on-brand motion to the landing page and every workspace without changing behavior. See Task 6 in [Planned Tasks](../../PLANNED_TASKS.md).

## What Changed

- `resources/css/app.css`:
  - Added the `--ease-casa` theme easing and the `:root` tokens `--casa-motion-fast` / `--casa-motion` / `--casa-motion-slow` (160 / 200 / 220ms), `--casa-rise` and `--casa-stagger`.
  - Added two shared keyframes, `casa-enter` and `casa-fade-in`.
  - `[data-page-content]` fades in. It animates opacity only and has no fill, so fixed dialogs inside `<main>` are unaffected.
  - Stat-strip, appointment-stat and metric-card items rise in with a 30ms stagger, capped at the fourth item.
  - Buttons and section tabs move down 1px on press, and table rows get a hover transition.
  - The drawer keyframes now use the tokens.
- `resources/css/landing.css`:
  - The hero copy rises in on load.
  - Section headings, treatment cards, add-ons, steps and the how-it-works image reveal on scroll. The reveal uses `animation-timeline: view()` inside `@supports` and `prefers-reduced-motion: no-preference`, and animates `translate` so the card hover lift still works.
- Alpine timing:
  - `modal`: enter 200ms and leave 160ms with `ease-casa` and a 0.5rem rise. The old 300ms scale is gone.
  - `dropdown`: leave goes from 75ms to 160ms.
  - `toast-stack`: explicit 200 / 160ms transitions with a small rise.
- Docs: the [Brand UI Guide](../../BRAND_UI_GUIDE.md#accessibility-and-motion) motion rules, [Project Memory](../../PROJECT_MEMORY.md), [Planned Tasks](../../PLANNED_TASKS.md).

## Verification

- `npm run build` succeeded, and the built CSS contains `ease-casa`, `duration-160`, `casa-enter` and `landing-reveal`. The chunk-size warning was already there before this work.
- Laravel suite: all 595 tests pass when run in three parts: Unit plus Feature A–L (362), Feature M–Z (209), and Feature/Auth (24).
  - Two single-process `php artisan test` runs crashed. PHP was killed with signal 11 right after `LandingTeamChartTest`. No assertion failed.
  - The tests right after that point (`MembershipTest`, `ModalConfirmationTest`, `ModalInfrastructureTest`) pass on their own.
  - The same single run passed (595) earlier today, before the CSS-only changes. This looks like a PHP process problem in the container, not a test failure. It is not investigated yet.
- `scripts/casa-docker.ps1 start` / `status` show the stack up, and `/login` returns 200.
- Ran `php artisan db:seed` on the local database with the user's approval. It is idempotent and added the demo accounts.
- Browser pane, signed in as the seeded admin:
  - The landing hero entry and the scroll reveal end fully visible.
  - Dashboard metric cards stagger 0 / 30 / 60 / 90ms and end at opacity 1.
  - The account dropdown and the delete-confirm modal open and close. The modal was cancelled; nothing was deleted.
  - At desktop and at 375px there is no horizontal scroll.
- Reduced motion can't be emulated in the pane. I checked by reading the CSS: the existing global override zeroes every animation and transition duration, and the scroll reveal is also gated on `no-preference`.

## Decisions

- Presentation only. Forms, routes, Turbo and Alpine behavior are unchanged.
- The page fade has no `transform` and no fill. A transformed or stacking `<main>` would capture fixed dialogs such as the auto-opening feedback prompt.
- Cards are not animated individually, so they don't fade twice inside the page fade. Status chips stay static.

## Follow-ups

- Investigate the PHP signal-11 crash in long single-process test runs (it may be the container's PHP extensions or memory).
- Check the customer workspace visually as the seeded customer.
