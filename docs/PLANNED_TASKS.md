# Planned Tasks

Work the user has asked to record for later. Nothing here is implemented yet. Each task must follow `AGENTS.md` (additive migrations, server-rendered forms, full verification gate) when it is picked up. Move this file to `archive/` once every task is done.

Added 2026-10-30. Each task below records its own status.

Live status is tracked in the `Tasks` tab of the owner's "Casa Paraiso Tracker" Google Sheet (formerly "Casa Paraiso - Therapist Roster") (IDs T1–T5 match Tasks 1–5 below, and T8 matches Task 6; T6, T7 and, since 2026-11-01, T9–T12 are sheet-only rows). This file keeps the specifications.

## Task 1: Sentiment Model for Feedback

Today `App\Services\HybridSentimentClassifier` chooses between the rules in `SentimentClassifier` and `LocalSentimentModel` (a native PHP JSON artifact trained by `scripts/train_sentiment_model.py`). The `rules`, `shadow` and `model` modes come from `SENTIMENT_CLASSIFIER_MODE` in `config/sentiment.php`, which defaults to `shadow`.

Started 2026-10-30 ([session note](notes/sessions/2026-10-30-xlm-roberta-sentiment.md)). Done 2026-11-01: Google Gemini passed the gate and runs in `model` mode. XLM-RoBERTa failed both gates and its driver was removed on 2026-11-01 ([session note](notes/sessions/2026-11-01-xlmr-removal.md)); the checked items below that name it are history.

- [x] Choose a pretrained multilingual XLM-RoBERTa sentiment checkpoint, or fine-tune one, that covers English, Tagalog and Taglish. (`cardiffnlp/twitter-xlm-roberta-base-sentiment`, pretrained; Tagalog quality still has to be measured.)
- [x] Record the model's source and license in [sentiment-dataset-manifest.json](sentiment-dataset-manifest.json). (The model card declares no license; confirm before production use.)
- [x] Decide how to run inference. Hostinger shared hosting cannot run a Python or transformer worker, and the [Sentiment Analysis Implementation Plan](SENTIMENT_ANALYSIS_IMPLEMENTATION_PLAN.md) rules out a mandatory external AI service. (Chosen: an optional HTTP endpoint in the Hugging Face format, with a short timeout and the rules as fallback. Locally it runs as the Docker `sentiment` profile. The production host is not chosen yet.)
- [x] Add the model as another classifier behind the existing hybrid adapter. Keep the `positive`, `neutral` and `negative` labels and the rules fallback. (`SENTIMENT_MODEL_DRIVER=xlmr`.)
- [x] Build the redacted, labelled locked test set, then pass the evaluation gate in the implementation plan (macro-F1 against the locked test set) and run in `shadow` mode before switching to `model`. Real feedback is too sparse (2 comments locally), so on 2026-11-01 the user chose a curated set of 240 fictional comments with two-person CSV labelling ([session note](notes/sessions/2026-11-01-sentiment-test-set.md), [Labelling Guide](sentiment/LABELING_GUIDE.md)).
  - [x] Draft the items, label sheets, agreement and lock scripts, and the gate verdict in `scripts/evaluate_sentiment_model.py`.
  - [x] ~~Two people fill in `labels-a.csv` and `labels-b.csv`.~~ No labellers were available, so on 2026-11-01 the user chose the AI-drafted labels in `suggested.csv`, disclosed as unverified ([session note](notes/sessions/2026-11-01-sentiment-gate-ai-labels.md)).
  - [x] Lock the set and record its SHA-256 in the manifest. (No agreement script or kappa, since there was only one source of labels.)
  - [x] Run rules, local and xlmr predictions and the gate. (Provisional result: xlmr macro-F1 0.694 against rules 0.839, failed all four checks, so it stays out of `shadow`. Production stays on `rules` with the `local` driver.)
- [x] Try an improved rules v3 and gate it on a fresh held-out set ([session note](notes/sessions/2026-11-01-sentiment-rules-v3-reverted.md)). The AI-labelled `curated-v2` set (150 rows) was locked before tuning. Every classifier failed: v2 0.714, v3 0.710, local 0.725, xlmr 0.536. v3 was reverted and production stays on the v2 rules.
- [x] Add a Google Gemini driver and gate it on `curated-v2` ([session note](notes/sessions/2026-11-01-gemini-sentiment-driver.md)). It passed with macro-F1 0.993, and the owner chose it for production in `model` mode on the free tier.
- [ ] Later, optional: move the Gemini project to paid billing so Google stops using the comments.

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
- [x] 2026-11-02: widen anonymity to every role, admins included, at the user's request ([session note](notes/sessions/2026-11-02-fully-anonymous-feedback.md)). The checkbox is now "Submit anonymously", and the CSV column is `Anonymous`.

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

