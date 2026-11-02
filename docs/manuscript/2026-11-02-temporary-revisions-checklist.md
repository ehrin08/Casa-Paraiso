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

## E. Structure carry-overs (Reference.pdf used as a structural guide only)

These items are still open from the [reference comparison](2026-11-02-reference-review.md):

1. Regenerate the table of contents and the figure and table lists last. The figure list stops at Figure 6, but the body has 23 figures.
2. Add original front matter (approval sheet, acknowledgments, abstract) and Casa-only appendices (instruments, test evidence).
3. Reconcile citations: "Jesus & Buenas" vs "De Jesus", and remove the literal `&amp;`.
4. Confirm the final submission month before changing the title-page date ("April 2026").

## Suggested order

A1 (confirm the retest) → A2–A8 → B1–B2 (new screenshot) → B3, C1–C3, D2 → the E items, with navigation regenerated last.
