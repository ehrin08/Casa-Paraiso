---
type: session-log
date: 2026-11-04
---

# Service-history import release

## Task

Put the service-history CSV import (`41bebeb`) live on https://casaparaiso.site. See [Service-history CSV import](2026-11-04-service-history-import.md).

## What Changed

- Pushed `redesign` to `origin` and fast-forwarded `main` to it. Both are at `41bebeb`.
- Packaged `41bebeb` as `casa-paraiso-hostinger-20261103-181918-5d5b56.zip` and uploaded it with its checksum to the private `.casa-deploy-20261104/` folder.
- Extracted it on Hostinger as the sibling `casa-paraiso-redesign-20261104/`:
  - its `.env` is a mode-600 copy of the 20261102b release's (never displayed);
  - its `storage/app/public` is a symlink to `casa-paraiso/storage/app/public`;
  - `storage:link`, `config:cache`, `route:cache` and `view:cache` were run.
- Took a private dump of the live database into `.casa-deploy-20261104/pre-migrate-20261104.sql` (owner-only, 488 KB, 45 tables, complete). Credentials went through a temporary owner-only option file that was removed afterwards.
- Ran the one pending additive migration, `2026_11_04_010000_add_import_reference_to_transactions_table`.
- Switched `public_html` atomically to `casa-paraiso-redesign-20261104/public`.
- Recorded the release in [Project Memory](../../PROJECT_MEMORY.md).

## Verification

- Local: 650 Laravel tests passed, `npm run build` passed, and Docker started. The package's dependency audits and archive smoke checks passed.
- Host, before the switch:
  - `sha256sum -c` passed.
  - `migrate:status` listed only the expected migration, which ran.
  - `casa:audit-crud-integrity` and `casa:check-hosting` passed.
  - The five `admin.transactions.import.*` routes are registered.
- Live, after the switch:
  - `/`, `/login`, `/privacy-policy`, `/up` and the build assets return 200.
  - `/admin/transactions/import` redirects guests to sign in.
  - `/.env` returns 403. `/composer.json` and unknown paths return 404.
  - `http://www.` ends at the bare HTTPS URL, keeping the query.
  - The new release has written no log.
- The import was not run on live data in this session.

## Decisions

- This is a full sibling release, so rollback is a single link switch. The older release ignores the new nullable column.

## Follow-ups

- [ ] Repoint the hPanel cron to `casa-paraiso-redesign-20261104/artisan schedule:run` and remove the older jobs.
- [ ] Add an inactive Pedicure service (₱189) on the live Services page, then import `output/imports/2026-09-29_2026-10-13/services-rendered.csv` with the commission payout date.
- [ ] Delete the uploaded ZIP and checksum in `.casa-deploy-20261104/` once the release is settled. Keep the dump and `RELEASE-20261104.json`.
- [ ] Revoke the deploy SSH keys in hPanel once host work is finished.
- Rollback if needed: from the domain directory, `ln -s "$PWD/casa-paraiso-redesign-20261102b/public" public_html.new && mv -T public_html.new public_html`, then repoint the cron.
