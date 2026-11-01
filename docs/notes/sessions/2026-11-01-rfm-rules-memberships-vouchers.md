---
type: session-log
date: 2026-11-01
---

# RFM rule engine, memberships, and gift vouchers

## Task

At the defense, the panel asked what technology the RFM rewards use. The answer was "if/else" over five hard-coded presets. This task:
- Replaces those presets with a named, admin-customizable technique.
- Brings the spa's real Gold and Platinum membership cards and its ₱300, ₱500 and ₱1,000 gift vouchers into the system.

## What Changed

- **RFM (Phase 1)**
  - `RfmScorer` gives each customer 1–5 percent-rank quintile scores for Recency, Frequency and Monetary.
  - `RfmRuleEngine` evaluates admin-managed `rfm_segments` score-range rules in `priority` order, and the first match wins.
  - Migration `2026_11_01_000000_add_rfm_scoring_rules` adds the priority and score-range columns. It backfills the five presets with default score rules and stores R/F/M on `promotion_suggestions`.
  - `RfmPromotionGenerator` and `ReportDashboard::rfm()` now use both services. `config/casa.php` presets are only seed defaults.
  - New RFM rules page at `admin.promotions.settings.edit`. It has the rule list with live match counts, move up/down, edit and delete-if-unused, plus a "How scoring works" table of the current value range for each score.
  - New rule forms at `admin.promotions.segments.*`.
  - The admin customer detail shows an RFM profile.
- **Memberships (Phase 2)**
  - Migration `2026_11_01_010000_create_memberships` adds `membership_tiers` (Gold and Platinum seeded from the printed cards), `customer_memberships`, `membership_referral_credits`, `membership_perk_usages` and `transaction_adjustments`.
  - The same migration adds `transactions.gross_amount`, `discount_amount` and `customer_membership_id`, and `rfm_segments.recommended_membership_tier_id` (High-value → Platinum, Loyal → Gold).
  - `PaymentPricing` applies benefits once, when a payment is first saved as paid, in all three payment paths. The benefits are the VIP Room price, the birthday treat, the add-on %, the service %, and a referral credit. `amount` stays the net revenue.
  - Memberships screens for admins and receptionists: index with RFM recommendations, issue, show and renew. Cancel is admin only.
  - Admin membership tier editor.
  - Benefits preview on the payment forms, served by the `*.transactions.quote` JSON support endpoints.
  - Breakdown on transaction detail pages.
  - Membership card on the customer home page.
- **Gift vouchers (Phase 3)**
  - Migration `2026_11_01_020000_create_gift_vouchers` adds `gift_vouchers`, the transaction tender columns `gift_voucher_id`, `gift_voucher_applied` and `amount_due`, and the voucher settings.
  - Sell, list and show screens for admins and receptionists. Void and voucher rules are admin only.
  - The voucher code is entered on the payment and finish-service forms. It is one-time use, refused once expired or void, any excess is due, and the leftover balance is forfeited. It does not combine with member benefits or RFM rewards unless an admin allows it.
- **Other changes**
  - The reward-validity save now changes only its own setting instead of rebuilding the settings row from defaults.
  - New docs: [RFM Engine](../../RFM_ENGINE.md) and [Memberships and Gift Vouchers](../../MEMBERSHIPS_AND_VOUCHERS.md).
  - Updated HOME, MVP_SCOPE, SCREEN_FLOW, DATABASE_DESIGN and PROJECT_MEMORY.

## Verification

- New tests: `RfmScorerTest`, `AdminRfmSegmentTest`, `PaymentPricingTest`, `MembershipTest` and `GiftVoucherTest`.
- Updated tests: `CustomerRewardsTest`, `AdminRewardsLayoutTest`, `InsightRemediationTest` and `PhaseFiveToTenWorkflowTest`.
- The full suite, `php artisan test`, passed: 543 tests and 5,819 assertions.
  - Tests ran in a throwaway app container that mounted this worktree with the shared vendor volume.
  - The first full run caught a null RFM profile crash on the customer detail page for customers with no visits. It was fixed before the final run.
- `npm run build` passed.
- The three migrations ran additively on the local `casa_paraiso` database.
- Browser check, logged in as the local super admin on a worktree preview server: the RFM rules page, Memberships (with a live Gold recommendation from real data), Membership tiers, and Gift vouchers all rendered.
  - No cards, vouchers, or payments were created in local data.
  - Screenshots could not render in the pane, so the pages were checked by text.
- `git diff --check` was clean.

## Decisions

- **Scoring formula.** A percent rank of `worse / (n − 1)`, split into fifths. Ties take the lower score, so every one-visit customer is F = 1, and a lone customer scores a neutral 3.
  - With very few customers, the default rules may therefore issue no reward. Tests that expect a reward now build a small customer base.
- **Amount meaning.** The entered amount is the gross. `transactions.amount` remains the net service revenue, so commissions, reports and RFM keep one meaning.
- **Vouchers as tender.** A gift voucher is a tender, not a discount. Voucher sales are separate records, so service revenue is not double counted.
- **One-time application.** Benefits and vouchers apply only on the first save as paid. Later edits keep the stored breakdown and re-derive the net. Refunds do not restore perks, credits or vouchers.
- **Birthday treat.** One per membership per calendar year, in the birth month. A service treat is worth up to that service's price.
- **Voucher rules location.** The voucher rules live on the Gift vouchers page rather than on the rewards settings page.
- **Commits.** All three phases are one commit. They share the pricing service, routes, models and views, so separate per-phase commits would each have been unverified intermediate states.

## Follow-ups

- Reports could add membership and voucher totals and CSV exports.
- Large customer lists in the issue-card, voucher-purchaser and referrer selects should move to the existing customer lookup endpoint.
