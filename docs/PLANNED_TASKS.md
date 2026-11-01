# Planned Tasks

Work the user has asked to record for later. Nothing here is implemented yet. Each task must follow `AGENTS.md` (additive migrations, server-rendered forms, full verification gate) when it is picked up. Move this file to `archive/` once every task is done.

Added 2026-10-30. Status of every task below: planned, not started.

Live status is tracked in the `Tasks` tab of the owner's "Casa Paraiso Tracker" Google Sheet (formerly "Casa Paraiso - Therapist Roster") (IDs T1–T5 match Tasks 1–5 below, and T8 matches Task 6; T6 and T7 are sheet-only rows). This file keeps the specifications.

## Task 1: Pretrained XLM-RoBERTa Sentiment Model for Feedback

Today `App\Services\HybridSentimentClassifier` chooses between the rules in `SentimentClassifier` and `LocalSentimentModel` (a native PHP JSON artifact trained by `scripts/train_sentiment_model.py`). The `rules`, `shadow` and `model` modes come from `SENTIMENT_CLASSIFIER_MODE` in `config/sentiment.php`, which defaults to `shadow`.

In progress since 2026-10-30 ([session note](notes/sessions/2026-10-30-xlm-roberta-sentiment.md)).

- [x] Choose a pretrained multilingual XLM-RoBERTa sentiment checkpoint, or fine-tune one, that covers English, Tagalog and Taglish. (`cardiffnlp/twitter-xlm-roberta-base-sentiment`, pretrained; Tagalog quality still has to be measured.)
- [x] Record the model's source and license in [sentiment-dataset-manifest.json](sentiment-dataset-manifest.json). (The model card declares no license; confirm before production use.)
- [x] Decide how to run inference. Hostinger shared hosting cannot run a Python or transformer worker, and the [Sentiment Analysis Implementation Plan](SENTIMENT_ANALYSIS_IMPLEMENTATION_PLAN.md) rules out a mandatory external AI service. (Chosen: an optional HTTP endpoint in the Hugging Face format, with a short timeout and the rules as fallback. Locally it runs as the Docker `sentiment` profile. The production host is not chosen yet.)
- [x] Add the model as another classifier behind the existing hybrid adapter. Keep the `positive`, `neutral` and `negative` labels and the rules fallback. (`SENTIMENT_MODEL_DRIVER=xlmr`.)
- [ ] Build the redacted, labelled locked test set, then pass the evaluation gate in the implementation plan (macro-F1 against the locked test set) and run in `shadow` mode before switching to `model`.

## Task 2: Star Rating Instead of a Dropdown

Done 2026-10-30 ([session note](notes/sessions/2026-10-30-star-rating.md)). The customer feedback form had used a `<select name="rating">` listing 5 down to 1.

- [x] Replace the dropdown with a 1–5 star control built as a styled radio group, so it works with the keyboard, screen readers and no JavaScript. (`x-star-rating-input`; it starts with no star chosen.)
- [x] Keep the `rating` field name so `FeedbackRequest` validation (`required`, integer, 1–5) and storage stay unchanged.
- [x] Show stars instead of `N/5` in the read-only lists and tables (customer history, staff feedback, admin and staff customer history, report records) with `x-star-rating`. The admin and staff feedback detail stat cards keep `N/5`.

## Task 3: Optional Name on Feedback

Done 2026-10-30 ([session note](notes/sessions/2026-10-30-feedback-anonymity-and-prompt.md)). Feedback had been linked to the customer profile through the appointment, and every staff and admin view showed the customer's name.

- [x] Confirm the meaning with the user. (Chosen: an anonymous choice that hides the name from therapists only. Admins still see it with an Anonymous badge.)
- [x] Keep the record linked to the customer for eligibility and the one-feedback-per-appointment rule, but hide the identity in therapist feedback views when the customer chooses anonymous. (`feedback.is_anonymous`, "Hide my name from my therapist".)
- [x] Add the field through an additive migration, and update the admin and staff feedback views, reports and CSV export. (CSV column `Hidden From Therapist`. Therapist customer pages leave anonymous feedback out.)

## Task 4: Feedback Pop-up When a Customer Is Eligible

Done 2026-10-30 ([session note](notes/sessions/2026-10-30-feedback-anonymity-and-prompt.md)). Before this, customer My appointments only showed a card for the most recent completed visit awaiting feedback.

- [x] When a customer has a completed appointment without feedback, open the existing feedback form automatically in an `x-modal`, with that appointment preselected. (`x-customer-feedback-prompt`, on every customer page except the feedback and booking pages.)
- [x] Add a "Later" dismissal that stops the prompt for the rest of the session (session flag). (`customer.feedback.prompt.dismiss`.)
- [x] Show the pop-up only in the customer workspace.

