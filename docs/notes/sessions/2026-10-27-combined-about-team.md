---
type: session-log
date: 2026-10-27
---

# About and team combined into one section

## Task

The user wanted the homepage "About Casa Paraiso" and "Meet the team" sections merged into one. The team chart should show only portraits, sit beside the About text, and open a pop-up with each person's details when a portrait is selected.

## What Changed

- **`welcome.blade.php`:**
  - One `#about` section now holds the history on the left and the `data-organization-chart` tiers on the right.
  - The separate `#team` section and its "Our team" nav link are removed.
  - Without a published team, the section keeps the towel photo.
- **`landing-org-node` component:**
  - It is now a portrait-only button (`aria-label` "About {name}, {title}").
  - The button opens an `x-modal` named `team-member-{id}` showing the larger portrait, name, title pill and bio.
- **`landing.css`:**
  - The card and `.landing-team*` styles are replaced by a sand chart panel, sized portrait buttons and `.landing-org-dialog` styles.
  - Landing tokens also apply to `.landing-org-dialog-panel`, because modals teleport to `<body>`.
  - The scroll fade now runs on the list items, so the hover lift still works.
- **Tests:** `LandingTeamChartTest` now asserts `#about`, the absence of `#team`, one dialog per member and the portrait-only chart. A new test covers the photo fallback when no team is published.
- **Docs:** [PROJECT_MEMORY](../../PROJECT_MEMORY.md), [SCREEN_FLOW](../../SCREEN_FLOW.md) and [BRAND_UI_GUIDE](../../BRAND_UI_GUIDE.md).

## Verification

- Targeted `--filter='Landing|AdminSettings'`: 25 passed.
- Full `php artisan test`: 512 passed.
- `npm run build` succeeded, with only the existing chunk-size warning.
- Browser checks against the local app at 1280px and 375px:
  - The text sits left and the chart right, stacking on mobile with no horizontal overflow.
  - Selecting a portrait opens the dialog, and Esc closes it.
  - Only one published member exists locally, so a full multi-tier chart was not viewed with real data.

## Decisions

- Small tier labels (Owner, Leadership, Our team) stay on the chart so it still reads as an org chart. Names appear only in the dialog.
- The existing `x-modal`/`casaModal` handles focus trapping, Esc and the backdrop, so no new JavaScript was added.

## Follow-ups

- Once more staff are published, check the chart with several leadership and team members.
- Deploy to casaparaiso.site with the usual release steps.
