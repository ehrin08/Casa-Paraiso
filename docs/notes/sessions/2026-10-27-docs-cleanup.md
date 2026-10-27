---
type: session-log
date: 2026-10-27
---

# Docs and repo cleanup

## Task

The user wanted redundant files cleaned up now that the docs are read through Obsidian. The vault is a junction to `docs/` and has no copies of its own, so the cleanup targeted stale docs, retired deploy files and local clutter in the repo.

## What Changed

- **Archived (git mv to `docs/archive/`):** `IMPLEMENTATION_ROADMAP`, `TASKS_TO_DO`, `CRUD_DATA_REPAIR_PLAN`, `CRUD_REMEDIATION_CHECKLIST`, `FRONTEND_CONSISTENCY_AUDIT` and `RENDER_ORACLE_DEPLOYMENT`. The audit's links were repointed, and a note explains that its local `output/` evidence is gone.
- **References:**
  - [HOME](../../HOME.md) has a new Archive section.
  - [AGENTS.md](../../../AGENTS.md) swaps the roadmap for `HOSTINGER_DEPLOYMENT.md` in the primary documents and marks the archive as history only.
  - The README now points to `docs/archive`.
  - [PROJECT_MEMORY](../../PROJECT_MEMORY.md) records the removals.
- **Stale guidance fixed:** the [BRAND_UI_GUIDE](../../BRAND_UI_GUIDE.md) About paragraph no longer says "do not derive public people from account records".
- **Removed from git:** `render.yaml`, `Dockerfile.render` and `docker/app/render-entrypoint.sh`.
- **Sent to the Recycle Bin (untracked, about 50 MB):**
  - the old screenshot folders and loose images in `output/`;
  - `tmp/pdfs` and the `.casa-preview/*.log` files;
  - `test_browser_submit.php`;
  - the find-skills leftovers: `.agents/`, the `.claude/skills` junction and `skills-lock.json`.

## Verification

- Every relative Markdown link in `README.md`, `AGENTS.md` and `docs/` resolves; only the archived audit's removed evidence links are excepted.
- No current doc names a moved file outside `archive/`.
- `git diff --check` is clean, and `compose config` is valid.
- `output/hostinger-release/` (release zips and the `.ssh` deploy key) and the tracked `output/markdown/` are intact.
- No application code changed, so no test run was needed.

## Decisions

- Kept `docker/mysql/init`: `MARIADB_MIGRATION.md` mounts it for the MySQL rollback compose file. It was briefly staged for removal and then restored.
- Kept the `.casa-preview` scripts and `session.lock`, because another preview workflow may be using them; only its logs were removed.
- Kept `REDESIGN_MODERN_RESORT.md` and the sentiment docs as current.

## Follow-ups

- Empty the Recycle Bin once nothing from `output/` is missed.
- The uncommitted lean-ctx session notes from the other session are still not committed.
