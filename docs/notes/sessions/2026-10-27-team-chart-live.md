---
type: session-log
date: 2026-10-27
---

# Team chart and sticky top bar live

## Task

Deploy the latest `redesign` work to https://casaparaiso.site: the sticky workspace top bar (`5e3128a`) and the staff-account team chart (`d3975a9`).

## What Changed

- Packaged `d3975a9` as `casa-paraiso-hostinger-20261027-044829-ba962c.zip`. The ZIP contains the new migration, `TeamProfileController` and `UserPhotoService`.
- Extracted it on Hostinger as the sibling release `casa-paraiso-redesign-20261027/`. It uses a copied `.env` (mode 600, never displayed), and its `storage/app/public` is a symlink to `casa-paraiso/storage/app/public`.
- Took a private dump of the live database before migrating: 37 tables, owner-only, kept in `.casa-deploy-20261027/` outside `public_html`. The temporary credentials file was removed.
- Ran `migrate --force`. Only `2026_10_27_090000_add_public_profile_to_users_table` was pending, and it applied in 40 ms.
- Switched `public_html` atomically to `casa-paraiso-redesign-20261027/public`. The `casa-paraiso/` and `casa-paraiso-redesign-20261026/` releases are untouched.
- Removed the uploaded ZIP, checksum and extract folder from the host.
- Updated [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md).

## Verification

- Local: Docker `app`, `mariadb` and `mailpit` are running, and `/` and `/up` return 200. The 511-test suite, the JS tests and the asset build passed on `d3975a9` earlier the same day and were reused.
- Packaging:
  - Docker's bridge network times out reaching `packagist.org`, while the host and `--network host` containers connect. `composer audit --locked` found no advisories on the host.
  - The npm audit found 0 vulnerabilities, and the build passed.
  - `deploy/build-hostinger.php` ran in a one-off `--network host` container from the app image, with the same mounts. Its audit and archive smoke checks passed.
- Host, before the switch:
  - `sha256sum -c` passed.
  - The caches built, and `casa:audit-crud-integrity` and `casa:check-hosting` passed against MariaDB 12.3.2.
  - A private kernel render returned 200 for `/`, `/login`, `/privacy-policy` and `/up`, and 404 for an unknown path. No assets were missing.
- Live, after the switch:
  - `/`, `/login`, `/privacy-policy` and `/up` return 200, and the new build assets return 200.
  - HTTP and `www` return a 301 to the bare HTTPS URL, keeping the path and query.
  - `/.env`, `/.git/config`, `/.htaccess` and the log path return 403. `/composer.json` and unknown paths return 404.
  - The browser shows no console errors, and the new release has written no log.

## Decisions

- Keep the sibling-release pattern, so the 2026-10-26 release stays one link switch away. The migration only adds nullable columns, so that rollback needs no schema change.
- Work around the Docker bridge's Packagist timeout with host networking for this build, rather than skipping the audit.

## Follow-ups

- [ ] Repoint the hPanel cron to `casa-paraiso-redesign-20261027/artisan schedule:run`, then remove the 20261026 job.
- [ ] Admins publish staff cards on the Team page. The homepage `#team` section stays hidden until someone is published.
- [ ] Rollback if needed: from the domain directory, `ln -s "$PWD/casa-paraiso-redesign-20261026/public" public_html.new && mv -T public_html.new public_html`.
- [ ] Look into the Docker bridge network failing to reach `packagist.org`. Until it is fixed, `scripts/casa-hostinger.ps1 package` fails at its audit step.
- [ ] The previous release logged one Google sign-in `InvalidStateException` and slow-request warnings at 12:14–12:30 (Asia/Manila), before this deploy. Watch whether they recur.
