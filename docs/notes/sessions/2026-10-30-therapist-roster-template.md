---
type: session-log
date: 2026-10-30
---

# Therapist roster template

## Task

Make a spreadsheet template for collecting therapist details forwarded over Messenger, so they can be entered into the site's staff and team-profile forms.

## What Changed

- Created a new Google Sheet, "Casa Paraiso - Therapist Roster", in the owner's Drive with a `Therapists` tab. No repository code changed.
- Its columns follow `app/Http/Requests/Admin/StaffRequest.php` (name, email, phone, staff type, position, specialization, hire date, bookable, services) and `app/Http/Requests/Admin/TeamProfileRequest.php` (public title, public bio, show on website). There is also an "Entered in Site" tick box.
- Nickname, gender and age are kept in the sheet only. The site has no fields for them.
- Pre-filled five therapist rows from the forwarded messages. Email, phone, hire date and services are left blank.

## Verification

- Checked the sheet in Chrome: bold, frozen header row, five rows, and tick boxes in column P.
- `git diff --check` on this note and the index.

## Decisions

- Personal details stay in the sheet, not in this note.
- Email is required by the staff form and is unique per account, so each therapist needs their own address before being added.

## Follow-ups

- Fill in emails and the other blank columns, then add each therapist through Admin → Staff.
