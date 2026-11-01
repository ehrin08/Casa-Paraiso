---
type: session-log
date: 2026-11-01
---

# XLM-RoBERTa removal (Task 1 / T1)

## Task

Gemini replaced XLM-RoBERTa as the sentiment model, so the user asked to clean XLM-RoBERTa out of the project. They chose to keep the dated history and to delete the Docker container, image and model volume.

## What Changed

- **Code:** removed `App\Services\XlmRobertaSentimentModel`, the `xlmr` branch in `HybridSentimentClassifier`, and the `xlmr` driver in `casa:sentiment-predictions` (drivers are now `rules|local|gemini`). Also removed the `sentiment.xlmr` config block and the `SENTIMENT_XLMR_*` lines in both env example files.
- **Docker:** removed the `sentiment` Compose service and the `sentiment-models` volume from `compose.yaml`, and deleted `docker/sentiment/` (Dockerfile, `app.py`, requirements).
- **Tests:** deleted `XlmRobertaSentimentModelTest`. These were converted to Gemini, so shadow mode, disagreement, failure fallback and blank-comment fallback stay covered:
  - the XLM-R cases in `HybridSentimentClassifierTest`;
  - the shadow-run test in `SentimentUpgradeTest`;
  - the predictions test in `SentimentPredictionsCommandTest`.
- **Current docs:** removed or rewrote the XLM-R content.
  - [Docker workflow](../../DOCKER_WORKFLOW.md): the service and the endpoint section.
  - [Hostinger deployment](../../HOSTINGER_DEPLOYMENT.md): the XLM-R paragraph.
  - [Implementation plan](../../SENTIMENT_ANALYSIS_IMPLEMENTATION_PLAN.md): the status paragraph is now a "removed on 2026-11-01" note, and the workflow commands point at `curated-v2` and Gemini.
  - [PLANNED_TASKS.md](../../PLANNED_TASKS.md): Task 1 is retitled "Sentiment Model for Feedback" and marked done, and the stale status line is fixed.
  - [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md).
  - [Manifest](../../sentiment-dataset-manifest.json): the Cardiff model entry is removed.
- **Kept as history:**
  - the dated session notes and the `SESSIONS.md` index;
  - the XLM-R rows in the plan's gate-results tables;
  - the evaluation strings in the manifest's dataset sources;
  - the ignored prediction files `storage/app/private/c1-xlmr.*` and `c2-xlmr.*`.

## Verification

- `git grep` finds no XLM-R references left in `app`, `config`, `tests`, `compose.yaml`, the env examples, `routes`, `resources`, `database`, `scripts` or `phpunit.xml`.
- The full suite, run in two halves because of the known single-process crash, passes: 399 + 209 = 608 tests. That is 614 minus the 5 removed XLM-R unit tests and 1 merged predictions test.
- `compose config --quiet` validates. `npm run build` succeeded. `casa-docker.ps1 start` is healthy. `git diff --check` is clean.
- Docker deleted the container `casa-paraiso-web-sentiment-1`, the image `casa-paraiso-web-sentiment:latest` (1.56 GB) and the volume `casa-paraiso-web_sentiment-models`. `casa-paraiso-web_mariadb-data` and the retired `mysql-data` volume were not touched.

## Decisions

- The history of why XLM-R was dropped stays traceable for the thesis comparison, as the user chose.
- Any `SENTIMENT_XLMR_*` lines left in a local or Hostinger `.env` are now ignored and harmless. Delete them by hand if you like; this task did not edit `.env`.

## Follow-ups

- Revise the manuscript, which still describes XLM-RoBERTa as the integrated model.
- Update tracker row T1 in the sheet.
