---
type: session-log
date: 2026-11-01
---

# Sentiment test set tooling (Task 1 / T1)

## Task

Pick up the last open item of Task 1 in [PLANNED_TASKS.md](../../PLANNED_TASKS.md): build the labelled locked test set and the evaluation gate for the XLM-RoBERTa driver.

## What Changed

- Checked the local database first. It holds 2 feedback comments and no annotations, too few for a real-feedback test set. The user chose a curated set with two-person CSV labelling.
- New `resources/sentiment/eval/curated-v1/`:
  - `items.csv`: 240 fictional comments, 80 each in English, Tagalog and Taglish, with ratings, topics and hard-case tags (97 rows: mixed, negation, sarcasm, misspelling, rating_conflict). Rows are shuffled with a fixed seed.
  - `suggested.csv`: the author's draft labels (90 positive, 75 neutral, 75 negative), for the adjudicator only.
  - `labels-a.csv` and `labels-b.csv`: blank sheets with a UTF-8 BOM so they open cleanly in Excel.
- New [Labelling Guide](../../sentiment/LABELING_GUIDE.md), linked from [HOME](../../HOME.md).
- New `scripts/sentiment_agreement.py` (Cohen's kappa overall and per language, a confusion table, and `adjudication.csv` with agreed finals pre-filled) and `scripts/lock_sentiment_testset.py` (refuses blank finals and never overwrites; writes `locked-test.csv` and prints its SHA-256). Both use only the standard library.
- `scripts/evaluate_sentiment_model.py` changes:
  - It now uses only the standard library (no scikit-learn).
  - It checks that the prediction and dataset row counts match.
  - It adds per-language and per-tag breakdowns.
  - New `--baseline` option applies the governance gate (macro-F1 ≥ 0.80, negative recall within 5 points of rules, fewer Taglish and mixed errors than rules) and exits 2 on failure. The `classification_report` key keeps its sklearn-style shape.
- Docs: a `curated-v1` source in the [manifest](../../sentiment-dataset-manifest.json) (status `curated-test-set-awaiting-labels`, `locked_sha256` still null). Also updated: the workflow and commands in the [implementation plan](../../SENTIMENT_ANALYSIS_IMPLEMENTATION_PLAN.md), Task 1 sub-steps in [PLANNED_TASKS.md](../../PLANNED_TASKS.md), and [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md).

## Verification

- Agreement script on simulated labels (24 rows flipped): kappa 0.850, which matches the hand calculation (0.900 − 0.335) / (1 − 0.335). A blank sheet is rejected with one summary line, and rerunning refuses to overwrite `adjudication.csv` without `--force`.
- Lock script: refuses 24 blank finals, locks after they're filled (240 rows, `split=test`), and refuses to lock again.
- Evaluator: the 4-row hand case gives macro-F1 0.778 and weighted F1 0.750, both correct. The gate reports FAIL with exit 2, and a short predictions file is rejected.
- All self-tests used throwaway copies in the session scratchpad. Nothing simulated was written to the repository.
- `php artisan test --filter=Sentiment`: 21 passed (117 assertions). `casa:sentiment-predictions` read `items.csv` with `--driver=rules` and wrote 240 rows. That temporary output was deleted.
- No PHP, view or asset changes, so the full suite, asset build and Docker startup were not rerun. Checked with `git diff --check`.

## Decisions

- Curated, fictional data instead of waiting for real feedback. Results will show how the model handles representative cases, not how it performs on real customers. The gate should be repeated on redacted real feedback later.
- Labellers work blind. The draft labels go only to the adjudicator.
- The English, Tagalog and Taglish items partly mirror each other's scenarios, so language effects can be compared, at the cost of some correlation between rows.
- No scores were computed against the draft labels, so the set stays unused until it is locked.

## Follow-ups

- The user and a second person fill in `labels-a.csv` and `labels-b.csv`.
- Then: run the agreement script, adjudicate, lock the set, record the SHA-256, and run the rules, local and xlmr predictions with the gate. Run in `shadow` mode only if xlmr passes. Production still needs a host, license confirmation and privacy wording.
- Update the T1 row in the tracker sheet.
