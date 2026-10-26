---
type: session-log
date: 2026-10-26
---

# Obsidian setup and task workflow

## Task

Set up Obsidian for the project and make every future task start from, and record its context in, the Obsidian notes.

## What Changed

- The `C:\Obsidian` vault (outside the repo) has a `Casa Paraiso` junction to `docs/`, templates in `Templates/` (Decision, Task Note, Session Log), relative Markdown link settings, `./assets` attachments, and a bookmark to the docs home.
- Added [HOME.md](../../HOME.md) as the documentation index, with an inline Mermaid architecture diagram.
- [system-architecture.mmd](../../system-architecture.mmd) now names MariaDB 10.11 LTS instead of MySQL 8.4.
- `AGENTS.md` now requires reading `docs/HOME.md` and the latest session notes at task start, and writing a session note plus index entry at task end.
- [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md) Authority Map points to the docs index and session history.
- Added [SESSIONS.md](../SESSIONS.md) and this note.

## Verification

- Every relative link in HOME.md, SESSIONS.md, and this note resolves.
- `git diff --check` is clean.
- Documentation only, so app tests, the asset build, and Docker startup were skipped per `AGENTS.md`.

## Decisions

- Session notes live in git under `docs/notes/`, so every machine and agent sees them. Vault configuration and templates stay outside the repo.
- Relative Markdown links instead of wikilinks, so notes render on GitHub.

## Follow-ups

- [x] Commit the Obsidian workflow changes (committed alone; other uncommitted work in the tree was left untouched).
