---
type: manuscript-guide
date: 2026-11-02
---

# Chapter III diagrams: replacements and additions

Chapter III of the Temporary Revisions tab now has eleven redrawn or new figures, drawn from the current code and the local MariaDB schema rather than copied from the LipaGo reference. The images, captions, explanatory paragraphs and renumbering are all in the Google Doc.

- **Images:** `output/questionnaire/figures/diagrams/` (ignored by git). Each figure has a PNG 2880 px wide (about 480 dpi at 6 inches) and a vector SVG.
- **Sources:** [diagrams/](diagrams/README.md). Run `build.py` to regenerate the images.

## Figures in the Doc

Each image sits directly above its caption, with keep-with-next set so the two stay on one page. Most images are at the full 6-inch text width (432 pt). The tall ones (Figures 4, 5, 7 and 9) are capped at 520 pt high, which makes them narrower, so that each fits on a page with its caption. Google stores the images at 2048 px wide, about 340 dpi in print.

Figure 1 (Input-Process-Output, §2.6) was also redrawn as `01-ipo.png`. Its "baseline" disclaimer paragraph was replaced with a description of the current inputs, processes and outputs. To replace one later, regenerate it with `build.py`, select the old image in the Doc, and choose **Image options › Replace image › Upload from computer**.

| No. | Caption | File | Section |
| --- | --- | --- | --- |
| 4 | Context Diagram of the Proposed System | `04-context-diagram.png` | §3.3 |
| 5 | Use Case Diagram | `05-use-case-diagram.png` | §3.3 |
| 6 | Three-Tier System Architecture | `06-three-tier-architecture.png` | §3.4 |
| 7 | Data Flow Diagram Level 0 | `07-dfd-level-0.png` | §3.4 |
| 8 | Data Flow Diagram Level 1: Record Payments and Commissions | `08-dfd-level-1-payments.png` | §3.4 |
| 9 | Customer Booking and Visit Flow | `09-booking-flow.png` | §3.4 |
| 10 | Sentiment Analysis Flow | `10-sentiment-flow.png` | §3.4, after the GeminiSentimentModel paragraph |
| 11 | Rule-Based Promotion Engine Logical Flow | `11-rfm-reward-flow.png` | §3.4 |
| 12 | Entity-Relationship Diagram: Accounts, Staff and Attendance | `12a-erd-accounts-staff-attendance.png` | §3.7 |
| 13 | Entity-Relationship Diagram: Appointments and Payments | `12b-erd-appointments-payments.png` | §3.7 |
| 14 | Entity-Relationship Diagram: Feedback, Rewards and Operating Hours | `12c-erd-feedback-rewards-settings.png` | §3.7 |

Figures 2 (Agile) and 3 (Existing Manual Process) are unchanged.

## What was wrong before

| Old figure | Problem |
| --- | --- |
| 3. Existing Manual Process | The sentence before it called it a "context diagram", but it is a sequence diagram. That sentence is now corrected. |
| 4. Three-Tier System Architecture | It showed Laravel Livewire and MySQL. It had no Gemini, Google sign-in, mail, polling, attendance or Hostinger, and its labels could not be read. |
| 5. Rule-Based Promotion Engine Logical Flow | It used fixed `visits > 5` and off-peak discount logic that the system never had. |
| 6. Entity-Relationship Diagram | It showed `bookings`, `schedules`, `promotions` and `audit_logs`, none of which exist, and left out about 30 real tables. |
| (missing) | There was no context diagram, use case diagram, data flow diagram, booking flow or sentiment flow. |

## What changed in the Doc (2026-11-02)

- **Chapter IV renumbered.** Figures 7–23 are now 15–31. Every caption and in-text reference was updated, including the ranges, which now read "Figures 17–31", "Figures 26–27" and "Figures 28–29".
- **Old figures replaced.** The old Figure 4, 5 and 6 images were replaced with the redrawn diagrams. Each "retains the earlier…" paragraph was replaced with a description of the new figure.
- **New figures added.** Figures 4, 5 and 7–10, and the second and third ERD parts (Figures 13–14), were added with their images, captions in the existing HEADING_5 style, and explanatory paragraphs in the body style.
- **Contents, List of Figures and List of Tables refreshed.** Each was refreshed with its own button in the Docs editor. Refreshing resets the formatting to Docs' default, so all three were set back to Times New Roman 13 pt, double-spaced, with the Contents chapter lines in bold. The List of Figures now lists Figures 1–31.
- **Not changed:** the other two tabs.

A copy of the tab taken before the edit is in `output/questionnaire/diagram-revision/doc-before.json` (ignored by git).

## Still to do

- Refresh the three lists again after any later edit that moves pages: click inside each list, press its refresh button, then restore the Times 13 pt double-spaced format.
- The Doc is now too large for Drive's PDF export, so check layout in Google Docs itself or with File › Download › PDF.
