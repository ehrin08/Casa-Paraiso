---
type: session-log
date: 2026-11-17
---

# Group CV drafted in a review tab

## Task

Draft a curriculum vitae for the three group members, using the supplied sample CV as the format reference. Add it to the [manuscript Google Doc](https://docs.google.com/document/d/1mKYzY63egRP4O-SqVh5m8K8KSPlxyio1bxD5IyCZF78/edit?tab=t.ciz2b8g8tnvf) as a new tab marked as a draft, with a border around each CV so it is easy to format and move into the main manuscript, and prepare questions to send to the group.

## What Changed

- Google Doc: new **CV Draft** tab (`t.ciz2b8g8tnvf`, after Chapter IV Draft) with a highlighted draft note, then three identical CV templates. Each starts on its own page inside a one-cell table with a 1.5 pt border and a one-page minimum height. Placeholders are in brackets; degree, university and capstone title are filled in.
- New [Group Curriculum Vitae](../../manuscript/2026-11-17-group-cv.md) note with the format, status checklist and the questions for the group chat, linked from [Docs Home](../../HOME.md).
- [Submission Timeline](../../manuscript/2026-11-17-submission-timeline.md): Curriculum vitae requirement annotated, and the CV added to the work before November 20.

## Verification

- Re-read the Doc: the four existing tabs have the same body as before; the new tab has 3 page breaks and 3 bordered tables, each with the same 42 paragraphs, 11 bullets, rules under the contact block and the eight section headings, all in Times New Roman.
- A PDF export for a visual check was refused ("File too large for export"), so page fit was not checked visually.
- `git diff --check` on the changed docs.

## Decisions

- Placeholders instead of the members' names, as the user chose.
- "One page per CV" is done with a page break before each CV and a box at least one page tall; if a filled CV is longer, the box continues onto the next page.
- Personal Information (birthdate, civil status and so on) is left out of the template and asked as optional in the questions.

## Follow-ups

- Send the questions, fill in the answers and photos, then move the approved CVs into the appendix and delete the draft tab.
