---
type: session-log
date: 2026-11-17
---

# Drafting-log dates removed from the manuscript

## Task

Remove wording in the [manuscript Google Doc](https://docs.google.com/document/d/1mKYzY63egRP4O-SqVh5m8K8KSPlxyio1bxD5IyCZF78/edit?usp=sharing) that reads like a development log, such as "The November 1, 2026 release record confirms Gemini activation…".

## What Changed

- Main tab, Chapter IV, "Gemini evaluation and deployment status": the dated release-record sentence now reads "The deployed system runs Gemini in model mode, with the rules-based classifier as fallback.", and "locked curated-v2 set" became "fixed evaluation set".
- Main tab, Chapter III, predictive validation: "The locked curated-v2 dataset" became "The fixed evaluation dataset".
- Chapter IV Draft tab: "Gemini was activated in model mode with the rules fallback in the November 1, 2026 release." became "The deployed system runs Gemini in model mode with the rules fallback."

## Verification

- All four replacements reported one occurrence each.
- Re-read the Doc text: zero matches for "November 1, 2026", "release record" and "curated-v2"; the kept dates are still present.
- `git diff --check` on the changed notes.

## Decisions

- Kept "operating since September 3, 2025" (a business fact), "Retrieved November 2, 2026" in the three undated Google references (APA 7 needs a retrieval date for undated web pages), and "May 2027" on the title page.
- The internal dataset tag "curated-v2" was removed with the dates, since it is the same kind of drafting leftover.

## Follow-ups

- None.
