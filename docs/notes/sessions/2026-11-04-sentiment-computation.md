---
type: session-log
date: 2026-11-04
---

# Sentiment computation walkthrough

## Task

Explain step by step how a feedback item becomes positive, neutral or negative.

## What Changed

- Created this session note and linked it in [SESSIONS.md](../SESSIONS.md).

## Verification

- Traced [`app/Services/SentimentClassifier.php`](../../../app/Services/SentimentClassifier.php), [`app/Services/GeminiSentimentModel.php`](../../../app/Services/GeminiSentimentModel.php), [`app/Services/HybridSentimentClassifier.php`](../../../app/Services/HybridSentimentClassifier.php), and the word lists in [`config/sentiment.php`](../../../config/sentiment.php).

## Decisions

- None.

## Follow-ups

- None.
