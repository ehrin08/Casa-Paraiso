---
type: session-log
date: 2026-11-02
---

# Chapter III diagrams

## Task

Check the Chapter III diagrams in the Temporary Revisions tab, which the owner suspected were outdated and incomplete. Then redraw the outdated figures and add the missing ones. The owner asked for code-drawn PNGs inserted into the Google Doc, and for a context diagram, DFD Levels 0/1, a sentiment flow, a use case diagram and a booking flow.

## What Changed

- Reviewed Figures 2–6 in the latest manuscript PDF export. Figure 2 (Agile) and the Figure 3 image are fine, but the sentence before Figure 3 calls it a context diagram. Figure 4 (Livewire/MySQL), Figure 5 (fixed visit and off-peak discount logic) and Figure 6 (`bookings`, `schedules`, `promotions` and `audit_logs`, none of which exist) are outdated, and their labels cannot be read.
- Added [diagram sources](../../manuscript/diagrams/README.md): `svgkit.py`, a fixed-layout SVG toolkit, and `build.py`, with one function per figure on a 720-unit canvas that matches the 6-inch text width.
- Built 11 PNG and SVG figures in the ignored `output/questionnaire/figures/diagrams/`:
  - context diagram
  - use case diagram
  - three-tier architecture
  - DFD Level 0
  - DFD Level 1 for payments and commissions
  - booking flow
  - sentiment flow
  - RFM reward flow
  - ERD in three parts
- Added the [Chapter III diagram guide](../../manuscript/2026-11-02-chapter3-diagrams.md), with upload steps, captions and what changed in the Doc. Linked it from HOME and from section F of the checklist.
- Edited the Temporary Revisions tab once the owner turned on the Google Docs connector:
  - Renumbered Chapter IV Figures 7–23 to 15–31 with tab-scoped find-and-replace, including the "Figures N–M" ranges.
  - In one guarded batch of 78 requests, replaced the old Figure 4–6 images with yellow insertion markers and retitled their captions.
  - Replaced each "retains the earlier…" paragraph with a description of the new figure.
  - Added Figures 4, 5, 7–10, 13 and 14 with HEADING_5 captions and explanatory paragraphs, and corrected the Figure 3 lead-in sentence.
- Inserted the 11 images with Claude in Chrome and the Docs API:
  - Docs has no file input, and a synthetic paste was ignored. A synthetic file drop on the editor was accepted, but it lands at an unpredictable place.
  - So each PNG was dropped in once. Each image was identified by its height at 432 pt wide.
  - The Docs API then placed each image at its marker, using the image's own `docs-images-rt` content URI, and the marker text and stray drops were deleted.
  - Figure 12c has the same aspect ratio as Figure 8, so it was dropped separately. It landed inside a cell of Table 1 and was removed from there.
- Follow-ups:
  - Redrew Figure 1 (Input-Process-Output) as `fig01_ipo` in `build.py` and swapped it in the same way.
  - Replaced its "baseline conceptual diagram" disclaimer paragraph and dropped "baseline" from the §2.6 intro.
  - Set keep-with-next on the image paragraphs of Figures 1 and 4–14.
  - Re-inserted Figures 4, 5, 7 and 9 at 520 pt high so each fits on a page with its caption.
  - Refreshed the Contents, List of Figures and List of Tables in the Docs editor, then restored their Times New Roman 13 pt double-spaced format through the API, because the refresh resets it.

## Verification

- Every element was checked against the code: `HybridSentimentClassifier`, `GeminiSentimentModel`, `SentimentRedactor`, `FeedbackSentimentUpdater`, `RfmScorer`, `RfmRuleEngine`, `RfmPromotionGenerator`, `RfmAddonVoucher`, `AppointmentWorkflow`, `CustomerBookingGuard`, `TherapistCommissionSynchronizer`, `config/casa.php` booking limits and `routes/web.php` role groups.
- The ERD relationships come from read-only `information_schema` foreign-key and column queries against the local MariaDB database. No data was changed.
- Each PNG was rendered and inspected, and label collisions were fixed over several passes.
- In the Doc:
  - A copy of the tab was saved before editing.
  - Each replacement count matched the references found beforehand.
  - A fresh read shows captions 1–31 in order and new paragraphs in Times New Roman 13 pt, justified with a first-line indent.
  - Inline images dropped from 23 to 20, the three replaced ones.
  - The other two tabs are byte-identical to the backup.
  - A fresh PDF export of Chapter III was rendered and checked visually.
- After the images went in:
  - The tab has 31 inline images (20 before plus 11) and none inside tables.
  - No "[Insert Figure" text remains, and each image sits directly above its own caption at 432 pt wide.
  - The other two tabs are unchanged.
  - Comparing the text with the pre-image read found one stray leading space before the title on page 1, typed while the editor had focus; it was deleted.
  - Drive's PDF export now fails with "File too large for export", so the layout was spot-checked in Chrome instead (Figure 6 renders sharply above its caption).
- After the follow-ups:
  - Each figure page in Chapter III was checked in Chrome at 50% zoom. Figures 5 and 7 had their captions pushed to the next page; the 520 pt cap fixed both, and every image now shares its page with its caption.
  - A final read shows 31 images and body text identical to the read taken after Figure 1, apart from the lists.
  - The lists have 55, 31 and 18 entries, and the other two tabs are unchanged.
- `git diff --check` passed. Documentation-only work, so no application tests, asset build or Docker startup check were run.

## Decisions

- Mermaid's automatic layout was tried first and dropped. The tall flowcharts would print at 3–5 pt, and the context, architecture and DFD layouts tangled.
- The sentiment flow shows no "needs review" step. `HybridSentimentClassifier` computes `review_state`, but nothing saves it, which matches the manuscript's statement that no automatic review queue exists.
- The ERD is split into three figures (12–14) rather than 12a–c. Change the numbering if the school template prefers sub-letters.
- Sentiment is Figure 10 and the promotion engine Figure 11, because the Gemini paragraph comes before the promotion paragraph in §3.4. The image files are named to match.
- No image was published outside Google. The owner chose the Chrome route over a temporary public GitHub branch, and the API inserts reuse the Doc's own image URLs. Google serves those at 2048 px wide, about 340 dpi at 6 inches.

## Follow-ups

- Refresh and restyle the three lists again after any later edit that moves pages (steps in the guide).
- Figure 1 (IPO, Chapter II) still needs its Process box updated (checklist F1).
- Regenerate the List of Figures last (checklist E1).
