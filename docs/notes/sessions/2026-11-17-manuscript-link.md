---
type: session-log
date: 2026-11-17
---

# Manuscript link recorded in the project context

## Task

Update the project context with the manuscript link the user supplied.

## What Changed

- Added the [manuscript Google Doc](https://docs.google.com/document/d/1mKYzY63egRP4O-SqVh5m8K8KSPlxyio1bxD5IyCZF78/edit?usp=sharing) link to the Manuscript section of [Docs Home](../../HOME.md).
- Added a Capstone manuscript row to the Authority Map in [Project Memory](../../PROJECT_MEMORY.md).
- Saved the link to the agent's persistent memory outside the repository.

## Verification

- `git diff --check` on the changed docs.
- Searched the repository for Google Doc links: all nine earlier notes already use the same document ID, so none pointed at an older document.

## Decisions

- Earlier dated notes were left unchanged. They link the same document, opened at its Temporary Revisions tab.
- The link is now recorded centrally so later sessions do not need it supplied again.

## Follow-ups

- None.
