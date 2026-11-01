---
type: session-log
date: 2026-11-01
---

# Dummy data tracker

## Task

Create the approved three-month synthetic dataset in the existing [Casa Paraiso Tracker](https://docs.google.com/spreadsheets/d/1tScTEbUX4WJvuaKp-qaSQ2hkv_C6aTaSq3nv5R9LS3g/edit), using the supplied [paper](https://docs.google.com/document/d/1mKYzY63egRP4O-SqVh5m8K8KSPlxyio1bxD5IyCZF78/edit?tab=t.js4c2uprg4q8) and current repository rules. Tracker only; no website or database import.

## What Changed

- Added Demo Guide, Demo Customers, Demo Services, Demo Appointments, Demo Payments, Demo Commissions, Demo Reviews, and Demo RFM tabs.
- Added 80 fictional customers, four catalog packages, and 600 appointments for August 1 through October 31, 2026 (540 completed, 40 cancelled, 20 no-shows).
- Added 540 payments (522 paid, 18 unpaid), 522 pending commissions, 180 fictional multilingual reviews, and 80 RFM snapshot rows.
- Paid net revenue totals PHP 340,302.00; individually rounded 22% commissions total PHP 74,866.36.
- Added stable DEMO IDs, unchecked entry checkboxes, filters, frozen headers, status validation, payment-net and commission formulas, and peso/Manila date formats. Existing Therapists and Tasks tabs were preserved.

## Verification

- Read back every populated cell in all eight new tabs and compared with generated values, including evaluated formulas.
- Compared existing cells in Therapists and Tasks, including values, formatting, validation and notes; both remained unchanged.
- Verified unique IDs, cross-tab references, treatment durations, operating hours, and absence of therapist/customer overlaps.
- Verified review links and verbatim snippets, blank actual-model results, payment arithmetic, paid-only commissions, and RFM spending/frequency reconciliation.
- Confirmed all five default RFM groups, unmatched customers, and eight customers without paid visits. Scoring uses 72 eligible profiles, repository quintile/tie rules, and first-match priority.
- Visually inspected all eight tabs in Google Sheets at normal zoom; refined ID/timestamp widths and guide currency formats.
- Documentation check: git diff --check. Application tests, asset build, and Docker startup are unnecessary for tracker/documentation changes.

## Decisions

- Current catalog packages and five tracker therapists take precedence over older paper descriptions.
- Daily historical therapist availability is a demo assumption, not evidence of real attendance or performance.
- Selected paid visits have an illustrative manual 10% discount. Memberships, gift vouchers, attendance and new staff accounts are excluded.
- RFM is a fixed snapshot as of October 31, 2026 at 23:59:59 Asia/Manila. Rewards and membership recommendations are illustrative and were not issued.
- Review scenarios: 60 positive, 36 neutral, 36 negative, 24 mixed and 24 unprocessed. Expected labels are illustrative; actual model results stay blank. Synthetic data is not research findings or model evaluation evidence.

## Follow-ups

- Optional manual website entry is future work. Check Entered in Site only after manual entry; no automatic importer was created.
- Regenerate RFM after visit/payment changes. Per-row net and commission formulas recalculate; guide totals and RFM are fixed snapshots.
