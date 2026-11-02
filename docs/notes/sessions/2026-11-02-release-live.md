---
type: session-log
date: 2026-11-02
---

# 20261102 release live

## Task

Deploy everything built since the 20261101 release (`fc8ec25`) to https://casaparaiso.site: operating hours, fully anonymous feedback, live updates, the week-view text fix and the attendance camera prompt.

## What Changed

- Fast-forwarded `main` to `redesign` on `origin`. Both are at `598de50`.
- Packaged `598de50` as `casa-paraiso-hostinger-20261102-015811-547f2f.zip` and uploaded it with its checksum to the private `.casa-deploy-20261102/` folder.
- Extracted it on Hostinger as the sibling `casa-paraiso-redesign-20261102/`:
  - its `.env` is a mode-600 copy of the 20261101 release's (never displayed), so the Gemini settings carry over;
  - its `storage/app/public` is a symlink to `casa-paraiso/storage/app/public`.
- Took a private dump of the live database into `.casa-deploy-20261102/pre-migrate-20261102.sql` (owner-only, 428 KB, 44 tables, complete).
- Ran the one pending additive migration, `2026_11_03_010000_create_operating_hour_rules_table`. Production had no closed dates, so no rules were copied.
- Switched `public_html` atomically to `casa-paraiso-redesign-20261102/public`.
- Recorded the release in [Project Memory](../../PROJECT_MEMORY.md).

## Verification

- Local: 637 Laravel tests and 106 JS tests passed, `npm run build` passed, and the composer and npm audits were clean. Docker `app`, `mariadb` and `mailpit` are running.
- Packaging: the archive smoke checks passed.
- Host, before the switch:
  - `sha256sum -c` passed.
  - `migrate:status` listed exactly the one expected migration, which ran.
  - `casa:audit-crud-integrity` and every `casa:check-hosting` check passed on MariaDB 12.3.2.
  - A private kernel render returned 200 for `/`, `/login`, `/privacy-policy` and `/up`, and 404 for an unknown path.
  - The old release kept serving 200 after the migration.
- Live, after the switch:
  - `/`, `/login`, `/privacy-policy`, `/up` and both build assets return 200.
  - `/.env`, `/.git/config` and the log path return 403. `/composer.json` and unknown paths return 404.
  - `http://www.` ends at the bare HTTPS URL, keeping the query.
  - The homepage shows 1:00 PM to 11:00 PM.
  - The new release has written no log, and a manual `schedule:run` returned "No scheduled commands are ready to run".

## Decisions

- No closed dates existed in production, so the Operating hours page starts empty; the owner adds closures there.

## Follow-ups

- [ ] Repoint the hPanel cron to `casa-paraiso-redesign-20261102/artisan schedule:run` and remove the 20261101 job.
- [ ] Check the attendance camera prompt on a phone as a therapist (allow, block, Retry).
- [x] Delete the uploaded ZIP and checksum in `.casa-deploy-20261102/` and the old `casa-paraiso-hostinger-20261026-044221-be8a29.zip`. Done with the owner's approval; `RELEASE.json` is kept beside the dump as `RELEASE-20261102.json`, and the domain directory has no ZIPs left.
- [ ] Revoke the `casa-paraiso-deploy-20261026` and `casa-paraiso-deploy-20261101` SSH keys in hPanel once host work is finished.
- Rollback if needed: from the domain directory, `ln -s "$PWD/casa-paraiso-redesign-20261101/public" public_html.new && mv -T public_html.new public_html`, then repoint the cron.
