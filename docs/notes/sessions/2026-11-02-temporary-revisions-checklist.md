---
type: session-log
date: 2026-11-02
---

# Temporary Revisions checklist

## Task

Check the manuscript's Temporary Revisions tab and list what needs revising or changing. The user chose a checklist only. UAT and SUS data have not been collected. `Reference.pdf` was supplied as a structural reference only.

## What Changed

- Added the [Temporary Revisions checklist](../../manuscript/2026-11-02-temporary-revisions-checklist.md) and linked it from HOME.
- The Google Doc, application code and data were not changed.

## Verification

- Read the tab's text and 11 open comment threads from a Drive export. Counted the result labels in Tables 8–10 (all Passed) and searched for the old 24/8/3 summary, which still appears in six places.
- Checked the NLP passages against the Gemini configuration recorded in project memory.
- Reused the earlier reference comparison instead of re-reading `Reference.pdf`; no local PDF text tool was available.
- Checked the relative links and ran `git diff --check`. Application tests, the asset build and Docker were skipped for this documentation-only work.

## Decisions

- Did not judge whether the new Passed labels are correct; the checklist asks for the dated retest evidence first.
- Kept UAT/SUS wording as planned/pending.

## Follow-ups

- Confirm or revert the Tables 8–10 labels, then update §3.10.3.1, §4.4.1, Table 11, §4.4.3 and §5.1.
- Take a current Gemini feedback-detail screenshot for Figure 23.
