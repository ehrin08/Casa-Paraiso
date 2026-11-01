---
type: session-log
date: 2026-11-01
---

# Gemini sentiment driver (Task 1 / T1)

## Task

There was no labelled data for fine-tuning XLM-RoBERTa, so the user chose Google Gemini's free API tier for sentiment. Their choices: send real comments on the free tier, and go straight to `model` mode once the gate passes.

## What Changed

- **New `App\Services\GeminiSentimentModel`** (`SENTIMENT_MODEL_DRIVER=gemini`):
  - It posts the star rating and a redacted comment to the Gemini `generateContent` API, with a condensed Labelling Guide and the text-then-stars blend rule as its instructions.
  - Structured JSON output returns a `label` and a `confidence`. Version `gemini-v1`.
  - The key travels in the `x-goog-api-key` header. Any failure returns null, so the rules decide.
- **New `App\Support\SentimentRedactor`:** the redaction moved out of `casa:export-sentiment-dataset`, which now calls it. Its behaviour is unchanged.
- **Wiring:**
  - `HybridSentimentClassifier` selects the `gemini` driver.
  - `casa:sentiment-predictions` gains `--driver=gemini` and `--sleep=N`.
  - `config/sentiment.php` has a new `gemini` block (`GEMINI_API_KEY`; `SENTIMENT_GEMINI_MODEL`, default `gemini-3.5-flash-lite`; `SENTIMENT_GEMINI_TIMEOUT`, default 5).
  - Both env example files have blank `GEMINI_API_KEY`, `SENTIMENT_GEMINI_MODEL` and `SENTIMENT_GEMINI_TIMEOUT` lines. The defaults stay `rules` and `local`.
- **Privacy policy:** it now says ratings and comments go to Google's Gemini API, what is removed first, that Google may use them under its free-tier terms, and asks customers to leave personal details out.
- **Docs updated:**
  - [Data governance](../../SENTIMENT_DATA_GOVERNANCE.md): a Gemini section
  - [Implementation plan](../../SENTIMENT_ANALYSIS_IMPLEMENTATION_PLAN.md): gate results and the decision
  - [Manifest](../../sentiment-dataset-manifest.json): a model entry
  - [Docker workflow](../../DOCKER_WORKFLOW.md) and [Hostinger deployment](../../HOSTINGER_DEPLOYMENT.md): env setup
  - [PLANNED_TASKS.md](../../PLANNED_TASKS.md) and [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md)

## Verification

- **Current Gemini docs checked before coding:**
  - the `generateContent` REST shape, the `x-goog-api-key` header, and `responseSchema` with enums;
  - stable models: `gemini-3.8-flash`, `gemini-3.6-flash`, `gemini-3.5-flash-lite` and `gemini-3.1-flash-lite`, all on the free tier;
  - the pricing page says free-tier content is "used to improve our products";
  - the terms say not to submit personal information to unpaid services.
- **Gate:** one run on the locked `curated-v2` set, with `--sleep=4`. All 150 rows answered and none fell back. Macro-F1 0.993 against 0.714 for rules v2. Negative recall 1.0, 1 Taglish error and 0 mixed errors, so the gate passed. Three answers had confidence below 0.80. The single error was a factual request labelled positive.
- **Key handling:** the key was added to `.env` by the user. The app's view of it was checked as booleans only, and it was never printed. A credential the user pasted into chat was not used, and the user was advised to replace it.
- **Tests:**
  - The targeted Sentiment, Gemini, Redactor and Authentication tests pass (41).
  - The full suite run in two halves passes: 404 + 210 = 614 tests.
  - A single full-suite run crashes PHP (signal 11), or corrupts what gets injected into `MembershipTest`, right after `LandingTeamChartTest`. The same crash happens at HEAD with this task's files removed, and continues after an app container restart, so it isn't caused by this change.
- **Build and startup:** `npm run build` succeeded. The Docker startup path (`casa-docker.ps1 start` and `status`) is healthy. `git diff --check` is clean.

## Decisions

- **Free tier in production:** the user's choice, kept despite Google's terms on personal information. It is disclosed, and moving to paid billing is recommended in the governance doc.
- **No shadow period:** the user chose to switch straight to `model` mode. The rules stay as the fallback and as the stored rules candidate for audit.
- **Default model:** `gemini-3.5-flash-lite`, the stable free-tier model Google recommends for new projects.
- **Reading the 0.993:** it is an upper bound. The comments and labels were AI-written, and an LLM is likely to agree with them.

## Follow-ups

- **Switch on:** the user sets `SENTIMENT_MODEL_DRIVER=gemini` and `SENTIMENT_CLASSIFIER_MODE=model` in `.env` and on Hostinger, plus `GEMINI_API_KEY` on Hostinger.
- **Paid billing:** recommended, so Google stops using customer comments.
- **Manuscript:** its sentiment sections now describe a classifier that is no longer the main one.
- **Pre-existing crash:** investigate the PHP crash after `LandingTeamChartTest` (likely image handling in the photo-upload test).
- **Optional:** run `casa:reclassify-sentiment` to relabel existing feedback, only when the user asks.
- **Sentiment container:** still running. Stop it with `./scripts/casa-docker.ps1 compose --profile sentiment stop sentiment`.
