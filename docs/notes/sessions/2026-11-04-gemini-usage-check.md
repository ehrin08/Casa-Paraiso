---
type: session-log
date: 2026-11-04
---

# Gemini usage check

## Task

Answer whether sentiment analysis always uses Gemini.

## What Changed

- Created this session note and linked it in [SESSIONS.md](../SESSIONS.md).

## Verification

- Read [`app/Services/HybridSentimentClassifier.php`](../../../app/Services/HybridSentimentClassifier.php), [`app/Services/GeminiSentimentModel.php`](../../../app/Services/GeminiSentimentModel.php), and [`config/sentiment.php`](../../../config/sentiment.php).
- Effective local config inside the app container: mode `model`, driver `gemini`, threshold `0.8`, API key present.
- The local `.env` declares `SENTIMENT_CLASSIFIER_MODE` twice (`rules`, then `model`); the later value wins.

## Decisions

- None.

## Follow-ups

- Remove the duplicate `SENTIMENT_CLASSIFIER_MODE` line from the local `.env`.
- Code defaults are `shadow` mode and the `local` driver, so other environments use Gemini only when configured.
