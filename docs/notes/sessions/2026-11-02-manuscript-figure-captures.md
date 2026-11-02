---
type: session-log
date: 2026-11-02
---

# Manuscript figure captures

## Task

Capture current screenshots for the outdated and missing manuscript figures listed in section F of the [Temporary Revisions checklist](../../manuscript/2026-11-02-temporary-revisions-checklist.md).

## What Changed

- Saved 18 full-page PNGs (1440 px wide) from the local app to the ignored `output/questionnaire/figures/2026-11-02/`. Files named `figNN-…` replace existing figures; files named `new-…` are figures the manuscript lacks.
  - Replacements: Figs. 7, 8, 9, 10, 14, 15, 18, 19, 20, 22.
  - New: admin attendance QR station, admin attendance records, therapist attendance page, therapist camera-permission prompt, admin Feedback page, operating-hours settings, customer feedback form, customer feedback list.
- `output/questionnaire/figures/capture.mjs` is the headless-Chrome capture script. It signs in with the seeded `*.casaparaiso.test` accounts, emulates reduced motion so the landing page's scroll-reveal sections are visible, and blurs any text that names a non-test account.
- Follow-up: added local test data through the app's own services. Only demo `.test` accounts are involved, and nothing was deleted or changed in existing records.
  - A one-day availability exception (2 November, 1:00–11:00 PM, reason "Manuscript figure test data") for the demo therapist, who is not on the published roster.
  - Two QR attendance scans for the demo therapist through `AttendanceWorkflow`: time-in at 2:34 PM, time-out at 2:35 PM.
  - Visit APT-0010 for the demo customer and demo therapist (TETHYS FLOW, 3:00 PM), scheduled through `AppointmentWorkflow`. At 3:00 PM it was completed through `AppointmentCompletion` with test payment TRX-0006 (PHP 649, cash), which generated one therapist commission.
- Recaptured all screens with this data. Added `new-customer-feedback-popup.png`, the post-visit "How was your visit?" prompt. Fig. 14 is now taken after dismissing it.
- No application code, Google Doc or tracked figures changed.

## Verification

- Viewed every capture. Non-test names and emails are blurred in the appointments, feedback, reports, rewards and attendance captures. The public team portrait is the photo already published on the site.
- The first pass showed a stale closed-modal shot, an empty landing page and one unblurred name inside a mixed cell. All were fixed and the full set was recaptured.
- `git diff --check` passed.

## Decisions

- **Figure 23 was not recaptured.** Local feedback is classified by the rules driver (Analysis 2.0.0), because Gemini runs only where `SENTIMENT_MODEL_DRIVER=gemini` is set. A Gemini detail screenshot must come from the live site, with the customer hidden, or from a local run with a Gemini key.
- Used a schedule exception instead of editing the published roster, so the roster stays as it was.
- The customer feedback form still says labels come from rating and keyword rules; this is app copy, left unchanged.

## Follow-ups

- Capture Figure 23 from Gemini output.
- Diagram redraws (Figs. 1, 4, 5, 6, DFDs, sentiment flow) still need to be drawn.
