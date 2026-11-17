---
type: session-log
date: 2026-11-17
---

# Task list at the end of every task

## Task

Add a working rule: after finishing a task, list the updated tasks so progress can be tracked.

## What Changed

- `AGENTS.md`, Task Report: added a **Tasks** part listing the tracked to-do list (done items checked, open items unchecked, next deadline), taken from [Defense Panel Revisions](../../manuscript/2026-11-10-defense-panel-revisions.md) and the [Submission Timeline](../../manuscript/2026-11-17-submission-timeline.md).
- Saved the same rule to the agent's persistent memory outside the repository.

## Verification

- `git diff --check` on the changed files.

## Decisions

- The list comes from the checklist files, which are updated first when a task changes an item, so the report and the files stay in step.

## Follow-ups

- None.
