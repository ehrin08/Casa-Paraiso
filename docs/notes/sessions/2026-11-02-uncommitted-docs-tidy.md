---
type: session-log
date: 2026-11-02
---

# Uncommitted docs tidy-up (2 November)

## Task

Review the uncommitted work on `redesign`, report its state, and finish it.

## What Changed

- Found three finished documentation-only sessions that had not been committed: manuscript reference comparison, chapter-only manuscript revision, and UAT form formal wording. Each had its note; the two manuscript sessions also had their documents under `docs/manuscript/`.
- The committed [SESSIONS](../SESSIONS.md) already listed the reference-comparison note, but the note itself was untracked, so that link was broken on GitHub until this commit.
- [HOME](../../HOME.md): moved the two manuscript documents into a new Manuscript section and put the Session Index link back at the top of Session Notes. Gave each session entry a summary and a date, and added the missing UAT form session link.

## Verification

- Checked that every relative link in HOME, SESSIONS and the new notes resolves to an existing file.
- `git diff --check` passed. Application tests, asset build and Docker startup were skipped because this is documentation-only work.

## Decisions

- Did not change the content of the three existing session notes or the manuscript documents. They describe external Google Docs/Forms work that cannot be checked from the repository.
- No project-memory update is needed, since routes, models, services and workflow are unchanged.

## Follow-ups

- Publish the UAT Google Form before sending it to participants (from the UAT session).
- Apply the reference review's priority checklists in a later manuscript revision.
