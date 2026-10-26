---
type: session-log
date: 2026-10-26
---

# Redesign live on casaparaiso.site

## Task

Make the `redesign` branch the live site while keeping the stable `main` version available as a rollback.

## What Changed

- On the user's decision, `redesign` was packaged (`casa-paraiso-hostinger-20261026-131136-b8a0c9.zip`) and extracted on Hostinger as the sibling release `casa-paraiso-redesign-20261026/`.
- The new release uses a copy of the live `.env` (mode 600, never displayed). Its `storage/app/public` is a symlink to `casa-paraiso/storage/app/public`, so both releases share uploads.
- With the user's explicit approval, `public_html` was switched atomically to `casa-paraiso-redesign-20261026/public`.
- The `main` release in `casa-paraiso/` was not touched. Git `main` was not merged or changed.
- Temporary host copies (ZIP, checksum, extract folder, render helper) were removed.
- [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md) and the update bullet in [HOSTINGER_DEPLOYMENT.md](../../HOSTINGER_DEPLOYMENT.md) describe the sibling-release switch, shared uploads and rollback.

## Verification

- Local gate on `redesign`: 498 Laravel tests (5,544 assertions), asset build, and Docker startup (`/` and `/up` return 200).
- The packager's archive smoke checks passed, and the SHA-256 matched on the host.
- On the host before the switch: `migrate:status` showed nothing pending, and the caches built. `casa:audit-crud-integrity` and `casa:check-hosting` passed. A private render returned 200 for `/`, `/login`, `/privacy-policy` and `/up`, 404 for unknown paths, and no missing assets.
- Live after the switch:
  - `/`, `/login`, `/privacy-policy` and `/up` return 200.
  - HTTP and `www` return a 301 to the bare HTTPS domain, keeping the path and query.
  - `/.env`, `/.git/config`, `/.htaccess` and the log path return 403. `/composer.json` and unknown paths return 404.
  - Assets return 200, and the browser shows no console errors on desktop or mobile.
  - No application log was written.

## Decisions

- Use a sibling release and a link switch, not a replacement, so `main` stays one command away. The redesign has no migration, config, route or dependency changes, so both releases work with the live database.
- Share uploads through a symlink, so a rollback loses no photos.

## Follow-ups

- [ ] Point the hPanel cron at `casa-paraiso-redesign-20261026/artisan schedule:run`. The cron is not editable over SSH. Until then the old release's scheduler keeps running the same tasks against the same database.
- [ ] Rollback if needed: `ln -s "$PWD/casa-paraiso/public" public_html.new && mv -T public_html.new public_html`, run from the domain directory, then revert the cron.
- [ ] Signed-in workspace check of the redesign on the live site.
