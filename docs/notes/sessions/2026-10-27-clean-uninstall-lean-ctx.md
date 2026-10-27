---
type: session-log
date: 2026-10-27
---

# Clean uninstall lean-ctx

## Task

Remove all remaining lean-ctx integrations after the earlier skill-only removal.

## What Changed

- Ran the built-in clean uninstaller to remove MCP registrations, hooks, agent rules, skill directories and lean-ctx data.
- Removed the remaining global Codex instruction block and uninstalled the global `lean-ctx-bin` npm package, including its executable and command shims.
- Replaced the lean-ctx-specific workflow requirements in [AGENTS.md](../../../AGENTS.md) and the long-test guidance in [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md) with standard tool guidance.
- The earlier empty skill directory was removed successfully by the clean uninstaller.

## Verification

- `npm uninstall --global lean-ctx-bin` succeeded.
- `Get-Command lean-ctx -All` returns no commands, and no lean-ctx process remains.
- The former Codex skill, rules, package, configuration and local data paths are absent.
- Checked Codex configuration, global instructions, Claude settings, Bash profiles and the PowerShell profile for remaining active references; none remain in the checked files.
- The legacy Bash profile flagged by the uninstaller is empty.
- `git diff --check` passed. No application code changed, so application tests, asset builds and Docker startup were skipped.

## Decisions

- Preserved historical session notes and unrelated settings and application work.

## Follow-ups

- Restart Codex and open a new shell to clear integrations and instructions already loaded into the current processes.
