---
type: session-log
date: 2026-11-04
---

# Commission and net revenue on Reports

## Task

The user asked whether therapist commission is subtracted from Total revenue on Insights (admin Reports). It was not. The user then asked to show commission and net revenue there and put the change live.

## What Changed

- `app/Services/ReportDashboard.php`: `metrics()` now returns `commission` and `net` beside `revenue`. A single query joins the per-transaction commission totals (earnings plus adjustments, pending or paid) to the paid transactions in range. Net is revenue minus that commission. Total revenue stays gross.
- `resources/views/admin/reports/index.blade.php`: new **Therapist commission** and **Net after commission** cards, shown in the All and Transactions categories with the usual period comparison.
- `resources/css/reports.css`: `.reports-metrics--wide` wraps more than three cards responsively.
- Tests: `AdminReportsLayoutTest::test_commission_and_net_cover_the_same_paid_transactions_as_revenue`. `RoleWorkspaceTest` now expects five Reports cards.
- Commit `eeaab93` (local, not pushed).
- Live release `casa-paraiso-redesign-20261104c`, built from `eeaab93` as `casa-paraiso-hostinger-20261103-231315-46fe6b.zip`, uploaded to the private `.casa-deploy-20261104c/` folder:
  - its `.env` is a mode-600 copy of the 20261104b release's (never displayed);
  - its `storage/app/public` is a symlink to `casa-paraiso/storage/app/public`;
  - config, route and view caches were built;
  - `public_html` was switched atomically to it.

## Verification

- Local: 12 `AdminReportsLayoutTest` tests, then the full suite (656 passed), `npm run build`, and the package's audits and archive smoke checks.
- Host: `sha256sum -c` passed. No pending migrations. `casa:audit-crud-integrity` and `casa:check-hosting` passed.
- Live: `/`, `/login` and `/up` return 200. `/admin/reports` redirects guests. `/.env` returns 403 and `/composer.json` returns 404. No new log.
- Live figures (read-only): all time ₱57,437 revenue, ₱12,632.36 commission, ₱44,804.64 net. Last month ₱54,443, ₱11,973.68 and ₱42,469.32.

## Decisions

- Commission is attributed to the paid transactions in the range, not by `earned_at`, so net always describes the same payments as revenue.
- Pending commission counts too. Net is after all owed commission, not only what has been paid out.
- No migration, so no pre-release database dump was taken. Rollback is a link switch.

## Follow-ups

- [ ] Push `redesign` and fast-forward `main` to `eeaab93` when ready.
- [ ] Repoint the hPanel cron to `casa-paraiso-redesign-20261104c/artisan schedule:run`.
- [ ] Delete the ZIP and checksum in `.casa-deploy-20261104c/` once the release is settled.
- Rollback: from the domain directory, `ln -s "$PWD/casa-paraiso-redesign-20261104b/public" public_html.new && mv -T public_html.new public_html`.
