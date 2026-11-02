---
type: session-log
date: 2026-11-02
---

# Legacy manuscript comment cleanup

## Task

Remove the 37 approved legacy-model comment threads from the [manuscript](https://docs.google.com/document/d/1mKYzY63egRP4O-SqVh5m8K8KSPlxyio1bxD5IyCZF78/edit?tab=t.js4c2uprg4q8), preserving the other 25 threads and all document content.

## What Changed

- Refreshed the full document and comment inventory before deletion: 62 threads, with the same 37 selected matches.
- Saved private recovery snapshots under ignored `output/questionnaire/legacy-comments-cleanup-backup.json` and `legacy-comments-document-before.json` before changing comments.
- Deleted the 37 selected threads through the native Google Docs interface, including the reply attached to one selected thread.
- Preserved manuscript text, formatting, images, all seven tabs, and the 25 unrelated threads.

## Verification

- Fresh connector inventory: 25 remaining threads; all 37 selected identifiers absent; no concurrent additions.
- Compared retained thread text, quoted content, replies, and resolved/deleted state against the snapshot: all 25 unchanged.
- Searched remaining comment content, quoted passages, replies, and editable text across all seven tabs for legacy names and checkpoint identifiers: zero matches.
- Full tab and body definitions matched the pre-deletion snapshot after excluding rotating image `contentUri` values. Image definitions and formatting remained unchanged.
- Native all-tabs comment search for `XLM` showed no matching results; screenshot saved in ignored `output/questionnaire/legacy-comments-cleanup-proof.png`.
- Documentation whitespace and referenced-path checks passed. Application checks and PDF rendering were unnecessary because no application or manuscript layout content changed.

## Decisions

- Scope was limited to current manuscript comments. Google revision history and project archival records were untouched.
- Browser interruptions were reconciled against fresh comment inventories before continuing; final verification found no missing preserved threads.

## Follow-ups

- Recovery snapshots remain local and ignored; do not commit their private comment contents.
