---
type: session-log
date: 2026-11-04
---

# Employee pay in the manuscript

## Task

Review the manuscript for how much employees (therapists) are paid.

## What Changed

- Created this session note and linked it in [SESSIONS.md](../SESSIONS.md).

## Verification

- Searched the text of `output/questionnaire/chapter-revision/final.pdf`: the paper defines therapist pay only as a commission, defaulted at 22% per completed service, and excludes salary, payroll deductions and benefits from scope.
- Confirmed `config/casa.php` sets `therapist_rate` to `0.22`.

## Decisions

- None.

## Follow-ups

- Chapter III still says "a fixed rate of 22%" while Chapter IV says the rate is configurable; consider aligning the wording.
