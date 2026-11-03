---
type: session-log
date: 2026-11-04
---

# Live data reset preparation

## Task

Reset the test data on https://casaparaiso.site, keep the real therapists, and import the real service history with realistic times.

## What Changed

- Added `casa:purge-test-accounts` (`app/Console/Commands/PurgeTestAccounts.php`) with `PurgeTestAccountsCommandTest`. It is dry-run by default and keeps admins, super admins and `--keep` users. It refuses while operational data remains.
- Made `output/imports/2026-09-29_2026-10-13/services-rendered-timed.csv` (git-ignored) from `services-rendered.csv` by filling the blank `time` column. Starts fall on 30-minute marks from 1:00 PM, with more weight on 5–9 PM. No therapist's visits overlap, and every one ends by 11:00 PM. Each therapist-day uses a fixed seed, so the output is reproducible.
- Recorded the command in [Project Memory](../../PROJECT_MEMORY.md).

## Verification

- The targeted tests passed: `PurgeTestAccountsCommandTest`, `ResetOperationalDataCommandTest` and `TransactionCsvImportTest` (18 tests).
- The full suite passed (655 tests), `npm run build` passed, and the Docker stack started healthy.
- The timed CSV was checked: 103 rows, all within hours, no overlaps per therapist.
- `output/imports/rehearse.php` on the `testing` database with the timed CSV:
  - created 103 rows on the first run and skipped all 103 on the second;
  - revenue ₱57,437 and paid commission ₱12,632.36, matching the earlier rehearsal;
  - the latest visit ends at 11:00 PM.
- Nothing has run on the live site yet.

## Decisions

- The user chose to remove activity data and test accounts, and keep admins. The five real therapists from the Tracker sheet were added to live on 2026-10-30, so they are kept with `--keep` instead of being re-created.
- The user approved bulk deletion on the live database, overriding the default rule in `AGENTS.md` for this task only. A fresh private dump comes first.

## Follow-ups

- [ ] Get the Hostinger SSH username, then release this commit.
- [ ] On live: take the dump, add the inactive Pedicure service, run both commands as dry runs and review them, then apply.
- [ ] Import `services-rendered-timed.csv` with the commission payout date.
