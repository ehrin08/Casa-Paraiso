---
type: manuscript-setup
date: 2026-11-19
---

# Claude Chat Project for Phone Work

Setup for editing the manuscript from the Claude phone app when the PC is off. Back to [Docs Home](../HOME.md).

The work runs in a Claude chat project with the Google Drive connector, so it edits the [manuscript Google Doc](https://docs.google.com/document/d/1mKYzY63egRP4O-SqVh5m8K8KSPlxyio1bxD5IyCZF78/edit?usp=sharing) directly. The chat project cannot see this repository. Its shared memory is the [Casa Paraiso Phone Brief](https://docs.google.com/document/d/1auHiXUxHULno3ZdRGbilQiIC9O_RDo1P1HxJ6RwzCGg/edit) Google Doc, created on 2026-11-19 from the notes in this folder. The brief holds:

- the manuscript link, its tabs and its formatting facts
- the editing rules
- the open tasks, copied from the [Submission Timeline](2026-11-17-submission-timeline.md) and [Defense Panel Revisions](2026-11-10-defense-panel-revisions.md)
- a Work log, newest first, where each entry ends with "Synced to repo: no" or "yes"
- the CV questions for the group

When the PC is on, Remote Control is the better route: the Claude app's Code tab opens the desktop sessions, which keep the repository and every connector.

## Setup on the phone

1. In the Claude app (or claude.ai in the phone's browser), open **Projects** and create a project named **Casa Paraiso Manuscript**.
2. Paste the instructions below into the project's instructions.
3. Don't upload the brief as a project file. A file is a fixed copy, while the instructions make Claude read the live doc.
4. In the project's first chat, turn on the **Google Drive** connector, and **Google Docs** if it is listed separately, from the chat's connector menu.
5. Ask "What's next?" to check that it reads the brief and lists the open tasks.

## Project instructions

```text
You help me finish the Casa Paraiso capstone manuscript from my phone while I'm away from my PC.

At the start of every chat, read the "Casa Paraiso Phone Brief" Google Doc with the Google Drive or Google Docs connector:
https://docs.google.com/document/d/1auHiXUxHULno3ZdRGbilQiIC9O_RDo1P1HxJ6RwzCGg/edit
It has the manuscript link, the editing rules, the open tasks and the work log. Follow its rules. Read the manuscript tab again right before each edit, because my groupmates edit it at the same time.

After every task that changes the manuscript or a task's status:
1. In the Phone Brief, update Open tasks (move a finished item to Done with the date) and add a Work log entry at the top of that list: the date, what changed (tab and section), what is still open, and "Synced to repo: no".
2. End your reply with:
- Result: Success, Partial or Failed, plus one line.
- Errors: failures or skipped steps, or "None".
- Suggestions: at most three next steps, or "None".
- Tasks: the open tasks, done ones checked, and the next deadline on one line.

If the connector can't edit a doc, tell me and stop; don't make a copy instead. Keep replies short, because I read them on a phone.
```

## Syncing back at the PC

Tell Claude Code to "sync the phone log". It then:

1. reads the Phone Brief and takes every Work log entry marked "Synced to repo: no"
2. rereads the manuscript sections those entries name
3. updates the Submission Timeline and Defense Panel Revisions checklists
4. writes a session note for the phone work and links it in [Sessions](../notes/SESSIONS.md)
5. changes those entries to "Synced to repo: yes"

The flow also runs the other way: when a manuscript task changes at the PC, Claude Code updates the brief's Open tasks too, so the phone project starts from the current list.
