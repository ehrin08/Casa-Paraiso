---
type: session-log
date: 2026-11-02
---

# UAT form formal wording

## Task

Reword the Google Form "Casa Paraiso — User Acceptance and Usability Evaluation" so UAT participants read formal, non-technical language.

## What Changed

- Google Form (edited in Chrome, owner's Drive): section 1 and 2 descriptions and questions, the four role task sections (titles, descriptions, grid titles, task rows, comment questions), the section 7 title and description, and the section 8 title, description and open questions.
- Jargon removed: "synthetic records", "designated test accounts", "attendance QR station", "sentiment", "source-linked comments", "completed-service popularity reports", "RFM".
- Unchanged: question types, required flags, answer scales, section order and navigation, the section 8 agreement statements, and the ten System Usability Scale statements.
- Follow-up: removed the participant code question. The section 1 and 2 descriptions no longer mention a participant code.
- Follow-up: the four role task grids and the section 8 grid now use the manuscript's §3.6.4 scale: 1 - Not met, 2 - Largely unmet, 3 - Partly met, 4 - Mostly met, 5 - Fully met, N/O - Not observed. N/O replaces "Not attempted", and the section descriptions and section 8 grid title explain the scale.
- No repository file held a copy of the questionnaire, so only this note and the session index changed in the repository.

## Verification

- Reloaded the form editor and read back every section; all new wording was saved.
- After the follow-up, a reload showed six columns (1–5 plus N/O) in all five grids, no participant code question, and the SUS items unchanged.
- `git diff --check` passed.

## Decisions

- The ten SUS statements keep their standard wording so the SUS score stays valid for the manuscript. Only the section title and instructions changed.
- The role-based section routing (consent "No" submits; each role goes to its task section, then to section 7) was left unchanged and rechecked after the edits.

## Follow-ups

- The form's public `viewform` link reports "This document is not published". Publish the form before sending it to participants.
