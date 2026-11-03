---
type: session-log
date: 2026-11-04
---

# AI activation conditions review

## Task

Clarify whether AI (Gemini / local model) is always active during sentiment analysis, detailing configuration gates, empty comment bypass, confidence thresholds, and network fallback.

## What Changed

- Created this session note and linked it in [SESSIONS.md](../SESSIONS.md).

## Verification

- Traced conditions in [`app/Services/HybridSentimentClassifier.php`](../../../app/Services/HybridSentimentClassifier.php), [`app/Services/GeminiSentimentModel.php`](../../../app/Services/GeminiSentimentModel.php), and [`.env`](../../../.env).
- Validated documentation formatting and diff cleanliness with `git diff --check`.

## Decisions

- None.

## Follow-ups

- None.
