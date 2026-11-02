---
type: session-log
date: 2026-11-02
---

# Short task report rule

## Task

Add a rule so every task ends with a short report: whether it succeeded, any errors, and short suggestions.

## What Changed

- `AGENTS.md`: added a **Task Report** section with three parts: Result (Success, Partial or Failed), Errors, and Suggestions (at most three). The old "report the outcome" line in Efficient Agent Workflow now points to that section.

## Verification

- Documentation only. `git diff --check` passed on the changed files.

## Decisions

- `docs/PROJECT_MEMORY.md` already sends agent workflow rules to `AGENTS.md`, so it was left unchanged.

## Follow-ups

- None.
