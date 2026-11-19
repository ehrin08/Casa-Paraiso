---
type: session-log
date: 2026-11-19
---

# Test cases drafted in the standard table format

## Task

Rewrite the manuscript's test cases in the standard table format (ID, Test Case Title, Test Steps, Test Data, Expected Results, Actual Results).

## What Changed

- Google Doc: added the **Test Cases Draft** tab after CV Draft. It holds a short reviewer note, the three intro paragraphs and captions from Section 3.10.3, and Tables 8–10 rebuilt with six columns: 17 administrator, 8 therapist and 10 customer cases (35 in all).
  - ID, title, steps, expected and actual results are copied from the main tab. The title and the numbered steps, which shared one cell, are now separate columns. The only text change is a doubled period removed from ADM-08's actual result.
  - Test Data is new: a short description of the inputs each case already names (for example, "Completed, fully paid test service; configured 22% therapist commission rate"). No passwords, emails or invented values.
  - Formatting matches the original tables: Times New Roman, bold header and ID and title columns, centred header and ID, horizontal rules only (1.2 pt top and bottom, 0.6 pt between rows), header row repeated on each page, and rows kept from breaking across pages. Column widths 50, 84 and 4 × 74.5 pt (432 pt in all, as before).
  - Table text is 11 pt, not the original 13 pt, at the user's choice: at 13 pt, six columns in the same width broke words such as "appointment" mid-word. Captions and intros stay 13 pt.
- [Submission Timeline](../../manuscript/2026-11-17-submission-timeline.md): both test-case items note the draft and stay open until review.

## Live-site Test Data (same day)

The user asked for actual test data from https://casaparaiso.site, read only (no records created) and written only to the draft tab.

- Read signed out: five treatments (Aurora Breeze 2 h PHP 849.00, Gaia Touch 1 h PHP 499.00, Hestia Warmth 1 h 30 min PHP 749.00, Pedicure 30 min PHP 189.00, Tethys Flow 1 h PHP 649.00), add-ons (30-Minute Back Massage PHP 299.00 and +30 min; Hot Compress, Hot Stone, Ventosa and VIP Room PHP 200.00 each), open every day 1:00 PM to 11:00 PM, and admin pages redirecting to the sign-in page.
- The admin pages could not be read: after the user's sign-in, both the built-in browser and Claude in Chrome still redirected `/admin/settings` to `/login`. The user chose to continue with public values only.
- Test Data now uses these values for ADM-03, ADM-05, ADM-06, ADM-07, CUS-01, CUS-04, CUS-05 and CUS-07. Examples are marked "e.g." and the live catalogue is named as such; no claim is made about which treatment a past test used. The other 27 rows keep their descriptions, and the reviewer note says so.
- Verified: only those eight Test Data cells and one reviewer-note sentence changed; all other cells and the other four tabs are unchanged; no emails or phone numbers in the tab. No form was submitted on the live site.
- Follow-up: once an admin session works in the browser, read the settings, test appointments, payments, commissions, rewards, reports and feedback to fill the other 27 rows.

## Moved into the main tab (same day)

- Main tab, Section 3.10.3: the old four-column Tables 8–10 were deleted and replaced by the six-column tables from the draft. The cell text is identical to the draft, including the eight live-site Test Data cells. Captions, headingIds, intros and the 1 pt spacer paragraphs before each table were kept; the extra blank paragraph each insert left after the table was removed. Formatting is the same as the draft's.
- The lead-in sentence now reads "Tables 8–10 present each test case's ID, title, steps, test data, expected results and actual results".
- The Test Cases Draft tab was deleted at the user's request; version history can restore it.
- Verified by re-reading: the main tab outside the three tables matches the earlier read exactly (610 elements, lead-in phrase aside); no four-column test table remains; the other tabs are unchanged; the doc now has Main tab, DECLARATION OF AI USE, Chapter IV Draft and CV Draft.

## Verification

- Re-read the doc: the new tab has three 6-column tables with 18, 9 and 11 rows; IDs run in order; every Expected and Actual cell matches the main tab's text (except the ADM-08 period); no empty cells; Test Data has no emails or passwords.
- The text of the Main tab, DECLARATION OF AI USE, Chapter IV Draft and CV Draft is identical to before the edit.
- Column widths, padding, header-row repeat and fonts were read back. The layout was not checked in a rendered export, because the doc exports to about 600 pages.
- `git diff --check` on the changed docs.

## Decisions

- Kept three role tables (Tables 8–10) rather than one combined table, so the chapter's cross-references stay valid.
- Drafted in a review tab; the main tab is unchanged until the draft is approved.
- Test Data describes inputs rather than exact values, because the doc does not record the exact data used.

## Follow-ups

- Review the tab; replace Test Data with exact values if the group kept them; then copy the tables into Section 3.10.3 and delete the tab.
- There are no receptionist test cases, although the system has a receptionist workspace.
