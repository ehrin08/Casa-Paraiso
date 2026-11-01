---
type: session-log
date: 2026-11-01
---

# RFM Client Question Sheet (Task 7)

## Task

Prepare a plain-language question sheet for the client, for Task 7's open item: which customer groups and rewards they actually think in.

## What Changed

- Added [Customer Groups and Rewards: Questions for the Client](../../client/RFM_GROUPS_CLIENT_QUESTIONS.md). It explains how rewards work, lists the five groups with their add-ons and cards, asks eight questions with answer lines, and says which answers need no code.
- Linked it from [HOME.md](../../HOME.md) and from the open Task 7 item in [PLANNED_TASKS.md](../../PLANNED_TASKS.md).

## Verification

- Checked the facts against the source:
  - group names and add-ons in the `config/casa.php` presets, and add-on prices (₱200, back massage ₱299);
  - card recommendations (Platinum for High-value, Gold for Loyal) in migration `2026_11_01_010000_create_memberships`;
  - validity options (30/60/90/180, default 90, or none);
  - the one-unused-reward guard in `RfmPromotionGenerator::hasActiveEntitlement`.
- `git diff --check` passed. This is documentation only, so no application checks were run.

## Decisions

- The sheet avoids RFM terms and scores, and explains that groups compare customers with each other.
- Answers that only rename groups or change rewards, order, validity or cards are admin settings. Fixed day or visit limits, or grouping by other data, would be new planned work.

## Follow-ups

- Ask the client the questions and record the answers. Then apply the setting changes, or add new planned work for the rest.
