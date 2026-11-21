---
type: session-log
date: 2026-11-21
---

# AI tool names removed from commit messages

## Task

Remove the remaining mentions of the AI coding tool from commit messages on GitHub.

## What Changed

- Reworded three commit messages on `main` and `redesign` with `git filter-branch --msg-filter`:
  - the PR #1 merge is now "Merge pull request #1: RFM rules customization";
  - the November 19 record commit now says "the phone chat project";
  - the co-author commit is now "Stop adding the AI co-author line to commits".
- File names and file contents are unchanged, including README's AI assistance section that backs the AI Declaration.

## Verification

- `git log main redesign -i -E --grep='claude|anthropic'` returns nothing.
- Commit counts match the backups and `git diff` between each backup and its rewritten branch is empty.
- `git diff --check` on this note.

## Decisions

- Messages only; file names and contents were left alone.

## Follow-ups

- GitHub's PR #1 page keeps its source branch name; only GitHub's UI can change the PR title.
