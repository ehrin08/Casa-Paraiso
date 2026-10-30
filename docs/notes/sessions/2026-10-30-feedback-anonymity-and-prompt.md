---
type: session-log
date: 2026-10-30
---

# Anonymous feedback and feedback pop-up (T3, T4)

## Task

Planned tasks T2–T4 from [PLANNED_TASKS.md](../../PLANNED_TASKS.md). T2 (star rating) was already done earlier the same day ([session note](2026-10-30-star-rating.md)), so this session built T3 (optional name on feedback) and T4 (pop-up when a customer is eligible).

## What Changed

- **T3, anonymous to therapists:**
  - Additive migration `2026_10_30_120000_add_is_anonymous_to_feedback_table` adds `feedback.is_anonymous` (boolean, default false).
  - The customer feedback form has a "Hide my name from my therapist" checkbox. `FeedbackRequest` validates it as a nullable boolean, and `Customer\FeedbackController@store` saves it.
  - `Feedback::staffDisplayName()` returns "Anonymous customer" when the flag is set. The therapist feedback list and detail use it.
  - `Staff\FeedbackController` leaves anonymous rows out of name search and sorts them as a blank name. `Staff\CustomerController` leaves them out of therapist customer feedback lists and counts.
  - Admins still see the name, with an Anonymous badge on the feedback list and detail. The report feedback CSV adds `Hidden From Therapist` (yes/no).
  - Customers see a "Hidden from therapist" badge in their own history.
- **T4, feedback pop-up:**
  - New `Appointment::scopeAwaitingFeedback()`, reused by customer My appointments, the feedback create page and the pop-up.
  - New `App\View\Components\CustomerFeedbackPrompt` (`x-customer-feedback-prompt`), rendered in `layouts/app.blade.php` after `<main>` for customers. It opens the shared feedback form in a transient `x-modal` named `customer-feedback-prompt`, with the most recent eligible visit preselected.
  - The pop-up is skipped:
    - on the feedback pages and the booking page;
    - while another form's errors are being recovered;
    - after Later.
  - Later is a normal POST to the new `customer.feedback.prompt.dismiss` route, which sets the session flag `customer_feedback_prompt_dismissed` and redirects back. Escape closes the pop-up for the current page only.
- **Tests:**
  - New `CustomerFeedbackAnonymityTest` and `CustomerFeedbackPromptTest`.
  - `CustomerAppointmentHistoryTest` now dismisses the pop-up before asserting that a filtered list leaves out a completed visit, because the pop-up legitimately lists that visit.
- **Docs:** [PLANNED_TASKS.md](../../PLANNED_TASKS.md), [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md), [DATABASE_DESIGN.md](../../DATABASE_DESIGN.md) and [SCREEN_FLOW.md](../../SCREEN_FLOW.md).

## Verification

- The new tests passed: 11 tests, 73 assertions.
- Related suites passed, covering:
  - feedback rating;
  - admin feedback and reports;
  - customer appointments, history and booking;
  - insights;
  - role workspace;
  - guest booking;
  - modal infrastructure.
- Full Laravel suite passed: 538 tests, 5,748 assertions.
- JavaScript tests passed: 92 of 92.
- `npm run build` passed.
- `casa-docker.ps1 start` passed with healthy services. The retained `mysql` orphan container was left alone.
- `git diff --check` passed.
- Not done: an interactive browser check of the pop-up (focus, Escape, Later). It needs a signed-in local customer account with a completed visit.

## Decisions

- Anonymous hides the name from therapists only. Admins keep the name so they can follow up on complaints (the owner's choice).
- The pop-up appears on every customer page except feedback and booking (the owner's choice). It lists every eligible visit, with the latest preselected.

## Follow-ups

- Anonymity hides the name, not the visit. A therapist can still infer the customer from the date and service of their own appointment.
- Browser-check the pop-up as a local customer before release.
- Deploy with the next release. It includes one additive migration, so take a private dump before migrating the live database.
- Set T3 and T4 to Done in the `Tasks` tab of the "Casa Paraiso Tracker" Google Sheet.
