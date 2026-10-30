# Planned Tasks

Work the user has asked to record for later. Nothing here is implemented yet. Each task must follow `AGENTS.md` (additive migrations, server-rendered forms, full verification gate) when it is picked up. Move this file to `archive/` once every task is done.

Added 2026-10-30. Status of every task below: planned, not started.

## Task 1: Pretrained XLM-RoBERTa Sentiment Model for Feedback

Today `App\Services\HybridSentimentClassifier` chooses between the rules in `SentimentClassifier` and `LocalSentimentModel` (a native PHP JSON artifact trained by `scripts/train_sentiment_model.py`). The `rules`, `shadow` and `model` modes come from `SENTIMENT_CLASSIFIER_MODE` in `config/sentiment.php`, which defaults to `shadow`.

- [ ] Choose a pretrained multilingual XLM-RoBERTa sentiment checkpoint, or fine-tune one, that covers English, Tagalog and Taglish.
- [ ] Record the model's source and license in [sentiment-dataset-manifest.json](sentiment-dataset-manifest.json).
- [ ] Decide how to run inference. Hostinger shared hosting cannot run a Python or transformer worker, and the [Sentiment Analysis Implementation Plan](SENTIMENT_ANALYSIS_IMPLEMENTATION_PLAN.md) rules out a mandatory external AI service. Options to compare: an ONNX export run from PHP, batch or offline classification, or a separate approved inference host.
- [ ] Add the model as another classifier behind the existing hybrid adapter. Keep the `positive`, `neutral` and `negative` labels and the rules fallback.
- [ ] Pass the evaluation gate in the implementation plan (macro-F1 against the locked test set) and run in `shadow` mode before switching to `model`.

## Task 2: Star Rating Instead of a Dropdown

Today the customer feedback form (`resources/views/customer/feedback/partials/form.blade.php`) uses a `<select name="rating">` listing 5 down to 1.

- [ ] Replace the dropdown with a 1–5 star control built as a styled radio group, so it works with the keyboard, screen readers and no JavaScript.
- [ ] Keep the `rating` field name so `FeedbackRequest` validation (`required`, integer, 1–5) and storage stay unchanged.
- [ ] Optionally show stars instead of `N/5` in the read-only views (admin and staff feedback, customer history, reports).

## Task 3: Optional Name on Feedback

Today feedback is linked to the customer profile through the appointment. The form has no name field, and staff and admin views show the customer's name.

- [ ] Confirm the meaning with the user: an anonymous option that hides the name from staff, or a free-text display name.
- [ ] Planned reading: add an optional "show my name" or anonymous choice. Keep the record linked to the customer for eligibility and the one-feedback-per-appointment rule, but hide the identity in feedback views when the customer chooses anonymous.
- [ ] Add the field through an additive migration, and update the admin and staff feedback views, reports and CSV export.

## Task 4: Feedback Pop-up When a Customer Is Eligible

Today customer My appointments shows the most recent completed visit awaiting feedback (`Customer\AppointmentController` queries `whereDoesntHave('feedback')`). The feedback form already supports modal mode through `$modalName` and `_modal` recovery.

- [ ] When a customer has a completed appointment without feedback, open the existing feedback form automatically in an `x-modal`, with that appointment preselected.
- [ ] Add a "Later" dismissal that stops the prompt for the rest of the session (session flag).
- [ ] Show the pop-up only in the customer workspace.

## Task 5: Prevent Troll Appointments

Today `CustomerAppointmentStoreRequest` has no booking rate or volume limit. The only throttles cover authentication and profile actions (`guest-sensitive` and `user-sensitive` in `AppServiceProvider`).

- [ ] Choose the safeguards with the user. Candidates:
  - a rate limiter on customer booking submissions;
  - a cap on active upcoming self-bookings per customer;
  - a verified email or phone before self-booking;
  - blocking self-booking, or requiring staff approval, after repeated no-shows or late cancellations, with thresholds in `config/casa.php`;
  - an admin flag or block on a customer profile.
- [ ] Enforce the limits inside the existing booking transaction, because confirmed appointments reserve therapist capacity.
- [ ] Show customers a clear message when a limit stops a booking, and let admins see why.
