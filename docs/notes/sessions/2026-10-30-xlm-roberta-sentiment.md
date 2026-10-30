---
type: session-log
date: 2026-10-30
---

# XLM-RoBERTa feedback sentiment (T1)

## Task

Planned task T1 from [PLANNED_TASKS.md](../../PLANNED_TASKS.md): use a pretrained multilingual XLM-RoBERTa model for feedback sentiment analysis, behind the existing hybrid adapter, with the rules as fallback.

## What Changed

- `config/sentiment.php`: new `model_driver` (`SENTIMENT_MODEL_DRIVER`, default `local`) and an `xlmr` block (`SENTIMENT_XLMR_URL`, `SENTIMENT_XLMR_TOKEN`, `SENTIMENT_XLMR_TIMEOUT` with a 3-second default, the checkpoint name, version `xlmr-twitter-base-v1` and a 1,000-character cap on the text sent). The keys were added, blank, to `.env.example` and `deploy/hostinger.env.example`.
- New `App\Services\XlmRobertaSentimentModel`. It posts the comment to the endpoint in the Hugging Face text-classification format and accepts both nested and flat responses, with `negative`/`neutral`/`positive` or `LABEL_0..2` labels. Score is P(positive) − P(negative). It returns null for blank comments, no URL, timeouts, HTTP errors and unexpected payloads, and its warning logs never include comment text.
- `HybridSentimentClassifier` picks the model candidate by driver. Modes, threshold, disagreement and review state, and the `feedback_sentiment_runs` records are unchanged.
- Optional Compose service `sentiment` (profile `sentiment`, `docker/sentiment/`): a CPU-only FastAPI and `transformers` endpoint at `http://sentiment:8000/`, with no host port and the checkpoint cached in the `sentiment-models` volume.
- New `casa:sentiment-predictions {dataset} {output} --driver=rules|local|xlmr` command, which writes predictions for `scripts/evaluate_sentiment_model.py`.
- Tests: new `XlmRobertaSentimentModelTest` and `SentimentPredictionsCommandTest`; extended `HybridSentimentClassifierTest` and `SentimentUpgradeTest`. All of them fake the endpoint.
- Docs: model entry in [sentiment-dataset-manifest.json](../../sentiment-dataset-manifest.json); updates to [PLANNED_TASKS.md](../../PLANNED_TASKS.md), [SENTIMENT_ANALYSIS_IMPLEMENTATION_PLAN.md](../../SENTIMENT_ANALYSIS_IMPLEMENTATION_PLAN.md), [DOCKER_WORKFLOW.md](../../DOCKER_WORKFLOW.md#sentiment-model-endpoint), [HOSTINGER_DEPLOYMENT.md](../../HOSTINGER_DEPLOYMENT.md) and [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md).

## Verification

- Targeted sentiment tests: 18 passed (100 assertions).
- Full Laravel suite: 527 passed (5,703 assertions).
- `npm run build` passed. `casa-docker.ps1 start` brought every service up healthy. `git diff --check` was clean.
- Live smoke test: the `sentiment` image was built and started, `/health` reported the checkpoint, and real calls through the PHP service and the hybrid adapter (in memory, nothing saved) returned labels. Example: "Ang tagal ng hintay at bastos ang staff." came back negative (0.90), but "Sulit na sulit, babalik ako!" also came back negative (0.64), which is wrong. The service was stopped afterwards. Local `.env` and the database were not changed.

## Decisions

- Inference runs through an HTTP endpoint (the owner's choice), because Hostinger cannot run the model. The same client works with a Hugging Face Inference Endpoint or a self-hosted server.
- The checkpoint is `cardiffnlp/twitter-xlm-roberta-base-sentiment` (the owner's choice). Its model card declares no license, and Tagalog is not one of its fine-tuning languages.
- The default driver stays `local`, and production stays `rules`. The smoke test already shows Taglish mistakes, so promotion has to wait for the evaluation gate.

## Follow-ups

- Build the redacted, labelled locked test set and compare rules, local and xlmr with `casa:sentiment-predictions`.
- Choose a production inference host, confirm the checkpoint license, and update the privacy policy before enabling `xlmr` on Hostinger.
- Consider fine-tuning on Casa Paraiso data if the pretrained checkpoint misses the gate.
- Update the T1 row in the Google Sheet tracker.
