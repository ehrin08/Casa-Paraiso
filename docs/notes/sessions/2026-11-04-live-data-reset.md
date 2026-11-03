---
type: session-log
date: 2026-11-04
---

# Live data reset and timed service-history import

## Task

Reset the test data on https://casaparaiso.site, keep the real therapists, and import the real service history with realistic times.

## What Changed

- Added `casa:purge-test-accounts` (`app/Console/Commands/PurgeTestAccounts.php`) with `PurgeTestAccountsCommandTest`. It is dry-run by default and keeps admins, super admins and `--keep` users. It refuses while operational data remains.
- Made `output/imports/2026-09-29_2026-10-13/services-rendered-timed.csv` (git-ignored) from `services-rendered.csv` by filling the blank `time` column. Starts fall on 30-minute marks from 1:00 PM, with more weight on 5–9 PM. No therapist's visits overlap, and every one ends by 11:00 PM. Each therapist-day uses a fixed seed, so the output is reproducible.
- Committed `8b2dc28` locally (not pushed). Packaged it as `casa-paraiso-hostinger-20261103-184738-c4eda9.zip`, uploaded it to the private `.casa-deploy-20261104b/` folder, and extracted it as the sibling `casa-paraiso-redesign-20261104b/`:
  - its `.env` is a mode-600 copy of the 20261104 release's (never displayed);
  - its `storage/app/public` links to `casa-paraiso/storage/app/public`;
  - config, routes and views are cached.
- There were no pending migrations. Took a private dump, `.casa-deploy-20261104b/pre-reset-20261104.sql` (owner-only, 582 KB, 45 tables, complete). The credentials file was temporary and removed.
- Switched `public_html` to `casa-paraiso-redesign-20261104b/public`.
- On live, after the user approved the dry-run list:
  - `casa:reset-operational-data --apply` removed 130 appointments, 113 transactions (103 of them the earlier untimed import), 111 commissions and 4 feedback entries.
  - `casa:purge-test-accounts --keep` (the five real therapists) deleted 11 accounts (4 test therapists, 7 customers) and 14 customer profiles, including 6 guests, plus 4 promotion suggestions.
  - The timed CSV was imported through `TransactionCsvImport` as the super admin, with commissions paid on 2026-10-13 12:00 Manila (the same date as the first import). The uploaded CSV and the one-off script were removed afterwards.
- Recorded the command and the live state in [Project Memory](../../PROJECT_MEMORY.md).

## Verification

- Local:
  - targeted tests passed (18), then the full suite (655 tests);
  - `npm run build` passed and the Docker stack started healthy;
  - the release package passed its dependency audits and archive smoke checks.
- `output/imports/rehearse.php` on the `testing` database created 103 rows on the first run and skipped all 103 on the second, with the same totals as on live.
- Host:
  - `sha256sum -c`, `casa:check-hosting` and `migrate:status` passed;
  - after the import, `casa:audit-crud-integrity` passed.
- Live:
  - 103 appointments, transactions and paid commissions;
  - revenue ₱57,437 and paid commission ₱12,632.36;
  - visits run from 1:00 PM to 11:00 PM at the latest;
  - users are 1 super admin and 5 therapists;
  - `/`, `/login` and `/up` return 200, `/.env` 403 and `/composer.json` 404.

## Decisions

- The user approved bulk deletion on the live database, overriding the `AGENTS.md` default for this task only, after a fresh dump and a reviewed dry run.
- The five real therapists were kept rather than re-created, so their schedules from 2026-10-30 remain.
- Names and emails are kept out of this note.

## Follow-ups

- [ ] Repoint the hPanel cron to `casa-paraiso-redesign-20261104b/artisan schedule:run` and remove the older jobs.
- [ ] Push `8b2dc28` to `origin` and fast-forward `main` when the user wants.
- [ ] Delete the uploaded ZIP and checksum in `.casa-deploy-20261104b/` once settled. Keep the dump and `RELEASE-20261104b.json`.
- [ ] Revoke the deploy SSH keys in hPanel once host work is finished.
- Rollback: switch `public_html` back to `casa-paraiso-redesign-20261104/public`. Restoring the data needs `pre-reset-20261104.sql` into a frozen database.
