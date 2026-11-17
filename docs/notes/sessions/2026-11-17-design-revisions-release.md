---
type: session-log
date: 2026-11-17
---

# Panel design revisions release

## Task

Put the defense panel's system revisions live on https://casaparaiso.site: the one-font/one-highlight typography, brand-only colors, booking success pop-up and toasts from [the 2026-11-15 session](2026-11-15-panel-design-revisions.md).

## What Changed

- Packaging first stopped because its Composer audit found 8 advisories published since the last release. With the user's approval, `composer update` patched `laravel/framework` 12.70.1 → 12.72.0, `symfony/*` 7.4.21 → 7.4.23 and `phpseclib/phpseclib` 3.0.57 → 3.0.58, with their patch-level dependencies. Only `composer.lock` changed (commit `73c9141`).
- Pushed `redesign` and fast-forwarded `main` to it; both are at `73c9141`.
- Packaged `casa-paraiso-hostinger-20261117-082908-a6704f.zip` and uploaded it with its checksum to the private `.casa-deploy-20261117/` folder. `RELEASE.json` is kept there as `RELEASE-20261117.json`.
- Extracted it as the sibling `casa-paraiso-redesign-20261117/`:
  - its `.env` is a mode-600 copy of the 20261104e release's (never displayed);
  - `storage/app/public` links to `casa-paraiso/storage/app/public`, and `public/storage` links to it;
  - config, route and view caches were built.
- Switched `public_html` atomically to `casa-paraiso-redesign-20261117/public`.
- Recorded the release in [Project Memory](../../PROJECT_MEMORY.md).

## Verification

- Local, before the patch: 668 Laravel tests and 106 JavaScript tests passed; browser check of the customer, reception and therapist pages at 375px (fonts, palm drawer, no sideways scroll), closing the 2026-11-15 follow-up.
- Local, after the patch: 668 Laravel tests, 106 JavaScript tests, `npm run build`, Docker `/up` 200, `git diff --check`, and a clean `composer audit`.
- Package: dependency audits and archive smoke checks passed.
- Host: `sha256sum -c` passed, no pending migrations, `casa:check-hosting` and `casa:audit-crud-integrity` passed.
- Live: `/`, `/login`, `/privacy-policy`, `/up` and the CSS bundle return 200; the bundle has the Cormorant Garamond rule, and the live sign-in page renders Cormorant Garamond titles over Manrope. `/.env` 403, `/composer.json` and unknown paths 404, `/admin/appointments` redirects guests to login, `http://www.` ends at the bare HTTPS URL with the query, uploaded photos load, and the new release wrote no log.
- Not checked live: signed-in pages and the booking pop-up (no live booking was made).

## Decisions

- Patch-level dependency updates only, within the existing version limits, rather than bypassing the audit.

## Follow-ups

- [ ] Repoint the hPanel cron to `casa-paraiso-redesign-20261117/artisan schedule:run` and remove the older job.
- [ ] Delete the ZIP and checksum in `.casa-deploy-20261117/` once settled.
- Rollback: from the domain directory, `ln -s "$PWD/casa-paraiso-redesign-20261104e/public" public_html.new && mv -T public_html.new public_html`, then repoint the cron.
