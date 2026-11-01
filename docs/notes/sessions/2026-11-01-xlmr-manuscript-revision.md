---
type: session-log
date: 2026-11-01
---

# XLM-RoBERTa manuscript revision

## Task

Incorporate the implemented optional XLM-RoBERTa sentiment classifier throughout the existing manuscript's [Temporary Revisions tab](https://docs.google.com/document/d/1mKYzY63egRP4O-SqVh5m8K8KSPlxyio1bxD5IyCZF78/edit?tab=t.js4c2uprg4q8), with explanatory comments on every revised section group.

## What Changed

- Revised 36 text blocks across 20 section groups in Chapters I-V and References, including definitions, literature, requirements, architecture explanations, development, evaluation, deployment, findings, conclusions, and recommendations.
- Identified `cardiffnlp/twitter-xlm-roberta-base-sentiment`, pretrained inference, the separate HTTP service, the default 0.80 selection threshold, fallback behavior, and rating-derived labels for blank comments.
- Distinguished the recorded October 30 integration tests and local smoke test from representative predictive evaluation and rules-based production behavior. Preserved historical figures and expert ratings as their original evidence.
- Added primary references for Conneau et al. (2020), Barbieri et al. (2022), and the checkpoint model card.
- Created 20 open revision comments identifying the tab, section, and exact revised text. Added one clarification reply after the final source check established that runtime disagreement/review-state flags are not saved by `FeedbackSentimentUpdater`.
- Preserved the six other tabs and all 11 existing comment threads. No application or database changes were made.

## Verification

- Guarded content and formatting writes with the document revision ID; reconciled a revision conflict through a fresh read confirming unchanged document bodies before retrying.
- Final native document readback confirmed all 36 revised text blocks and unchanged bodies in the six other tabs.
- Final comment readback confirmed 20 new open comments, one clarification reply, and unchanged content/resolution state for the 11 pre-existing comments.
- Checked technical descriptions against the classifier, adapter, feedback persistence service, configuration, inference service, project notes, and primary model/research sources.
- Exported the final document to PDF and visually inspected 34 pages containing revised paragraph starts, including revised tables, reference entries, and figure explanations. Corrected inherited bold text and restored reference italics during review.
- Documentation whitespace checked with `git diff --check`. Application tests, asset builds, and Docker startup were not required for this manuscript/documentation task.
- Local document snapshots, PDF exports, and visual-review files remain under the existing ignored `output/questionnaire/` directory; no temporary download URLs or credentials are recorded here.

## Decisions

- Only Temporary Revisions was edited; other manuscript versions and questionnaires remain unchanged.
- XLM-RoBERTa is an integrated optional candidate, not an evaluated or production-promoted classifier. No accuracy score, Casa Paraiso fine-tuning, or business improvement was invented.
- High-confidence disagreement can still select the model in model mode; the classifier emits review flags, but their persistence and an automatic flagged-review queue are not established by the current implementation.
- The model does not extract snippets. Source-linked snippet functionality remains separately evaluated.
- Connector-created comments can appear unanchored, so comments include section names and quoted text. The 3.5 thread's clarification reply gives the updated exact target after the source-check correction.

## Follow-ups

- Complete the locked representative English, Tagalog, and Taglish evaluation and compare rules, local, and XLM-RoBERTa outputs before changing production selection.
- Confirm inference hosting, checkpoint licensing, and privacy disclosure before activation.
- Verify or implement durable disagreement/review-state handling separately if an automatic review queue is required; this task did not alter application behavior.
- Superseded: the XLM-RoBERTa driver was later removed and Gemini became the sentiment model (see [XLM-RoBERTa removal](2026-11-01-xlmr-removal.md)). The Temporary Revisions tab still describes XLM-RoBERTa and needs a follow-up revision.
