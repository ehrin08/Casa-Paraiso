---
type: session-log
date: 2026-11-04
---

# New customer RFM reward for first-timers

## Task

The user reported that on live, new customers were not getting the New customer RFM reward. The fix was to be released without a backfill.

## What Changed

- `app/Services/RfmScorer.php`: `isNewCustomer()` makes a customer with exactly one paid visit within `casa.customer_rewards.new_customer_days` always score R 5 and F 1, while M is still ranked. Before this, a lone customer scored a neutral 3/3/3, and first-timers who paid on the same day tied for a lower Recency score, so neither matched the New customer group (R 5, F 1).
- `config/casa.php`: added `customer_rewards.new_customer_days = 30` and reworded the New customer preset description. Existing database segments are unchanged.
- Tests:
  - `RfmScorerTest` covers a lone first-timer, same-day first-timers and a visit outside the window.
  - `CustomerRewardsTest` covers the first paying customer and same-day first-timers receiving the voucher.
  - `AdminRfmSegmentTest`: the neutral-score fixture now uses a returning customer with two visits.
- Commit `6c0dd26` (local, not pushed).
- Docs: [RFM Engine](../../RFM_ENGINE.md) and [Project Memory](../../PROJECT_MEMORY.md).

## Verification

- Targeted RFM, rewards, reports and membership tests: 99 passed.
- Full suite: 662 passed. `npm run build` passed, Docker started through the wrapper with `/up` returning 200, and `git diff --check` was clean.
- Live release: not done in this session. The automatic permission check blocked SSH to the Hostinger host, so the package was built but not uploaded.

## Decisions

- No backfill (the user's choice). The 103 imported visits never issued vouchers, because rewards are only created when a payment is recorded in the app. Only new paid visits earn rewards.
- The 30-day window is a config value, not an admin setting.

## Follow-ups

- [ ] Release `6c0dd26` to Hostinger as `casa-paraiso-redesign-20261104e`. No migrations are needed. Create `public/storage` before `casa:check-hosting`. Rollback is a link switch to `casa-paraiso-redesign-20261104d/public`.
- [ ] Push `redesign` and fast-forward `main` when ready.
