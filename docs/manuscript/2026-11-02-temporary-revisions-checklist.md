---
type: manuscript-checklist
date: 2026-11-02
---

# Temporary Revisions checklist

This checklist covers the [Temporary Revisions](https://docs.google.com/document/d/1mKYzY63egRP4O-SqVh5m8K8KSPlxyio1bxD5IyCZF78/edit?tab=t.js4c2uprg4q8) tab as it stood on 2 November 2026. It was built from a Drive export of the tab and its 11 open comment threads. The Google Doc was not changed. UAT and SUS responses have not been collected yet. `Reference.pdf` (the LipaGo paper) was used for structure only, as in the earlier [reference comparison](2026-11-02-reference-review.md).

**Main finding:** the result column of Tables 8–10 now reads **Passed** for all 35 cases. Six prose passages still describe the October 30 record: "24 Passed, eight Partially verified, and three Not executed." The manuscript currently contradicts itself.

## A. Test-result consistency (fix first)

| # | Location | Current text | Problem | Recommended change | Source |
| --- | --- | --- | --- | --- | --- |
| A1 | Tables 8–10 (§3.10.3) | Every result begins "Passed — The system …" | Cases previously recorded as partly verified or not run are now Passed, with no new test date or evidence. Examples: ADM-07 completion, ADM-11/12 rewards, ADM-16 and THR-06 camera QR, ADM-17 concurrency, CUS-10 feedback. | Confirm that a real retest took place, its date, and its records (appointment and transaction IDs, screenshots). If none exists, restore the earlier labels. Do not report a Passed result without evidence. | My check |
| A2 | §3.10.3.1 closing paragraph | "35 cases: 24 Passed, eight Partially verified, and three Not executed … Individual legacy result labels … must be read with the retesting limits" | Contradicts A1. | After A1, state the dated retest and its totals (for example 35 Passed on *date*). Otherwise keep this paragraph and revert the labels. | Jericho comment "." |
| A3 | §4.4.1 opening | "Role-based testing and retesting on October 30, 2026 … yielding 24 Passed, eight Partially verified and three Not executed outcomes" | Same contradiction. | Bring this in line with A1/A2 and add the new retest date. | My check |
| A4 | Table 11, "Remaining verification" column | "Visit completion, generated commissions, reward issuance and redemption, customer feedback submission, QR scans, concurrency…"; row 4: "the partially verified or unexecuted workflows recorded in Tables 8–10" | Lists as unverified the very cases the tables now mark Passed. | Rebuild the table after A1. Keep only work that really remains: UAT, SUS, measured performance, real-feedback NLP evaluation, snippet fidelity. | Jericho "draft palang need pa i update" |
| A5 | §4.4.3 | "The documented October 30 retest summary … is 24 Passed …; some legacy Passed labels remain inconsistent with those evidence limits" | Same contradiction. | Rewrite after A1. | My check |
| A6 | §5.1 | "The 35 cases yielded 24 Passed, eight Partially verified and three Not executed outcomes (Tables 8–10). Visit-start restrictions limited completion and no-show checks." | Same contradiction. | Rewrite after A1. | My check |
| A7 | §3.11 (technical-verification paragraph) and §5.2/§5.3 | "Questionnaire ratings do not establish Gemini accuracy, concurrent-booking protection, camera attendance or snippet fidelity." | This sentence is correct in itself (ratings ≠ tests). Its "remaining work" lists elsewhere must drop concurrency and camera attendance once A1 is confirmed. | Keep the ratings limitation. Point to the dated test evidence for concurrency and attendance. | Jericho "for review" |
| A8 | ADM-14 (Sentiment Filtering and Review Details) and THR-04 (Assigned Visit Completion and Payment) | The result text was reworded into "The system correctly filters …" / "The system allows the therapist to complete …" | Check that the result text matches each row's expected-result column and what was actually observed. | Keep each result sentence to what was observed, tied to the dated evidence from A1. | Jericho "for review" ×2 |

## B. NLP and sentiment (Jericho: "update mo lahat ng connected sa nlp")

| # | Location | Current text | Problem | Recommended change | Source |
| --- | --- | --- | --- | --- | --- |
| B1 | Figure 23 and its discussion (§4.4.1) | "Figure 23. Rules-Based Sentiment Details and Review Controls (historical rules output)" | The only sentiment screenshot shows the retired rules-only analyzer 2.0.0. The live system uses Gemini in `model` mode (`gemini-v1`) with rules fallback. | Capture a current feedback-detail page that shows the Gemini label, confidence and fallback/review controls, with no customer identity. Recaption it, for example "Figure 23. Gemini-Assisted Sentiment Details and Review Controls". If the old figure stays, renumber it as clearly historical. | Jericho |
| B2 | Table 11 row 3 | "stored Positive labels, sentiment filters, and analyzer 2.0.0 review controls (Figure 23). Later evidence: the November 1, 2026 release and provisional Gemini comparison…" | Describes the analyzer as current and Gemini as a later add-on. | Describe the current Gemini `model` mode as the primary evidence. Keep the curated-v2 result (0.993 on 150 fictional, AI-labeled reviews) qualified as provisional. | Jericho |
| B3 | §3.10.1 Verification and Validation Plan | "The recorded October 30 implementation check passed 18 targeted sentiment tests with 100 assertions…" | Accurate, but long. The October 30 test figure and the November 1 gate read as one timeline. | Shorten it into two clear parts: (1) software checks (adapter/hybrid tests with simulated responses), and (2) predictive validation (the Nov 1 curated-v2 gate, then the independent real-feedback evaluation still needed). Keep all the limitations. | Your comment "Check" |
| B4 | §1.4, §2.5, §3.4, §4.3, §5.2 | Already describe Gemini with rules fallback and the provisional 0.993 score | No factual change needed. | Keep the wording "provisional … fictional AI-labeled set" the same in every chapter. | My check |

## C. Evaluation wording (UAT/SUS not yet collected)

| # | Location | Current text | Problem | Recommended change | Source |
| --- | --- | --- | --- | --- | --- |
| C1 | §3.11 UAT/SUS paragraph | "The planned UAT participants comprise one spa owner, one receptionist, three therapists, and ten regular customers…" | Keep this as planned, because no responses exist. It does not name the instrument. | Add that UAT and SUS are run through the Casa Paraiso User Acceptance and Usability Evaluation Google Form. Name its 1–5 scale plus N/O (Not observed), matching the §3.11 interpretation scale. | Jericho "for review" |
| C2 | §5.1 last sentence | "Customer and staff UAT and SUS evaluations remain pending, while technical accuracy, security effectiveness, and measurable business improvements require separate supporting evidence." | Fine while UAT is pending. It may overstate what is missing once A1 is settled. | Keep the "pending" wording. Re-check the list after A1. Replace it with results once the form responses are collected. | Jericho "need i update" |
| C3 | §5.2 closing | "Customer/staff UAT, SUS scores, and comprehensive technical evidence remain outstanding…" | Same as C2. "Comprehensive technical evidence" is vague. | Name the remaining technical items specifically, as in A4. | Jericho "need i update" |

## D. Objectives and attendance

| # | Location | Current text | Problem | Recommended change | Source |
| --- | --- | --- | --- | --- | --- |
| D1 | §1.3 specific objective 1.4 | "Develop a QR code-based attendance module that enables therapists to record time-in and time-out by scanning a live venue QR code…" | New text. It reads well and matches the system. | Keep it. Optionally add that the code rotates and that the scanner asks for camera permission only on the attendance page. | Your comment "New" |
| D2 | §1.2 matching sentence | "The QR code-based attendance module enables therapists … and allow authorized management to review attendance records." | Grammar: "allow" should be "allows". | Change it to "allows". | My check |

## E. Structure compared with Reference.pdf (format guide only)

The reference was read directly: 226 pages, text extracted with `pdftotext`. Use it only for **layout and section order**. Do not copy its content, sample sizes, scores, scale labels or the evaluators' personal details in its CVs.

| # | Reference pattern (physical page) | Casa Temporary Revisions | Recommended change |
| --- | --- | --- | --- |
| E1 | Front matter: Acknowledgments, Table of Contents, Lists of Tables and Figures, **Abstract** before Chapter 1 (pp. 4–12) | No acknowledgments or abstract. The contents and figure list are stale; the figure list stops at Figure 6 of 23. | Write an original acknowledgments page and an abstract built from Casa's actual results. Regenerate the contents and lists last. |
| E2 | Chapter 1 sections 1.1–1.5; the objectives list each module as a numbered sub-objective (p. ~6) | Same 1.1–1.5 sections, with numbered specific objectives | Already aligned. Only fix D2. |
| E3 | Chapter 3 testing split into **3.10 Testing and Evaluation** (V&V), **3.11 Testing Procedure** (unit → integration → system, plus model metrics), **3.12 Test Cases**, **3.13 Evaluation Procedure** (questionnaire and Likert table), then 3.14 Deployment, 3.15 Sustainability (pp. 112–143) | 3.10 combines V&V, the testing plan and test cases; 3.11 Evaluation Method; 3.12 Deployment; 3.13 Sustainability | Optional: split 3.10 the same way (3.10 V&V, 3.11 Testing Procedure, 3.12 Test Cases, 3.13 Evaluation Procedure, 3.14 Deployment, 3.15 Sustainability). This also gives B3's shortened §3.10.1 a natural home. Renumber the cross-references if you do. |
| E4 | Test cases grouped by ISO/IEC 25010 characteristic (functional suitability, performance, compatibility, usability, reliability, security, maintainability, portability), as Tables 3.5–3.12 | Grouped by role (administrator, therapist, customer) in Tables 8–10 | Keep the role grouping: it matches Casa's workspaces and the expert questionnaire already covers the ISO categories. Optionally add a sentence mapping each role table to ISO characteristics. |
| E5 | Chapter 4 opens with a short "results and their consistency with the objectives" introduction, and each 4.x.y subsection is one specific objective (pp. ~145–166) | 4.1–4.3 follow the objectives; 4.4 covers testing and evaluation | Already aligned. Check that each specific objective in §1.3 has a matching 4.x subsection, including the new Objective 1.4 (QR attendance). |
| E6 | Separate **4.4 System Expert Evaluation** (consolidated table by criterion with verbal interpretation) and **4.5 System End-User Evaluation** | 4.4.2 IT Expert Evaluation Results; UAT/SUS listed as pending in 4.4.3 | Once the Google Form responses come in, add an end-user evaluation subsection laid out like 4.4.2: a consolidated mean table with interpretation, plus the SUS score. |
| E7 | Chapter 5 adds **5.4 Evaluation Results**, with **5.4.1 Qualitative Feedback from Respondents** (themes drawn from comments, each paired with an improvement) and **5.4.2 Response Charts** (pp. 176–207) | No written-comment synthesis | After UAT, summarize the respondents' comment themes and the improvement for each. Quote no one by name. Until then, do not claim written comments were analyzed. |
| E8 | Tables numbered by chapter ("Table 3.5", "Table 4.2") | Sequential ("Table 8", "Table 13") | Institution-dependent. Change this only if the school template requires it; otherwise stay consistent. |
| E9 | **Appendices**: Appendix A Documentation & Evidence; CVs of the IT evaluators (pp. 208–226) | Ends at References | Add Casa appendices: the questionnaires and UAT form, test evidence, anonymized response summaries, and evaluator credentials only with their consent. |

Still open from the earlier [reference comparison](2026-11-02-reference-review.md): reconcile the citations ("Jesus & Buenas" vs "De Jesus", the literal `&amp;`), and confirm the submission month before changing the title-page date ("April 2026").

## F. Figures

The tab has Figures 1–23. The List of Figures shows only Figures 1–6.

**Update (2026-11-02):** the F1 diagrams (Figures 4–6) have been redrawn, and the F3 context, use case, DFD, booking and sentiment diagrams have been drawn. Their images, captions and text are now in the Doc, and Chapter IV is renumbered to Figures 15–31. Figure 1 (F1) is redrawn too. The Contents, List of Figures and List of Tables were refreshed, which covers the figure part of E1. See the [Chapter III diagram guide](2026-11-02-chapter3-diagrams.md).

### F1. Figures to update (the text already says they are out of date)

| Figure | What is wrong | Update |
| --- | --- | --- |
| 4. Three-Tier System Architecture (§3.4) | Shows Livewire and MySQL | Redraw it: Blade + Alpine.js/Turbo, Laravel 12, MariaDB, the Gemini API over HTTPS with rules fallback, Google sign-in, SMTP mail, Hostinger hosting. |
| 5. Rule-Based Promotion Engine Logical Flow (§3.4) | Fixed-threshold discount examples | Redraw it: paid, completed visit → relative 1–5 RFM scores → first matching active customer-group rule (by priority) → complimentary add-on issued (90-day validity) → reserved at booking → redeemed or expired. |
| 6. ERD (§3.7) | Baseline entities only | Regenerate it from the current migrations. Add: feedback anonymity, `rfm_segments`, memberships and cards, gift vouchers, transaction adjustments, booking blocks, operating-hour rules, sentiment runs and topics. Split it into two or three diagrams so the labels can be read. |
| 19. Automatic Reward Configuration (§4.2) | Old day, visit and peso cutoffs | Recapture the current customer-group rules page (score ranges, preview counts, templates). |
| 20. Management Reports and Sentiment Dashboard (§4.3) | Old Export CSV button and old layout | Recapture the current Reports page (three period metrics, revenue bars, status and sentiment doughnuts, top services, RFM table, the CSV/Excel/PDF/Print exports). |
| 23. Rules-Based Sentiment Details (§4.4.1) | Retired rules-only analyzer 2.0.0 | Recapture the current feedback details with the Gemini label, confidence and review controls, with the customer hidden (see B1). |
| 1. Input-Process-Output (§2.6) | Text qualifies it as a baseline; the feedback flow is now Gemini plus rules fallback | Update the Process box to "Gemini classification with rules fallback", or relabel it to match the current flow. |

### F2. Screenshots to recapture (the UI changed after the 29–30 October captures)

| Figure | Change since the capture |
| --- | --- |
| 9. Administrative Daily Dashboard (dated 29 Oct) | Reworked navigation (Today, Appointments, Customers, …), live updates, the needs-attention queue |
| 10. Appointment Management | Booking-block and capped-customer states, and the double-booking guard (1–2 Nov) |
| 15–17. Guided Booking | Booking refusals now show in the dialog; the restricted-customer notice; closing at 11:00 PM with configurable operating hours (1 Nov) |
| 7, 8, 11–14, 18, 21, 22 | Check against the live site and recapture only if they look different |

### F3. Figures the manuscript lacks

| Needed figure | Where | Why |
| --- | --- | --- |
| QR attendance: the administrator's rotating QR station, the therapist's scanner with its camera-permission prompt, and the attendance records page | Ch. IV, new subsection for Objective 1.4 | Objective 1.4 (attendance) has no screenshot at all. |
| Customer feedback form (star rating, the eligible-feedback pop-up) | §4.3 | Shows where the reviews come from; CUS-10 has no figure. |
| Admin Feedback page (sentiment cards, topic bars, service sentiment table) | §4.3 | The current sentiment output; Figure 23 shows only one review. |
| Operating-hours settings (default hours and dated closures or special hours) | §4.1.5 or §4.1.2 | A new admin feature, described in §3.5.1 with no figure. |
| Reward selection during booking | §4.2 | Shows the reserve-then-redeem step (CUS-09, ADM-12). |
| Sentiment analysis flow diagram (review → redaction → Gemini → threshold/mode → rules fallback → stored result) | §3.4 | The reference has a "Data Pipeline" figure (Fig. 3.11). Casa explains this flow only in text. |
| Context diagram and data flow diagrams (Level 0/1, optionally Level 2 per module) | §3.4 | The reference has a Context Flow Diagram and DFDs (Figs. 3.3–3.10). Casa has none. |
| Use case diagram for the five roles | §3.3 or §3.4 | Optional; summarizes what each role can do. |
| Expert evaluation chart (mean by ISO category); later, UAT/SUS charts | §4.4.2; later the end-user section | The reference adds response charts (§5.4.2). Casa reports its results only in tables. |

Diagrams must be drawn from the current Casa system, not copied from the reference. Hide customer names, emails and phone numbers in every screenshot.

## Suggested order

A1 (confirm the retest) → A2–A8 → B1–B2 and F (new screenshots and diagrams) → B3, C1–C3, D2 → the E items, with the contents and List of Figures regenerated last.
