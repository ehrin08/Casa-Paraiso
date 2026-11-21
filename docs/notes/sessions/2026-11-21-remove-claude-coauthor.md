---
type: session-log
date: 2026-11-21
---

# Claude co-author line removed from git

## Task

Remove the `Co-Authored-By: Claude` line from commit messages, both in future commits and in the published history.

## What Changed

- History: rewrote every commit message on `main` and `redesign` with `git filter-branch --msg-filter`, deleting `Co-Authored-By: Claude ...` lines (Opus 5.5 and Fable 5.2 variants). File contents are unchanged; commit hashes changed.
- Backups of the old tips kept as local branches `backup/main-pre-coauthor` and `backup/redesign-pre-coauthor`.
- Not rewritten: the `archive` remote and the local `claude/*` worktree branches.
- `AGENTS.md`: Verification and Delivery now says not to add an AI co-author line to commits.
- Claude Code user settings: commit attribution turned off.

## Verification

- `git log main redesign -i --grep="Co-Authored-By: Claude"` returns nothing.
- Commit counts match the backups (main 146, redesign 153) and `git diff` between each backup and its rewritten branch is empty.
- `git diff --check` on the changed docs.

## Decisions

- Kept the `main` and `redesign` rewrite to the published branches only; old worktree branches stay as they were.
- Force-push to `origin` with `--force-with-lease`, since the history is public on GitHub.

## Follow-ups

- Delete the two backup branches once the pushed history looks right.
- Any other clone of the repository must be re-cloned or hard-reset to the new `origin` branches.
