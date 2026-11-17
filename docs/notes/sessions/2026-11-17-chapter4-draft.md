---
type: session-log
date: 2026-11-17
---

# Chapter IV revision drafted in a review tab

## Task

Revise the whole of Chapter IV for the defense panel: more results and discussion, fewer screenshots, and screenshots only for the major functions. At the user's request, the draft goes in a new **Chapter IV Draft** tab of the [manuscript Google Doc](https://docs.google.com/document/d/1mKYzY63egRP4O-SqVh5m8K8KSPlxyio1bxD5IyCZF78/edit?usp=sharing) for review before anything changes in Temporary Revisions.

## What Changed

- Google Doc: added the Chapter IV Draft tab with a short reviewer note and the full revised chapter (about 4,850 words, up from about 4,070).
  - Reorganized by objective: 4.1 core system (authentication, booking, transactions and commissions, QR attendance), 4.2 RFM engine, 4.3 sentiment and analytics, 4.4 testing and evaluation, and a new 4.5 summary.
  - Each module reports its test cases (Tables 8–10) and ratings (Tables 13–25), then discusses the lower-rated items and compares the results with the Chapter II related systems.
  - 7 of 18 screenshots kept, one per major function, renumbered Figures 15–21 (old 18, 24, 20, 21, 26, 30, 31). The images were copied from the main tab.
  - New Table 26, Summary of Results by Objective. Tables 13–25 are unchanged and shown as placeholders.
- [Defense Panel Revisions](../../manuscript/2026-11-10-defense-panel-revisions.md) and the [Submission Timeline](../../manuscript/2026-11-17-submission-timeline.md): the Chapter IV item stays open with the draft noted as awaiting review.

## Verification

- Re-read the Doc: the new tab has all 13 headings in order, each of the 7 images sits directly above its caption, Table 26 has 5 rows and 4 columns, and no placeholder markers remain.
- The Temporary Revisions text is identical to its state before this task.
- Every number in the draft appears in the original manuscript, and every cited work is in the reference list.
- Fonts: body text is Times New Roman 13, matching the manuscript; the first formatting pass lost the font when the paragraph styles were set, and this was corrected and re-checked.
- `git diff --check` on the changed docs.

## Decisions

- Kept screenshots: appointment management, booking date and time, payment records, commissions, attendance station, reward rules and the reports dashboard.
- Discussion uses only numbers already in the manuscript; explanations of lower ratings are worded as likely causes, not findings.
- The functional-testing summary states that each of the 35 recorded actual results matched its expected output, since Tables 8–10 have no separate pass/fail column.

## Follow-ups

- Review the draft tab; once approved, apply it to Temporary Revisions (remove 11 screenshots and their text, renumber Figures 15–21, add Table 26, refresh the List of Figures and List of Tables) and delete the draft tab.
- New Figure 16 still shows the old 1 PM to midnight hours label; recapture it with the current hours.
