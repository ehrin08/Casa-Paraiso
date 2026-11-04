---
type: session-log
date: 2026-11-04
---

# New customer RFM reward for first-timers

## Task

The user reported that on live, new customers were not getting the New customer RFM reward. The user chose to release the fix without a backfill, then approved the live release.

## What Changed

- `app/Services/RfmScorer.php`: `isNewCustomer()` makes a customer with exactly one paid visit within `casa.customer_rewards.new_customer_days` always score R 5 and F 1, while M is still ranked. Before this, a lone customer scored a neutral 3/3/3, and first-timers who paid on the same day tied for a lower Recency score, so neither matched the New customer group (R 5, F 1).
- `config/casa.php`: added `customer_rewards.new_customer_days = 30` and reworded the New customer preset description. Existing database segments are unchanged.
- Tests:
  - `RfmScorerTest` covers a lone first-timer, same-day first-timers and a visit outside the window.
  - `CustomerRewardsTest` covers the first paying customer and same-day first-timers receiving the voucher.
  - `AdminRfmSegmentTest`: the neutral-score fixture now uses a returning customer with two visits.
- Commit `6c0dd26` (local, not pushed).
- Live release `casa-paraiso-redesign-20261104e`, uploaded to the private `.casa-deploy-20261104e/` folder:
  - its `.env` is a mode-600 copy of the 20261104d release's (never displayed);
  - its storage links match earlier releases;
  - config, route and view caches were built;
  - `public_html` was switched atomically to it.
- Docs: [RFM Engine](../../RFM_ENGINE.md) and [Project Memory](../../PROJECT_MEMORY.md).

## Verification

- Targeted RFM, rewards, reports and membership tests: 99 passed.
- Full suite: 662 passed. `npm run build` passed, Docker started through the wrapper with `/up` returning 200, and `git diff --check` was clean.
- Release package `casa-paraiso-hostinger-20261104-033947-64b7f2.zip` passed its audits and smoke checks.
- Host: `sha256sum -c` passed, there were no pending migrations, and `casa:check-hosting` and `casa:audit-crud-integrity` passed.
- Live: `/`, `/login` and `/up` return 200, `/.env` returns 403, `/composer.json` returns 404, and the admin rules page redirects guests to login.
- Read-only live check: the New customer group is active with the Hot Compress add-on. One named customer has paid history, more than 30 days old, so nobody qualifies today.

## Decisions

- No backfill (the user's choice). The 103 imported visits never issued vouchers, because rewards are only created when a payment is recorded in the app. Only new paid visits earn rewards.
- The 30-day window is a config value, not an admin setting.

## Follow-ups

- [ ] Repoint the hPanel cron to `casa-paraiso-redesign-20261104e/artisan schedule:run`.
- [ ] Delete the ZIP and checksum in `.casa-deploy-20261104e/` once settled.
- Rollback: from the domain directory, `ln -s "$PWD/casa-paraiso-redesign-20261104d/public" public_html.new && mv -T public_html.new public_html`.
- [ ] Push `redesign` and fast-forward `main` when ready.
