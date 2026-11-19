---
type: session-log
date: 2026-11-19
---

# Claude chat project for phone work

## Task

Set up a way to keep working on the manuscript from the Claude phone app when the PC is away or off.

## What Changed

- Google Drive: created the [Casa Paraiso Phone Brief](https://docs.google.com/document/d/1auHiXUxHULno3ZdRGbilQiIC9O_RDo1P1HxJ6RwzCGg/edit) Google Doc. It holds the manuscript link and tabs, formatting facts, editing rules, the open tasks before November 20, a Done list, a Work log with a "Synced to repo" flag, and the CV questions for the group.
- [Claude Chat Project for Phone Work](../../manuscript/2026-11-19-claude-chat-project.md): the project instructions to paste, the phone setup steps, and the sync-back steps. Linked from [Docs Home](../../HOME.md).
- No manuscript or application change.

## Verification

- Read the brief back through the Drive connector. Its headings, lists, links and text match what was sent, and it holds no emails, phone numbers or credentials.
- `git diff --check` on the changed docs.
- The chat project itself was not created or tested: there is no tool here that creates claude.ai chat projects, so the user creates it on the phone from the setup steps.

## Decisions

- The shared state lives in a Google Doc rather than in project files. The chat project can read and edit a Google Doc through the connector, while a project file is a fixed copy it cannot update.
- The open tasks in the brief were copied from the Submission Timeline and Defense Panel Revisions notes. The repo stays the record; the brief is the working list while away.
- Remote Control stays the first choice when the PC is on, because it keeps the repository and every connector.

## Follow-ups

- The user creates the project, pastes the instructions, turns on the Google Drive connector and checks that "What's next?" reads the brief.
- At the PC, "sync the phone log" folds unsynced Work log entries into the repo checklists and a session note.
- Keep the brief's Open tasks current whenever a manuscript task changes at the PC.
