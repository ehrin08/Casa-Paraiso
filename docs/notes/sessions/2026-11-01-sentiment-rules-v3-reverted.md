---
type: session-log
date: 2026-11-01
---

# Sentiment rules v3 experiment, reverted (Task 1 / T1)

## Task

The user saw gate failures and asked for a different sentiment approach. The goal was to find why every classifier failed, try a better approach, and gate it fairly.

## What Changed

- Diagnosis: on `curated-v1`, about half of the rules' errors came from the stars overriding the text, while the labels describe the text only. Most of the rest were mixed or hedged comments and missing words. XLM-RoBERTa fails because its Twitter checkpoint misreads Tagalog.
- Tried a rules v3: read the text first and use the stars only when the text is silent, plus contrast, hedge, sarcasm, verdict-word and Tagalog-form handling. It was tuned on a new 90-row practice set, `resources/sentiment/eval/curated-v1-dev/`.
- After v3 failed negative recall on `curated-v1`, wrote and locked a new held-out set, `resources/sentiment/eval/curated-v2/` (150 rows, AI-drafted labels), before fixing anything. Then gated every classifier on it once.
- Results on `curated-v2` (macro-F1):
  - v2: 0.714
  - v3 (text and stars): 0.710
  - v3 (text only): 0.476
  - local: 0.725
  - xlmr: 0.536

  All failed the gate.
- At the user's request, every application and test change was reverted. The net code diff is zero. The kept files are the two new evaluation sets and these documents:
  - [Implementation Plan](../../SENTIMENT_ANALYSIS_IMPLEMENTATION_PLAN.md) (results section)
  - [Dataset Manifest](../../sentiment-dataset-manifest.json)
  - [PLANNED_TASKS.md](../../PLANNED_TASKS.md)
  - [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md)

## Verification

- `lock_sentiment_testset.py` locked `curated-v2` with SHA-256 `1070af4eaa35a66eaea2f2587acc5787df2531da061961217fbe36298018bcb9`.
- The v2 baseline ran from the committed classifier and config in a temporary, git-ignored copy. It reproduced `c1-rules.csv` exactly. The copy was deleted afterwards.
- The xlmr predictions came from the Compose `sentiment` profile, with `SENTIMENT_XLMR_URL` passed per command (`--env`). `.env` was not changed.
- Predictions and reports are in `storage/app/private/c2-*` and `d1-*`, which git ignores.
- After the revert, the full `php artisan test` passed: 606 tests. An earlier full run had died with a PHP segfault (signal 11) after `LandingTeamChartTest` while files were being edited. The tests around that point passed on their own, and the rerun was clean, so it looks intermittent.
- The net code diff is zero, so no asset build or Docker startup check was needed. `git diff --check` is clean.

## Decisions

- Production stays on the v2 rules with the `local` driver. v3 tied v2 on unseen text and found fewer negatives, so it was not promoted. The user chose to revert rather than keep v3 behind a switch.
- The user wants the stored label to combine the text and the stars. The test labels remain text-only.
- `curated-v1` is no longer clean, because its errors were inspected while tuning. Future gates should use `curated-v2`.
- Every label is AI-drafted by the same assistant that wrote the comments and the rules, so all results are provisional.

## Follow-ups

- Keyword rules level off around macro-F1 0.71 on new text. Getting past that needs a model trained on labelled Tagalog and Taglish reviews, for example a fine-tuned XLM-RoBERTa. That also needs a training set, an inference host and privacy wording.
- Human labels for `curated-v2`, or redacted real feedback, would make the gate trustworthy.
- Update tracker row T1 in the sheet.
- The `sentiment` container is still running. Stop it with `./scripts/casa-docker.ps1 compose --profile sentiment stop sentiment`.
