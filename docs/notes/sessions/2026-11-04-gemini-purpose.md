---
type: session-log
date: 2026-11-04
---

# Gemini purpose explanation

## Task

Explain what Gemini adds when the rules classifier always runs.

## What Changed

- Created this session note and linked it in [SESSIONS.md](../SESSIONS.md).

## Verification

- Compared [`app/Services/SentimentClassifier.php`](../../../app/Services/SentimentClassifier.php) (word counting, star fallback, low-star override) with the instruction in [`app/Services/GeminiSentimentModel.php`](../../../app/Services/GeminiSentimentModel.php).

## Decisions

- None.

## Follow-ups

- None.
