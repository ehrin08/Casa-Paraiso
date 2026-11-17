---
type: session-log
date: 2026-11-17
---

# Task report shows only the tasks to resolve

## Task

New rule from the user: display only the tasks instead of the whole timeline.

## What Changed

- `AGENTS.md` Task Report: the **Tasks:** part now lists only the tasks to resolve (the "Work before November 20" list in the [Submission Timeline](../../manuscript/2026-11-17-submission-timeline.md)), checked or unchecked, with the next deadline on one line. The deadline and requirement lists are left out unless the user asks for them.

## Verification

- `git diff --check` passed. Documentation-only, so no application checks.

## Decisions

- Kept the next deadline as a single line so the report still says when the tasks are due.

## Follow-ups

- None.
