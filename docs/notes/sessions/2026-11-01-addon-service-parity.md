---
type: session-log
date: 2026-11-01
---

# Add-on admin matches Services

## Task

Make admin add-on editing look and work like the Services catalog. Scope chosen: same list layout, same create/edit form layout, and a detail page. No photos for add-ons.

## What Changed

- `routes/web.php`: add-ons resource now includes `show` (`->except('destroy')`, same as services).
- `App\Models\Addon::bookings()` counts appointment add-on lines by `addon_code`.
- `Admin\AddonController` follows `ServiceController`: index sorting (name, duration, price, status), active/inactive summary, `show`, saves redirect to the detail page, referer-aware toggle. The code is still generated once and kept when the name changes.
- Views: `admin/addons/index.blade.php` reuses the services table styles; `admin/addons/partials/form.blade.php` replaces `form.blade.php` with the two-column layout and a "Code is automatic" card; new `admin/addons/show.blade.php`; create/edit headers match services.
- `AdminAddonManagementTest` checks the redirects to the detail page and renders the show, edit, create, and sorted index pages.
- Updated [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md) and [SCREEN_FLOW.md](../../SCREEN_FLOW.md).

## Verification

- `AdminAddonManagementTest` and `AdminServiceManagementTest`: 11 passed.
- Full Laravel suite: 596 passed.
- `npm run build` succeeded (existing large-chunk warning only).
- Docker services up; `/up` returns 200 and `/admin/addons/1` redirects guests to login.
- Not checked signed in through the browser.

## Decisions

- No new CSS: add-on pages reuse the `services-*` classes from `resources/css/services.css`.
- Add-on codes never change on rename because `appointment_addons` and RFM add-on vouchers reference them.
- Duration still allows 0 minutes (step 15), unlike services.

## Follow-ups

- None.
