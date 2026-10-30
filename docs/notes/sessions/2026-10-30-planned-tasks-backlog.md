---
type: session-log
date: 2026-10-30
---

# Planned tasks backlog

## Task

Record five requested features as planned tasks without implementing them: a pretrained XLM-RoBERTa sentiment model, star ratings instead of the dropdown, an optional name on feedback, a feedback pop-up for eligible customers, and prevention of troll appointments.

## What Changed

- Added [PLANNED_TASKS.md](../../PLANNED_TASKS.md), with a checklist for each task, the code each one touches today, and open questions.
- Linked it from the Core section of [HOME.md](../../HOME.md) and from [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md).
- No application code, migrations or tests changed.

## Verification

- Checked that the referenced classes, files and config keys exist: `HybridSentimentClassifier`, `LocalSentimentModel`, `SENTIMENT_CLASSIFIER_MODE` in `config/sentiment.php`, `FeedbackRequest`, `CustomerAppointmentStoreRequest` and the customer feedback form partial.
- `git diff --check` on the changed docs.
- Documentation-only, so no tests, asset build or Docker run.

## Decisions

- The archived `TASKS_TO_DO.md` is finished, so the backlog is a new top-level doc rather than an archived file reopened.
- The XLM-RoBERTa task notes that Hostinger shared hosting cannot run a Python worker, so the inference approach is an open choice to settle before building.

## Follow-ups

- Task 3: confirm whether "optional name" means anonymous to staff or a free-text display name.
- Task 5: choose which troll-booking safeguards to build.
