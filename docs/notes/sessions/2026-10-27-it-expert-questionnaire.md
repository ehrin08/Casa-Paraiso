---
type: session-log
date: 2026-10-27
---

# IT expert evaluation questionnaire

## Task

Create the approved printable Word questionnaire with 20 items and a 1 to 5 quality scale.

## What Changed

- Created a four-page editable questionnaire at `output/questionnaire/Casa_Paraiso_IT_Expert_Evaluation_Questionnaire.docx`.
- Included five criteria each for functional suitability, performance efficiency, security, and usability; evaluator information; behavioral scale anchors; Not Observed; and comment fields.
- Added an administration checklist and scoring worksheet after the respondent form. Examples use synthetic records, the current 22% commission rate, and complimentary add-on rewards.
- Added this note to the documentation indexes. The application and Google manuscript were not changed.

## Verification

- Checked feature alignment against project memory, MVP scope, and the configured commission rate.
- Builder confirmed exactly 20 rating items across four categories, repeating table headers, and non-splitting rows.
- Packaged DOCX renderer failed because LibreOffice was unavailable. Used Microsoft Word PDF export and bundled pypdfium2 to render all four pages, then visually inspected every page.
- Corrected title border residue, enlarged response boxes, and improved footer spacing before final rendering.
- `git diff --check` passed. No application tests were needed for this document-only task.

## Decisions

- Scale is 1 Poor, 2 Fair, 3 Good, 4 Very Good, 5 Excellent; Not Observed and blanks are excluded from means.
- Report category and overall coverage; no invented passing threshold or mean-to-label bands.
- This researcher-developed expert instrument remains separate from standardized SUS. Performance targets require measured evidence beyond questionnaire ratings.
- Local output and render intermediates remain in `output/questionnaire/`; only the Word document is delivered.

## Follow-ups

- Prepare synthetic test accounts and reference outcomes before administering the questionnaire.
