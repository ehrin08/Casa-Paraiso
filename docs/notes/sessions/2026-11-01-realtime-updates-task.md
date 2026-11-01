---
type: session-log
date: 2026-11-01
---

# Realtime updates task recorded (Task 9)

## Task

Record "apply realtime changes instead of always reloading" as a planned task. Nothing was implemented.

## What Changed

- [PLANNED_TASKS.md](../../PLANNED_TASKS.md) has a new Task 9, Apply Changes in Real Time Instead of Reloading. It describes how updates reach the page today (opt-in Turbo, normal form redirects, 30-second booking-count polling, the attendance scanner reload) and a checklist: agree the scope, choose a transport that works on Hostinger shared hosting, keep normal Laravel requests for changes, keep booking capacity checks transactional, replace the scanner reload, and update docs and tests.

## Verification

- `git diff --check` on the changed docs.

## Decisions

- Recorded as a specification only; scope and transport are open decisions for the user.

## Follow-ups

- Add Task 9 (and Tasks 7 and 8) to the tracker sheet if the owner wants them there.
