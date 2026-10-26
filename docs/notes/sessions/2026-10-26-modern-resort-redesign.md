---
type: session-log
date: 2026-10-26
---

# Modern Resort redesign, first pass

## Task

Plan and start a visual redesign on the local `redesign` branch only, keeping the same concept and brand. The user chose the Modern Resort direction, full scope in phases, the same palette with refined use, and the same Cormorant Garamond and Manrope fonts.

## What Changed

- [REDESIGN_MODERN_RESORT.md](../../REDESIGN_MODERN_RESORT.md) records the direction, tokens, component rules and phase checklist. It is linked from [HOME.md](../../HOME.md).
- `resources/css/app.css` `@theme` adds radius tokens (card 20px, panel 16px, control 12px, pill), a soft shadow, a glass surface, a palm tint and semantic status colors. Shared buttons are now sentence-case pills; inputs, cards, tables, the stat strip, pagination, the mobile dock and the panel backdrop use the tokens.
- The sidebar and mobile drawer (`layouts/navigation.blade.php`) are a light frosted surface (`casa-sidebar-panel`) with a pale palm active pill. The page header and mobile top bar use `casa-glass-bar`.
- Components: `status-badge` and `toast-stack` use the status tokens; `modal` has a lighter blurred backdrop; `metric-card` drops its side strip; `dropdown` uses 20px corners.
- `landing.css`: frosted header, larger hero with a rounded arched frame, pill facts strip, 20px treatment, add-on and contact cards with soft shadows, pill buttons, and palette tokens in place of local hex.
- Every page stylesheet went through a rule-based shape pass. Cards and panels moved to the 20/16px tokens with the soft shadow, controls to 12px, and button overrides to pills. Calendar events, chart bars, tabs, dots and badges were skipped. Exact palette and status hex values became tokens.
- [BRAND_UI_GUIDE.md](../../BRAND_UI_GUIDE.md) shape rules and [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md) describe the redesign branch.

## Verification

- `npm run build` succeeded through the Docker wrapper.
- Homepage checked in the browser at desktop and 375px: no horizontal overflow, readable frosted header, pill controls. The login page renders the new buttons.
- The full Laravel suite had 482 of 483 passing. The modal test still expected the old `backdrop-blur-sm` hook; it now checks `backdrop-blur-md` and passes.
- Local sign-in had been failing because a stopped Cloudflare preview left secure-only session cookies and a tunnel `APP_URL`. `scripts/casa-preview.ps1 stop` restored the four journaled settings. The local app is `http://localhost:18001`.
- Signed-in review: a temporary helper rendered 33 GET pages as the existing local super-admin and a customer account. It used an in-memory session and a rolled-back transaction. The therapist account redirects because its invitation is pending, and there is no local receptionist account. The pages were reviewed in the browser pane at desktop width and 375px. None overflowed sideways, and the light sidebar, mobile drawer, cards, pills and status badges render consistently. The helper and snapshots were deleted afterwards.
- Fixes from the review: the customer dock labels are centered with tighter wrapping, and page titles now have explicit sizes. Operational titles are 24–30px Manrope; customer editorial titles are larger Cormorant Garamond. A `text-4xl` inherit rule had shrunk the customer titles to 16px.

### Branch verification and consistency pass

- JavaScript: `node --test tests/js/*.test.mjs` passed 78 of 78. A bare directory argument is not supported by this Node version.
- The scans found no raw hex in page CSS and no `app.css` hex outside `@theme`. Docs links resolve, every doc is linked from HOME, documented tokens all exist, and `git diff --check main..redesign` is clean.
- Fixed in this pass:
  - Raw Tailwind `red-*` classes in nine views (error notices, the calendar error dot, the delete-account section) now use the danger tokens.
  - Attendance status and staff labels are pills.
  - The schedule view toggle is a pill, and the schedule mobile day cards use the panel radius.
  - The three accent `casa-dark-panel` cards use 20px corners.
  - The service photo preview uses 16px corners.
- Intentional exceptions kept: small radii on calendar events, chart bars, meters, focus outlines and table-header corners; the deep palm `casa-dark-panel` accents; the skip-link `rounded-lg`.

### Navigation redesign

- **Top bar and drawer:** the user chose a ☰ overlay drawer at every width, a deep palm drawer, an avatar account menu, a notification bell, fewer destinations, no drawer extras, and a logo plus View site link to the public homepage.
- **Destinations:** admin goes from 16 nav and account links to 8 drawer links. Commissions joins Payments, and Feedback and Customer rewards join Insights (Reports), through a shared section-tab row. The therapist's My commissions joins Payments.
- **Code:** `resources/js/sidebar.js` is replaced by `resources/js/workspace-drawer.js`. The collapse rail, tooltips and pre-render preference script are removed. `x-dropdown` now closes on Escape, returns focus and exposes `aria-expanded`.
- **Tests:** `RoleWorkspaceTest` is updated for the new structure and covers the merged-page tabs and active links, the home links, bell visibility per role, and the account menu contents. The new `tests/js/workspace-drawer.test.mjs` covers the drawer store.

## Decisions

- Work stays on `redesign`. `main` is untouched, and no Hostinger package is built from this branch.
- Existing `--color-casa-*` names and class hooks are kept, so tests and page markup are unaffected.
- Page-only one-off shades (for example therapist calendar colors) stay until each workspace gets its signed-in review.

## Follow-ups

- [x] Review the admin and customer workspaces visually.
- [ ] Review the therapist and reception workspaces once those local accounts can sign in.
- [x] Booking wizard and appointment dialogs polished (`appointment-form.css`, `appointment-completion.css`). The changes cover the pill stepper, lifted treatment and add-on cards with a selected check badge, a paper calendar with palm-tint open days, pill time slots, token error banners, paper review cards, a frosted footer, glass dialog bars, a pill close button, and paper detail panels in the completion dialog. Reviewed through rolled-back snapshots with sample calendar data injected into Alpine state (display only) at desktop and 375px. Confirmation and Finish service dialogs were checked open. The snapshots were deleted afterwards.
- [ ] Live calendar feeds and the in-dialog booking panel still need a real signed-in browser session.
- [x] Converted the remaining page-only hex shades (about 128 values across 16 stylesheets) to status, neutral and new violet/rose categorical tokens, one commit per workspace. Text and tint pairs stay at roughly 7:1 contrast or better. Reviewed Users, Reports, Therapists and Services through rolled-back snapshots, which were deleted afterwards. Calendar event tones need live feeds and were checked by contrast only.
