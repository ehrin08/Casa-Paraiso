---
type: session-log
date: 2026-11-19
---

# AI declaration filled in

## Task

Fill in the DECLARATION OF AI USE tab of the manuscript Google Doc (an item on the work before November 20) without changing the form's wording.

## What Changed

- **Google Doc, DECLARATION OF AI USE tab:** filled 180 blank cells, boxes, and lines. Parts I and II record the title, adviser, client, AY 2026–2027 First Semester (to confirm), and the date (November 20, 2026). The ticked deliverables are the manuscript, the source code (GitHub link), and the technical documentation; "AI was used" is also ticked. Part III lists Claude (Anthropic) and ChatGPT (OpenAI). Part IV gives each component a Y/N, codes, a level, and what the AI did and what the team did. Chapters 1–3 and 5 are Level 3: the team wrote them and AI revised them, as the user confirmed. Chapter 4 and the front-end, back-end, and database code read "3 or 4 [confirm with adviser]" because Claude produced most of them. Part V logs five Claude entries from commits 3d6620c, 7b57cc6, a603ce4, 3b43855, and 869b028, plus one ChatGPT row in brackets. Part VI ticks only "reviewed and tested" and "listed in the README". The Remarks cover the AI-drafted sentiment test labels, the missing APA citation of the AI tools, Co-Authored-By trailers as attribution rather than authorship, and Gemini as a system feature. Part VIII has the member names. The signature, adviser, instructor, and faculty fields stay blank.
- The fields still in `[brackets]` are the section, the SR codes, the roles, the tool access type, the Claude start date and other users, the ChatGPT details, and the slides row. The user did not give these values.
- Gemini added at the user's request: Part III row 3 now reads Gemini (Gemini app) [model], Google, with access, members and period in brackets. Remarks item (5) now separates members' use of the Gemini app from the system's Gemini API feature. Meanwhile someone deleted the empty fourth row of the Part I member table; the fourth "None" row of the Part VIII signature table remains.
- Items the group must confirm are now highlighted yellow: 46 paragraphs in total. These are every entry with `[brackets]` or `[confirm]`, every "3 or 4" level, the Chapter 4 description, the ChatGPT log row, and the five unticked Part VI statements. The form's own Annex is not highlighted. The group should remove each highlight once its item is settled. A reread confirmed that the text did not change and that only those paragraphs carry the highlight.
- At the user's request, drafted Galang's Part I role from the project record: "Lead developer; deployment, technical documentation and manuscript revisions". It stays highlighted until the group confirms it. Belino's and Clavano's roles are still `[Role]`.
- Part III confirmed by the user and filled, with the highlight removed. All three tools were used by all three members from August 2026 to November 20, 2026:
  - Claude (Claude Code and claude.ai, Claude Opus 5.5 and Claude Fable 5.2), Anthropic, paid subscription.
  - ChatGPT Plus (GPT-6 models, including GPT-6 Sol), OpenAI, paid.
  - Google Antigravity (Gemini 3.1 Pro), Google, through the Google AI Pro one-year free trial offered to students. The user changed this row from the Gemini app to Antigravity. Remarks item (5) was updated to match. At the user's request, the Part IV front-end, back-end and database rows now read "Claude Code and Google Antigravity generated …".

  The ChatGPT and Gemini model names were taken from November 2026 secondary sources and confirmed by the user. Someone else removed the empty fourth row of the Part III table.
- The user said they orchestrated the build: they decided what was built and how. Galang's role now reads "Lead developer and system architect; planned and directed the AI-assisted build …". The Part IV architecture, front-end, back-end and database rows now say that Galang designed and directed the work and the AI tools generated code from it. The README credits Galang's direction. Levels stay "3 or 4 [confirm with adviser]", because the form grades how much AI output was kept, not who directed it.
- `README.md`: new "AI assistance" section, which supports the Part VI README tick.
- [Submission Timeline](../../manuscript/2026-11-17-submission-timeline.md): the AI Declaration item now lists what is still open.

## Verification

Reread the tab after the edit and compared it paragraph by paragraph with the read taken before the edit. All 180 changed paragraphs were intended entries, and no form text changed. The 15 blank paragraphs left are the signature, adviser, instructor, and faculty fields. `git diff --check` is clean.

## Decisions

- The form was filled in place, as the user chose. Unknown facts stay in brackets instead of being guessed.
- Where AI may have produced most of a component, the level reads "3 or 4" rather than a lower figure, because Part VII item 8 forbids understating AI use and Level 4 needs the adviser's approval.
- Collaborators were editing other tabs at the same time (the Test Cases Draft tab was removed), so the writes were sent without a revision lock. Positions were checked against a fresh read first.

## Follow-ups

- The group fills the bracketed fields and confirms the Part IV rows marked [confirm].
- Ask the adviser to settle Level 3 or 4 for the source code and Chapter 4.
- Add the APA reference for Claude and ChatGPT and a Chapter 3 sentence on AI use, then tick that Part VI item.
- The submission sheet asks for a Google Drive link to the source code; the declaration currently gives the GitHub link.
