---
type: session-log
date: 2026-10-27
---

# Staff-account team chart

## Task

The client wants the spa's people on the website with their photo, name and role. The user asked to remodel the org chart and build it from the staff user accounts instead of the hand-typed chart in Settings.

## What Changed

- **Data:** additive migration `2026_10_27_090000_add_public_profile_to_users_table` adds these `users` columns: `photo_path`, `public_title`, `public_bio`, `org_chart_tier` (owner, leadership, team), `org_chart_order` and `show_on_org_chart`. `User` gains `ORG_TIERS`, `publiclyListed()`/`teamMembers()` scopes, `roleLabel()`, `publicTitle()` and `initials()`.
- **Photos:** `UserPhotoService` stores a centre-cropped square WebP, at most 480px, at `user-photos/{uuid}.webp`. It follows the same transaction, lock and cleanup rules as `ServicePhotoService`. The new `x-user-avatar` component replaces initials in the top bar, My profile, User accounts, Therapists and the therapist detail panel.
- **Admin Team page:** `admin.team.index`, `admin.team.edit` and `admin.team.update` (`TeamProfileController`, `TeamProfileRequest`).
  - The list uses a stat strip, toolbar filters (search, role, website visibility), a table and compact pagination.
  - Card edits open in the shared form panel; `/admin/team/` was added to both panel prefix lists.
  - Turning on "Show on the website" requires a chart level. Customers return 404, and only the super admin can edit the super-admin card.
  - New navigation item "Team page" for admins. Links to it come from User accounts and from the therapist actions menu.
- **Staff self-service:** a "Public team card" section on My profile for non-customers (`profile.team-card.update`, `ProfileTeamCardRequest`) covers photo, title and bio. Visibility, level and order stay admin-only.
- **Settings:** the chart editor, `OrgChartPhotoService`, the `settings-chart-*` components, `settings-dialog.js` and its JS test are removed. Settings now shows a link card to the Team page and posts a plain PATCH. `application_settings.organization_chart` and the `org-chart/*.webp` files are kept untouched as a backup and are never rendered.
- **Landing page:**
  - The `/` route passes `OrganizationChart::tiers()`.
  - The `#team` section shows Owner → Leadership → Team rows of arched portrait cards with name, title pill and optional bio. The page queries only presentation columns.
  - An "Our team" nav anchor appears when anyone is published. About appears when there is history or a chart.
  - The unused IntersectionObserver/`data-org-animate` code was replaced by a CSS scroll-driven fade that runs only where supported.
- **Docs:** [PROJECT_MEMORY](../../PROJECT_MEMORY.md), [DATABASE_DESIGN](../../DATABASE_DESIGN.md), [BRAND_UI_GUIDE](../../BRAND_UI_GUIDE.md), [SCREEN_FLOW](../../SCREEN_FLOW.md) and [MVP_SCOPE](../../MVP_SCOPE.md) were updated.

## Verification

- Targeted run passed: `TeamProfileManagementTest`, `ProfileTeamCardTest`, `LandingTeamChartTest`, `AdminSettingsTest`, `LandingServiceCatalogTest`, `ServicePhotoTest` and `PanelFormRecoveryTest`, 59 tests.
- Full `php artisan test`: 511 passed.
- JS unit tests: 87 passed.
- `npm run build` succeeded, with only the existing chunk-size warning.
- `php artisan migrate` applied the new migration to the local database; it only adds columns.
- Browser: the homepage loads with no published accounts and no chart. Sample chart markup injected into the page rendered correctly at desktop and at 375px, with no horizontal overflow and team cards two per row. It was not checked with real published accounts, because the local database has no seeded staff.

## Decisions

- Any non-customer role can appear. Admins choose a level and an order per account rather than deriving them from the role.
- Staff manage their own photo, title and bio; admins control whether the card is shown and where.
- The old JSON chart is not migrated into accounts. Admins re-enter photos per account, and the JSON stays as a backup.

## Follow-ups

- Publish the real team on the Team page after deploying, since the live homepage chart is empty until admins turn cards on. Deploying needs `php artisan migrate`.
- Check the Team page and a real published chart in a browser once staff accounts exist locally.
