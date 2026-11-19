---
type: session-log
date: 2026-11-19
---

# Manuscript new tabs check and review

## Task

Check whether the newly added manuscript tab is visible through the Google Docs connector, then review the two tabs that the repo notes did not yet record.

## What Changed

No manuscript or application change. The doc now has six tabs: Main tab, DECLARATION OF AI USE, IT Expert Questionnaire, Customer and User Evaluation, Chapter IV Draft, and CV Draft. Review findings:

### Customer and User Evaluation (fourth tab)

- It conflicts with the results already reported in the main tab. Chapter III and IV report 17 completed end-user evaluations scored with 1 Not met to 5 Fully met plus N/O, and a SUS mean of 80.29. This form uses an agreement scale with N, I/A/F/X task codes, and states that it is not SUS. Either label it as the instrument actually used, or keep it out of the appendices; it cannot back the reported numbers as is.
- The management page asks testers to open a "key snippet" and compare it with the review. No snippet feature exists in `app/`, `routes/` or `resources/views/`; objective 3 in the main tab says snippet extraction is evaluated separately. Remove those two tasks and items 7 and the snippet half of the guide, or mark them X.
- The respondent role "Owner or Manager" is a single person, so the anonymity promise does not hold for that page; say so in the facilitator guide.
- The tab title ("Customer and User Evaluation") differs from the form heading ("Casa Paraiso User Evaluation").
- The facilitator guide's scoring rules (missing kept as missing, completion rates with stated denominators, ratings not treated as technical evidence) are sound.

### DECLARATION OF AI USE (second tab)

- Every field is blank. It is a submission requirement for November 20 under the revised manuscript.
- The repo history shows heavy AI use that must be declared: 136 of 152 commits since 2026-08-02 carry a Claude co-author trailer, and the Chapter IV and CV drafts were written by Claude in review tabs. Expect source code, documentation and Chapter IV entries at Level 3, possibly 4.
- Level 4 (kept with little change) needs the adviser's prior approval (Part IV key and Part VII item 3). Settle this before signing.
- Part VI asks that AI-assisted code be marked in comments or listed in the README. `README.md` does not mention AI assistance yet.
- If the form is the department's official template, fill it without editing its wording; the APA guidance date in its Annex was not checked.

### Recheck later the same day

A second connector read showed four tabs: Main tab, DECLARATION OF AI USE, Chapter IV Draft, and CV Draft. The IT Expert Questionnaire and Customer and User Evaluation tabs were removed; the text of the remaining four is unchanged. The evaluation-form findings above no longer apply to the doc; the AI declaration findings still do.

### Task list

Added the AI Declaration to the work before November 20 in the [Submission Timeline](../../manuscript/2026-11-17-submission-timeline.md), at the user's request.

## Verification

Read the doc with the Google Docs connector, extracted both tabs' full text, compared them with the main tab's evaluation sections, searched the application for the features each task names, and counted AI co-authored commits with `git log`.

## Decisions

None; review only. No edits were made to the Google Doc.

## Follow-ups

- Confirm the removal of the two questionnaire tabs was intended; the appendices still need the instruments actually used.
- Fill in the AI declaration with the group, and ask the adviser about any Level 4 component.