Added 2026-10-30. Done 2026-11-01 ([session note](notes/sessions/2026-11-01-subtle-animations.md)). Tracker ID T8.

Before this, motion was limited to a few places: the drawer slide and fade and the loading sweep and pulse (keyframes in `resources/css/app.css`), the centered panel (`appointment-form.css`), the landing team-chart rise (`landing.css`), Alpine `x-transition` in the `modal`, `dropdown` and `toast-stack` components, and the scroll-hiding top bar (`resources/js/scroll-header.js`). The [Brand UI Guide](BRAND_UI_GUIDE.md#accessibility-and-motion) keeps motion between 160–220ms, uses it only to explain navigation or state changes, and requires honoring `prefers-reduced-motion`.

- [x] Agree the scope with the user. (Chosen: everywhere, the landing page and every workspace, kept subtle.)
- [x] Add shared motion tokens. (`--ease-casa` is in `@theme`. The duration, rise and stagger tokens are on `:root`. There are two shared keyframes, `casa-enter` and `casa-fade-in`.)
- [x] Done: page fade-in, staggered `stat-strip`, appointment-stat and metric-card entry, landing hero entry and section reveal on scroll, table-row hover and button and tab press feedback, and consistent toast, modal and dropdown timing. Status chips stay static, because they do not change state in place.
- [x] One global `prefers-reduced-motion: reduce` override. (It was already at the end of `resources/css/app.css` and covers every new rule. The scroll reveal is also gated on `no-preference`.)
- [x] Keep it presentation-only. (Full gate run. Desktop and mobile were checked in the browser. Reduced motion could not be emulated in the browser pane, so it was checked by reading the CSS.)

## Task 7: Make the RFM Rules Friendly for Non-Technical Users

Added 2026-11-01. Wording done 2026-11-01 ([session note](notes/sessions/2026-11-01-rfm-friendly-rules.md)). Preview and templates done 2026-11-01 ([session note](notes/sessions/2026-11-01-rfm-preview-templates.md)). Not yet in the tracker sheet.

The RFM rule engine (see [RFM Engine](RFM_ENGINE.md)) works, but the client found it too technical.
- On **RFM rules and settings** (`admin.promotions.settings.edit`) and the rule form (`resources/views/admin/promotions/segment-form.blade.php`), admins set Min/Max scores of 1 to 5 for Recency, Frequency and Monetary.
- The page explains those scores with a percent-rank table.

The user chose to keep how it works and change only how it looks: the same engine (`RfmScorer`, `RfmRuleEngine`), fields, validation and routes. The five seeded groups stay.

- [ ] Ask the client which customer groups and rewards they actually think in. (Kept the five seeded groups; the client can rename them in the UI. Questions to ask: [Customer Groups and Rewards](client/RFM_GROUPS_CLIENT_QUESTIONS.md).)
- [x] Replace the 1 to 5 options with plain level names. (Five named levels per measure, one per score, e.g. "Very recent", "Recent", …, so existing rules round-trip unchanged.)
- [x] Show each rule as a sentence, for example "Customers who last visited recently or very recently … get a free VIP Room."
- [x] Rename R, F and M in the UI to "Last visit", "How often" and "Total spent". Keep the RFM terms only in a collapsible "How it works" panel, for the defense.
- [x] Show real peso and day values for each level instead of score numbers. (In the form's options and the How it works table.)
- [ ] ~~Later, optional (adds behavior): a few example customers per group.~~ Not chosen on 2026-11-01.
- [x] Ready-made templates (Reward loyal regulars, Win back lost customers, Welcome new customers). (GET links on Add group that only fill the form.)
- [x] A plain-language "This rule would include 12 customers right now" preview before saving. (Shows who fits and who would join, given the groups above; updates live.)
- [x] Update [RFM Engine](RFM_ENGINE.md), [Screen Flow](SCREEN_FLOW.md), and the tests (`AdminRfmSegmentTest`, `AdminRewardsLayoutTest`, new `RfmVocabularyTest`), then run the full gate.

## Task 8: Align the App With the Client's Facebook Page

Added 2026-11-01. Items 1–3 done 2026-11-01 ([session note](notes/sessions/2026-11-01-closing-time-back-massage-closed-dates.md)). Not yet in the tracker sheet.

Source: [Client Facebook Page Review](client/FACEBOOK_PAGE_REVIEW.md), which compared the client's public page with the app. Each item starts by confirming the fact with the client. Items 1–4 change behavior, so each needs its own design choice and the full verification gate. Items 5–7 are settings or client actions and need no code.

- [x] **Closing time.** The page says the spa is open 1:00 PM to 11:00 PM. The app books until midnight (`business_hours.closes_at` `00:00` in `config/casa.php`, and "1:00 PM to 12:00 MN" in [MVP Scope](MVP_SCOPE.md)). Confirm the closing time and the latest start the client accepts. If it is 11:00 PM, change the config and the docs, and check the slot, availability and calendar tests. Existing appointments after the new close stay as they are. (Client confirmed 11:00 PM; treatments must finish by closing. Therapist shifts may still end at midnight.)
- [x] **PHP 299 back massage on its own.** The page says "Price starts at 299". In the app, the 30-Minute Back Massage (PHP 299) is only an add-on, and the cheapest package is GAIA TOUCH (PHP 499). Confirm whether customers can book it alone. If so, add it as a bookable service with an idempotent seeder or data migration, and keep the add-on. (Client confirmed. Added by data migration `2026_11_02_000000` and assigned to GAIA TOUCH therapists.)
- [x] **Holiday and closed dates.** The spa closed on 1 and 2 November 2026, but the app has no closed-date setting. Design an admin-managed list of closed dates (additive migration), and remove those dates from customer, receptionist and admin booking availability. Decide how to treat appointments already booked on a newly closed date. (Admin page `/admin/closed-dates`. A date with confirmed appointments cannot be closed until they are moved or cancelled.)
- [ ] **Seasonal promos.** The Teachers' Month promo was PHP 50 off for teaching and non-teaching staff, 1–31 October 2026. The app's discounts come only from membership tiers and RFM rules. Ask how often the client runs promos like this. Then either record them as a manual transaction adjustment or design dated promos (amount, eligible group, start and end dates).
- [ ] **Contact details in Settings.** The page lists a phone number and email, but `contact_phone` and `contact_email` default to empty. The admin enters them in Settings. No code change.
- [ ] **Public business name.** The page uses "Casa Paraiso Body & Wellness Spa" and [MVP Scope](MVP_SCOPE.md) uses "Casa Paraiso - Body and Wellness Spa". Confirm the spelling the client prefers and set it in Settings.
- [ ] **Move bookings from Messenger to the website.** Customers now book through Messenger messages. Ask the client to add casaparaiso.site to the page (a "Book now" button, a pinned post, and the Messenger auto-reply). This is a client action.

## Task 9: Apply Changes in Real Time Instead of Reloading

Added 2026-11-01. Done 2026-11-02 ([session note](notes/sessions/2026-11-02-live-updates.md), [design](superpowers/specs/2026-11-02-live-updates-design.md)). Not yet in the tracker sheet.

Today most changes only show after a full page reload or a fresh navigation. Turbo Drive is opt-in (`turboSession.drive = false` in `resources/js/app.js`), and state-changing forms submit normally and redirect. The only live update is the booking-count badge, which uses authenticated HTTP polling every 30 seconds (`resources/js/booking-notifications.js`). The attendance scanner calls `window.location.reload()` after a scan. Hostinger shared hosting cannot run a WebSocket server, and no broadcast credentials or broadcast routes exist ([Project Memory](PROJECT_MEMORY.md)).

- [x] Agree the scope with the user: which screens should update without a reload (for example the appointments queue, calendars, reception and therapist views, attendance and notifications), and whether that means other users' changes appearing live, the user's own saves updating in place, or both. (Chosen: both, on the appointment queues, calendars, attendance and notifications.)
- [x] Choose the transport that fits Hostinger shared hosting: extend the existing visible-page polling, Turbo Frames or Turbo Streams over normal HTTP responses, or a hosted broadcast service. A self-hosted WebSocket server is not possible there. (Chosen: visible-page polling about every 15 seconds against `GET /live/state` fingerprints, with region swaps from the current URL.)
- [x] Keep state-changing actions as normal Laravel requests with validation, CSRF and a no-JavaScript fallback, as `AGENTS.md` requires. Live updates only refresh what is shown. (Saves opt into Turbo form submission; without JavaScript they are plain forms.)
- [x] Keep therapist capacity safe: a live calendar or slot view must still be re-checked inside the booking transaction. (Unchanged: booking still checks capacity in `AppointmentWorkflow`.)
- [x] Replace the attendance scanner's page reload with an in-place update.
- [x] Update [Project Memory](PROJECT_MEMORY.md), [Tech Stack](TECH_STACK.md) and the tests, then run the full gate.
