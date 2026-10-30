---
type: session-log
date: 2026-10-30
---

# Main merge and release live

## Task

Merge `redesign` into `main`, then deploy the result to https://casaparaiso.site.

## What Changed

- Fixed `Customer\AppointmentCalendarController`: it parsed the month with `createFromFormat('Y-m')`, which keeps today's day, so on the 29th–31st a shorter month overflowed into the next (Feb 2027 returned March). It now uses `!Y-m`, like the rest of the code (`3177a96`). The full suite caught this on the 30th.
- Updated `symfony/mime` from 7.4.19 to 7.4.22 for CVE-2026-45070, a medium email header injection advisory reported 2026-10-28 (`891bd20`). The release audit blocked packaging until it was fixed. Only that package changed in `composer.lock`.
- Fast-forwarded `main` to `redesign` and pushed both. `main`, `origin/main` and `origin/redesign` all point to the same commit.
- Packaged `891bd20` as `casa-paraiso-hostinger-20261030-071401-8f76b2.zip`. The Docker bridge reached Packagist this time, so the standard `scripts/casa-hostinger.ps1 package` worked without the host-network workaround.
- Extracted it on Hostinger as the sibling `casa-paraiso-redesign-20261030/`:
  - It has a copied `.env` (mode 600, never displayed).
  - Its `storage/app/public` is a symlink to `casa-paraiso/storage/app/public`.
- No migrations were pending, so no database dump or migrate was needed.
- Switched `public_html` atomically to the new `public` directory. The upload folder `.casa-deploy-20261030/` was removed. Older releases are untouched.
- Updated [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md).

## Verification

- Local:
  - `php artisan test` passed 512 tests after each fix.
  - The 92 JS tests passed.
  - `npm run build` passed.
  - Docker `app`, `mariadb` and `mailpit` are running.
- Packaging: the composer and npm audits were clean after the update. The archive smoke checks passed.
- Host, before the switch:
  - `sha256sum -c` passed.
  - `migrate:status` showed nothing pending.
  - `storage:link` and the config, route and view caches built.
  - `casa:audit-crud-integrity` and `casa:check-hosting` passed on MariaDB 12.3.2.
  - A private kernel render returned 200 for `/`, `/login`, `/privacy-policy` and `/up`, and 404 for an unknown path.
- Live, after the switch:
  - `/`, `/login`, `/privacy-policy`, `/up` and both build assets return 200.
  - HTTP and `www` return a 301 to the bare HTTPS URL, keeping the query.
  - `/.env`, `/.git/config`, `/.htaccess` and the log path return 403. `/composer.json` and unknown paths return 404.
  - The browser shows the combined About/team section with leaf portraits and the navbar hiding on scroll. There are no console errors, and the new release has written no log.

## Decisions

- Fix the date-dependent calendar bug and the dependency advisory in this task rather than skip the gates. Both are small and were blocking a verified release.

## Follow-ups

- [x] Repoint the hPanel cron to `casa-paraiso-redesign-20261030/artisan schedule:run`. The user asked for this through Claude in Chrome. A new every-minute Custom job was added, and its output was the normal "No scheduled commands are ready to run". The 20261027 job was then deleted, leaving only the new one.
- [ ] Rollback if needed: from the domain directory, `ln -s "$PWD/casa-paraiso-redesign-20261027/public" public_html.new && mv -T public_html.new public_html`.
- [ ] Old upload ZIP `casa-paraiso-hostinger-20261026-044221-be8a29.zip` still sits in the domain directory. Remove it when convenient.
