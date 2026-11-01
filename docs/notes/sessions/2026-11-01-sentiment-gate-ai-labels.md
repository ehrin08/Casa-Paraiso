---
type: session-log
date: 2026-11-01
---

# Sentiment gate on AI-drafted labels (Task 1 / T1)

## Task

Finish Task 1 in [PLANNED_TASKS.md](../../PLANNED_TASKS.md): lock the curated test set and run the evaluation gate. Nobody was available to fill in the two label sheets, so the user chose to use the AI-drafted labels, openly disclosed.

## What Changed

- New `resources/sentiment/eval/curated-v1/adjudication.csv`, built straight from `suggested.csv`, with `source=ai-draft` on every row. `labels-a.csv` and `labels-b.csv` are still blank. `sentiment_agreement.py` was not run.
- New `locked-test.csv` (240 rows: 90 positive, 75 neutral, 75 negative), SHA-256 `b61daedfd07ac9e2b8abdb8803e59b1ddafce01d22b102570cedbfc4b0ee8753`.
- Predictions and reports in `storage/app/private/` (ignored by git): `c1-rules`, `c1-local` and `c1-xlmr` (`.csv`, plus a `.json` report for each).
- Provisional results:
  - rules: macro-F1 0.839
  - local: 0.827 (gate failed)
  - xlmr: 0.694 (all four gate checks failed)
- Docs updated:
  - [manifest](../../sentiment-dataset-manifest.json): status `locked-ai-drafted-labels`, the hash and the provenance disclosure
  - [implementation plan](../../SENTIMENT_ANALYSIS_IMPLEMENTATION_PLAN.md): a results table
  - [Labelling Guide](../../sentiment/LABELING_GUIDE.md)
  - [PLANNED_TASKS.md](../../PLANNED_TASKS.md)
  - [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md)

## Verification

- The lock script printed the hash, and `sha256sum` matches the manifest.
- The evaluator accepted 240 predictions per file. The xlmr rows have varying confidences, so they are real model outputs, not the rules fallback.
- The xlmr run used the Compose `sentiment` profile, with `SENTIMENT_XLMR_URL` passed per command (`--env`). `.env` was not changed.
- `php artisan test --filter=Sentiment` passed. `git diff --check` is clean. There were no PHP, view or asset changes, so the full gate was not needed.

## Decisions

- The labels are AI-drafted and unverified, and there is no kappa. Every result is marked provisional. The same assistant wrote both the comments and the labels, which may favour the keyword rules.
- xlmr stays out of `shadow`. Production stays on `rules` with the `local` driver.

## Follow-ups

- Optional: build a human-labelled `curated-v2` or use redacted real feedback, then rerun the gate.
- Update tracker row T1 in the sheet.
- If xlmr is reconsidered, it still needs model license confirmation, an inference host and privacy wording.
- The `sentiment` container is still running. Stop it with `./scripts/casa-docker.ps1 compose --profile sentiment stop sentiment`.
