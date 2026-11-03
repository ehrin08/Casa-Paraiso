---
type: session-log
date: 2026-11-04
---

# Service-history CSV import

## Task

Get the Sep 29 – Oct 13, 2026 payroll sheets (five therapists and one receptionist) ready for the system. The user chose to add a CSV import to the app and to prepare a CSV from the sheets. Daily rates, overtime, cash advances and the receptionist's pay stay in a reference CSV only, because the app has no payroll tables. Highlighted rows on the sheets have no meaning.

## What Changed

- New admin and super-admin page at Payments → Import CSV. The flow is upload, a read-only preview (per-therapist totals and row errors), then confirm. Routes are `admin.transactions.import.*`, served by `Admin\TransactionImportController` and `App\Services\TransactionCsvImport`.
- Each row becomes a completed appointment, a paid transaction and a therapist commission. A blank commission uses 22%; a given one keeps its own rate. An optional payout date marks the commissions paid.
- Additive migration `2026_11_04_010000_add_import_reference_to_transactions_table` adds the unique nullable `transactions.import_reference`, so importing the same file again skips rows already loaded.
- Rows without a customer share one guest profile, `IMPORT-WALKIN`. RFM scoring, the Reports RFM table and reward generation leave it out.
- `TransactionCsvImportTest` covers access, the template, the preview, writes, re-imports, row and file errors, ambiguous therapists, the token check and the RFM exclusion.
- The prepared files are in git-ignored `output/imports/2026-09-29_2026-10-13/`:
  - `services-rendered.csv` holds 103 rows with references `PR20260929-<NICKNAME>-NNN`, payment method Cash and the commission copied from each sheet.
  - `payroll-reference.csv` holds the per-person payroll summary.
  - `output/imports/parse-check.php` is a read-only parse check, and `rehearse.php` is a testing-database rehearsal that is rolled back.
- `/output/imports/` was added to `.gitignore`, and `docs/DATABASE_DESIGN.md` and `docs/PROJECT_MEMORY.md` were updated.

## Verification

- Each therapist's rows add up to the commission total on their sheet (3359.84, 3145.52, 1899.70, 2207.04 and 2020.26). Every payroll total income matches its sheet, and Daisy's ₱4,507 is the sheet's rounding of 4,507.04.
- The rehearsal ran on the `testing` database with stand-in therapist accounts and a Pedicure service, then rolled back. It imported 103 rows with ₱57,437 revenue and ₱12,632.36 paid commission, and the latest visit ended at 7:30 PM. A second import created 0 rows and skipped 103.
- A read-only parse against the local database matched every service except Pedicure. It found none of the five therapists, because they exist only on the live site.
- The new migration was applied to the local database; it adds a nullable column only.
- Targeted tests passed: 63 tests and 519 assertions across the import, commission, payments, reports, rewards and membership tests.
- Full Laravel suite: 650 tests passed (6,554 assertions). `npm run build` passed, with the chunk-size warning that was already there. Docker startup through the wrapper passed, and `/login` returned 200. `git diff --check` was clean.

## Decisions

- The upload is kept on the private `local` disk under a session token, so preview and confirm read the same file. Any row error blocks the whole file.
- Imported visits skip booking guards, pricing, notifications and rewards, because they are history.
- Therapist names use the sheet nicknames, which match the live accounts by first name or by the `<nickname>.therapist@` email.
- Names, pay amounts per person and file contents stay out of this note.

## Follow-ups

- On the live site, add an inactive Pedicure service (₱189) before importing, or the one Pedicure row is rejected.
- Deploy a release with the new migration (take a private dump first), then upload `services-rendered.csv` with the payout date the commissions were paid.
- A payroll module (daily rates, overtime, cash advances, receptionist days) would be a separate task.
