---
type: session-log
date: 2026-11-04
---

# Payments showing on Insights

## Task

Explain why payments now appear on the admin Insights (Reports) page.

## What Changed

- No code changes. Insights (`admin.reports.index`, `Admin\ReportController`) has always built Total revenue, the revenue chart and the Transactions tab from paid `transactions`. The page code was last changed on 2026-11-02.
- What changed is the data: the 2026-11-04 service-history import created 103 paid transactions (₱57,437) on live, so Insights now has payments to show.

## Verification

- Read `routes/web.php`, `Admin\ReportController::insights`, `resources/views/admin/reports/index.blade.php`, `layouts/navigation.blade.php` and their git history. Payments and Insights remain separate menu items.

## Decisions

- None.

## Follow-ups

- If imported history should be left out of Insights, filter on `transactions.import_reference` in a separate change.
