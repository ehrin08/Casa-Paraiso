---
type: session-log
date: 2026-11-02
---

# Manuscript reference comparison

## Task

Review the manuscript's Temporary Revisions tab against the supplied LipaGo Reference.pdf for structure, presentation and finished-project completeness. Preserve both documents and keep their research content separate.

## What Changed

- Added the [separate comparison and prioritized checklist](../../manuscript/2026-11-02-reference-review.md).
- Linked the review and this session from [HOME](../../HOME.md), and indexed this session in [SESSIONS](../SESSIONS.md).
- No manuscript text, images, comments, other tabs, application code or database was changed.

## Verification

- Read the current native target-tab text and downloaded a fresh all-tabs PDF through the Drive connector. Isolated target PDF pages 89-245; compared with the 226-page local reference.
- Visually inspected 28 Casa and 16 reference pages; checked selected PDF font sizes, body positions, headings, captions and page-number destinations.
- Confirmed missing front/back matter in the target tab, incomplete figure list, stale navigation, the Tables 13-18 item-results correction, and explicit unresolved evaluation evidence.
- Compared the final connector revision with the initial review revision; checked the local reference SHA-256 for stability.
- Documentation whitespace and relative local links checked. Application tests, asset build and Docker startup intentionally skipped for this documentation-only review.
- One initial text-inspection command encountered Windows console Unicode encoding; rerun with UTF-8 succeeded. A font-metadata warning did not prevent extraction or PDF rendering.

## Decisions

- The reference is a presentation example, not an official template. Its own contents inconsistencies are documented rather than copied.
- Preserve Casa's five-expert evaluation and pending 15-person UAT plan; do not import reference participants, results, methods, citations or personal details.
- Separate immediately supportable corrections, new original manuscript material, and evaluations requiring actual evidence.
- No durable application or verification-workflow change, so project memory did not need an update.

## Follow-ups

- Use the review's three priority lists for a later manuscript revision.
- Source exports and render samples remain local under ignored `output/questionnaire/reference-review/`.
