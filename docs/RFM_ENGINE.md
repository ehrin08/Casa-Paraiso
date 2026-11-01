# RFM Engine

How Casa Paraiso segments customers and issues automatic rewards. This is the reference to use when explaining the technique, for example at a capstone defense.

## Short answer: what technology is this?

The engine combines two techniques.

1. **RFM analysis with quintile scoring.** This is the customer-segmentation method from database marketing (Hughes, *Strategic Database Marketing*, 1994).
   - Each customer is measured on three things:
     - **Recency**: days since the last paid visit.
     - **Frequency**: number of paid, completed visits.
     - **Monetary**: total amount paid.
   - Each measure is turned into a score from 1 to 5. The score comes from the customer's **percent rank** among all customers.
2. **A rule-based decision table.** The admin manages it as a priority-ordered list of segment rules.
   - Each rule is a set of score ranges, such as R 4–5, F 4–5 and M 4–5.
   - The engine evaluates the active rules from top to bottom. The **first rule that matches wins**.
   - This is the same evaluation strategy as a first-hit decision table or a production-rule system.

Because the thresholds are relative scores, not fixed peso or day values, the segments adapt automatically as the customer base grows. Admins change the business rules from the UI, not in code.

## Scoring

Implemented in `app/Services/RfmScorer.php`.

- The **population** is every customer with at least one paid transaction linked to a completed appointment.
- For each measure, the score is computed from the share of customers who are strictly worse:

  ```
  percent_rank = customers_strictly_worse / (population − 1)
  score        = min(5, 1 + floor(5 × percent_rank))
  ```

  - "Worse" means **lower** for Frequency and Monetary.
  - "Worse" means **more days since the last visit** for Recency.
  - Ties share the lower score. For example, every one-visit customer gets F = 1.
  - A lone customer has nobody to rank against and gets the neutral score of 3.
- Sorting plus binary search keeps scoring at O(n log n), so the report and the rules page can score every customer on each request.

Worked example with ten customers whose visit counts are 1 to 10:

| Visits | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 |
|---|---|---|---|---|---|---|---|---|---|---|
| F score | 1 | 1 | 2 | 2 | 3 | 3 | 4 | 4 | 5 | 5 |

The admin rules page shows the current value range behind each score (for example, "F5: 9 – 10 visits"), so staff can see what a score means today.

## Rules (segments)

Implemented in `app/Services/RfmRuleEngine.php` and stored in `rfm_segments`.

| Column | Meaning |
|---|---|
| `priority` | Evaluation order; a lower number is checked first. |
| `r_score_min` / `r_score_max` (and `f_`, `m_`) | Inclusive 1–5 bounds; `null` means "any". |
| `addon_code` | Complimentary add-on issued as the reward (optional). |
| `recommended_membership_tier_id` | Membership card to recommend (optional). |
| `is_active` | Inactive rules are skipped. |

Default rules, seeded by `2026_11_01_000000_add_rfm_scoring_rules`:

| Order | Segment | Conditions | Reward | Recommends |
|---|---|---|---|---|
| 1 | High-value customer | R 4–5 · F 4–5 · M 4–5 | VIP Room | Platinum |
| 2 | Loyal customer | R 3–5 · F 4–5 | 30-Minute Back Massage | Gold |
| 3 | At-risk customer | R 1–2 · F 3–5 | Ventosa | — |
| 4 | Inactive customer | R 1 | Hot Stone | — |
| 5 | New customer | R 5 · F 1 | Hot Compress | — |

Admins manage the rules at **Customer rewards → Customer groups and rewards** (`admin.promotions.settings.edit`). From there they can:
- Add, edit, activate, deactivate and delete rules.
- Move rules up or down to change their priority.
- See how many customers each rule matches right now.

A rule that has already issued rewards cannot be deleted, because that would lose history. It can be deactivated instead.

## Admin wording

Admins never have to read score numbers. `app/Services/RfmVocabulary.php` (display only) renames the measures and gives each score a plain name:

| Score | Last visit (R) | How often (F) | Total spent (M) |
|---|---|---|---|
| 5 | Very recent | Very often | Top spenders |
| 4 | Recent | Often | High |
| 3 | A while ago | Regularly | Average |
| 2 | Long ago | Occasionally | Low |
| 1 | Very long ago | Rarely | Lowest |

- The groups page lists each rule as a sentence, for example "Customers who last visited recently or very recently, come often or very often and spend a lot or the most get a free VIP Room and are recommended the Platinum card."
- The form's From/To choices show the named level with today's real values, for example "Recent (4 days – 12 days)". They still submit the same 1–5 scores, so the engine, validation and stored rules are unchanged.
- The technical view (scores, percent rank, R/F/M ranges per group) stays in a collapsed "How it works (RFM scoring)" panel, for the defense.

## When a reward is issued

`RfmPromotionGenerator` runs after a transaction is first recorded as **paid** for a **completed** appointment.

1. It skips the customer if they already hold an available reward, or one reserved against a confirmed booking.
2. It scores the customer against the current population and takes the first matching active rule.
3. If that rule has an add-on, it creates one voucher. The voucher stores the raw metrics, the R/F/M scores, the add-on and the expiry, so every reward can be explained later.

The generator is idempotent per transaction: it uses a `generation_key` and row locks.

## Where RFM appears

- **Admin customer detail**: an RFM profile showing the scores, raw metrics and current segment, plus any membership recommendation.
- **Reports → Customers by RFM group**: the same scorer and rules, applied as of the report's range-end cutoff.
- **Memberships → Recommended for membership**: customers whose segment recommends a card above the one they hold.

## Business alignment

The RFM rules decide the *rewards* and *membership recommendations*. The spa's physical Gold and Platinum cards and its gift vouchers are priced at payment through one service, `PaymentPricing`. See [Memberships and Gift Vouchers](MEMBERSHIPS_AND_VOUCHERS.md).
