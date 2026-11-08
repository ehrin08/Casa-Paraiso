---
type: session-log
date: 2026-11-04
---

# New customer reward check and Kenneth voucher

## Task

The user asked why a recent live customer had no New customer reward, then approved a manual voucher for Kenneth Clavano.

## What Changed

- No application code changed. Investigation used read-only tinker queries over SSH against release `casa-paraiso-redesign-20261104e`.
- Live (user-approved): created promotion suggestion 5 for Kenneth Clavano (customer 24): New customer segment, `hot-compress`, status `suggested`, expires 2027-02-02. It is shaped as the fixed generator would have issued it for transaction 217 (generation key `paid-transaction|217`, R 5 F 1 M 3, frequency 1, ₱649), and its `notes` record the manual grant. The script was transactional, checked the customer, transaction and segment, and refused to run twice.

## Verification

- Before the grant, no promotion suggestions existed on live. The RFM population holds one customer (Kenneth); the imported walk-in profile is excluded.
- No customer profile or user named "Charles" exists on live.
- Newest customers: Ehrin John Galang (28) and Cela Main (27) each have one `confirmed` appointment and no transaction. EHRIN JOHN GALANG (26) is the same. Kenneth Clavano (24) has two paid, completed visits (09:38 and 11:10 Manila, 2026-11-04).
- After the grant, `RfmAddonVoucher::availableFor` returns voucher 5 for Kenneth.
- SSH on port 65002 was refused, then timed out and reset, before it connected after a network switch.

## Decisions

- Vouchers are created only when a completed appointment's payment is recorded, so customers with only a confirmed booking get nothing yet. This is expected behavior, not a defect.
- Kenneth's first payment came before the 20261104e fix went live (about 11:45 Manila), and he now has two visits, so he no longer counts as new. Only Kenneth got a backfilled voucher; there is no general backfill.
- There is no admin screen for issuing vouchers by hand, so the grant went through tinker.

## Follow-ups

- [ ] Optional: add an admin "Give reward" action for one customer, so manual grants no longer need tinker. The user asked how to add a complimentary reward; today rewards are set per customer group at Admin → Promotions → Settings, with add-ons managed at Admin → Add-ons.
