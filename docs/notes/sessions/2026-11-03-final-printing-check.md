---
type: session-log
date: 2026-11-03
---

# Final printing check

## Task

List what the manuscript's Temporary Revisions tab still needs before final printing, then do the abstract and the table of contents.

## What Changed

Nothing in the Google Doc or the application. This note records the findings. They come from the 3 November post-SUS snapshot (`output/questionnaire/sus-update-after.json`), checked against the [Temporary Revisions checklist](../../manuscript/2026-11-02-temporary-revisions-checklist.md).

Already done since the checklist was written: test-result contradictions (A), NLP wording (B), evaluation results for UAT/SUS (C, E6), Chapter III diagrams and Figure 1 (F1, F3 diagrams), acknowledgments and dedication, and a November 2026 title-page date. All 149 suggestions are accepted.

Still needed before printing:

1. **Abstract:** the tab has none. Add it before the Table of Contents.
2. **Appendices:** "Appendix A. System Evaluation by an IT Experts" and "Appendix B. Evaluation by Users" are empty headings. Fill them in, and fix the grammar of Appendix A's title.
3. **Missing screenshots:** Chapter IV has no QR attendance figure for Objective 1.4, and no feedback-form, admin Feedback page or operating-hours figure. These were captured on 2 November in `output/questionnaire/figures/2026-11-02/` but never inserted. Figure 24's text still calls its screenshot's 1 PM–midnight label historical, so check that the recaptured replacements were swapped in.
4. **Open comments:** 25, including Jericho's "draft", "for review" and "need i update" threads plus 13 revision-log comments. Resolve or delete them.
5. **Small text fixes:** §1.2 "and allow authorized management" should be "allows". The acknowledgments mix "we" with "the researchers" and "their capstone adviser". The Ziuziun et al. (2025) reference ends with a truncated DOI.
6. **Last step:** refresh the Table of Contents, List of Figures and List of Tables, restore their Times New Roman 13 pt double-spaced format, and export only the Temporary Revisions tab to PDF.

### Update: abstract and lists (same day)

The user approved the appendix data as it is and limited this pass to the abstract and the table of contents. A fresh read of the live doc showed that collaborators had already done some items since the morning snapshot:

- **Abstract:** an ABSTRACT page with keywords now sits before the Table of Contents. Its figures match the body: five IT experts at 4.66, 17 end-users, SUS 80.29, acceptance 4.51 and the provisional Gemini macro-F1 of 0.993. It was left unchanged.
- **Screenshots:** a new §4.1.6 Therapist QR Attendance has Figures 26–28. Their images (`casa-fig-attendance-*.png` in Drive) were inserted while this check ran. Chapter IV figures now run to Figure 32.
- **Other changes:** the "Copy of Temporary Revisions" tab and "Tab 4" are gone, the acknowledgments are in the third person, and Appendix A's title now reads "by IT Experts".

Edits made, all through the Docs connector with the revision locked, except the refresh:

- The three new image paragraphs had come in as HEADING_5, so they would have appeared as blank List of Figures entries. They were set to the same style as the other figure images: NORMAL_TEXT, centered, keep-with-next, 6 pt above and 3 pt below.
- The Table of Contents, List of Figures and List of Tables were refreshed with their own buttons in the Docs editor, in the user's Chrome. Page numbers after Figure 25 moved by 1–2 pages, and References is now on page 150.
- The three lists were set to Times New Roman 13 pt, double-spaced, not bold, black with no underline. The six chapter and References lines in the Contents are bold.

## Verification

Text was extracted from the snapshot with a scratch script. The counts covered the old "24 Passed / Partially verified / Not executed" wording (0), "Abstract" (0), figure captions (1–29), table captions (1–25) and comment and suggestion status.

## Decisions

None. The user had already chosen to leave the SUS selection wording and the "Copy of Temporary Revisions" tab as they are.

A re-read after the edits found no text change anywhere in the tab apart from the three image paragraph styles. The lists have 56, 32 and 25 entries, all in Times New Roman 13 pt with 200% line spacing. Only the 6 Contents chapter lines (12 runs) are bold. The Contents page was also checked visually in the editor.

## Follow-ups

- Items 4 and 5 (comments, small text fixes) were left to the user.
- Refresh and restyle the three lists again after any edit that moves pages, then export only the Temporary Revisions tab to PDF.