## Task 5: Prevent Troll Appointments

Done 2026-11-01 ([session note](notes/sessions/2026-11-01-booking-safeguards.md), [design](superpowers/specs/2026-11-01-booking-safeguards-design.md), [plan](superpowers/plans/2026-11-01-booking-safeguards.md)). Before this, `CustomerAppointmentStoreRequest` had no booking rate or volume limit.

- [x] Choose the safeguards with the user. (Chosen: a 3-per-10-minutes submission limit, a cap of 2 upcoming self-bookings, a pause after 2 no-shows in 90 days, and an admin block with a reason. Phone verification, late-cancellation tracking and staff approval were left out.) Candidates:
  - a rate limiter on customer booking submissions;
  - a cap on active upcoming self-bookings per customer;
  - a verified email or phone before self-booking;
  - blocking self-booking, or requiring staff approval, after repeated no-shows or late cancellations, with thresholds in `config/casa.php`;
  - an admin flag or block on a customer profile.
- [x] Enforce the limits inside the existing booking transaction, because confirmed appointments reserve therapist capacity. (`CustomerBookingGuard` inside `AppointmentWorkflow::autoBook`, after locking the profile row.)
- [x] Show customers a clear message when a limit stops a booking, and let admins see why. (Booking notice for customers, Online booking card for admins, badge for reception.)

## Task 6: Animations Throughout the Project

Added 2026-10-30. Planned, not started. Tracker ID T8.

Motion today is limited to a few places: the drawer slide and fade and the loading sweep and pulse (keyframes in `resources/css/app.css`), the centered panel (`appointment-form.css`), the landing team-chart rise (`landing.css`), Alpine `x-transition` in the `modal`, `dropdown` and `toast-stack` components, and the scroll-hiding top bar (`resources/js/scroll-header.js`). The [Brand UI Guide](BRAND_UI_GUIDE.md#accessibility-and-motion) keeps motion between 160–220ms, uses it only to explain navigation or state changes, and requires honoring `prefers-reduced-motion`.

- [ ] Agree the scope with the user: the public landing page only, or every workspace, and which moments.
- [ ] Add shared motion tokens (durations within 160–220ms, easing) to the `@theme` block in `resources/css/app.css`, and reuse the existing keyframes rather than adding per-page ones.
- [ ] Candidates: content fade-in on Turbo navigation, staggered `stat-strip` and card entry, landing section reveal on scroll, table-row and list hover and press feedback, `x-section-tabs` and status-chip transitions, and consistent toast, modal and dropdown timing.
- [ ] One global `prefers-reduced-motion: reduce` override. No content may depend on animation to be read, and nothing may cause layout shift or delay interaction.
- [ ] Keep it presentation-only: server-rendered forms, Turbo and Alpine behavior stay unchanged. Verify with the full gate plus desktop, mobile and reduced-motion browser checks.

## Task 7: Make the RFM Rules Friendly for Non-Technical Users

Added 2026-11-01. Planned, not started. Not yet in the tracker sheet.

The RFM rule engine (see [RFM Engine](RFM_ENGINE.md)) works, but the client finds it too technical.
- On **RFM rules and settings** (`admin.promotions.settings.edit`) and the rule form (`resources/views/admin/promotions/segment-form.blade.php`), admins set Min/Max scores of 1 to 5 for Recency, Frequency and Monetary.
- The page explains those scores with a percent-rank table.

Keep the feature and the engine underneath (`RfmScorer`, `RfmRuleEngine`). Change only what the admin sees and types.

- [ ] Ask the client which customer groups and rewards they actually think in.
- [ ] Replace the 1 to 5 selects with plain choices mapped to score ranges. For example:
  - "Last visit": very recent, recent, a while ago, or long ago.
  - "Visits": first-timer, occasional, regular, or frequent.
- [ ] Show each rule as a sentence, for example "Customers who visited recently, come often, and spend the most get a free VIP Room."
- [ ] Rename R, F and M in the UI to "Last visit", "How often" and "Total spent". Keep the RFM terms only in a collapsible "How it works" panel, for the defense.
- [ ] Show real peso and day values, and a few example customers, for each group instead of score numbers.
- [ ] Offer ready-made templates (Reward loyal regulars, Win back lost customers, Welcome new customers), so admins rarely build rules from scratch.
- [ ] Show a plain-language preview before saving, for example "This rule would include 12 customers right now".
- [ ] Update [RFM Engine](RFM_ENGINE.md), [Screen Flow](SCREEN_FLOW.md), and the tests (`AdminRfmSegmentTest`, `AdminRewardsLayoutTest`) for the new wording, then run the full gate.
