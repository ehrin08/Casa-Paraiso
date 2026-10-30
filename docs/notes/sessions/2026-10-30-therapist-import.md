---
type: session-log
date: 2026-10-30
---

# Therapist import and schedules

## Task

Add the five therapists from the Tracker sheet's `Therapists` tab to the live site, then give them schedules (task T6).

## What Changed

- No repository code changed. All data went in through the live admin forms on https://casaparaiso.site as the super admin.
- Five therapist accounts were created through Add therapist (`admin.staff.store`). Each is active and bookable, with position "Massage Therapist", specialization "Massage" and all four active services. They are not shown on the website.
- Email is required and unique, and the sheet had none, so each account uses a placeholder `<nickname>.therapist@` address on the site domain. These addresses cannot receive mail, so the therapists cannot set a password until real addresses replace them via Edit.
- Therapist schedules: Apply hours set 1:00 PM – 12:00 midnight for the five on Fri–Sat of the Oct 25 week (earlier days were read-only), and on all seven days of the Nov 1 week. Both weeks were published, and later weeks repeat the Nov 1 week. The existing therapists' hours were left unchanged.
- In the Tracker sheet, the emails and services columns are filled in and "Entered in Site" is ticked for the five rows. T6 is marked Done.

## Verification

- The Therapists list on the live site showed the five new accounts beside the two existing ones.
- The Nov 8 week shows "Repeating from Nov 1, 2026", with 1:00 PM – 12:00 midnight effective hours every day for all five.
- `admin.appointments.availability` for Gaia Touch in November returned 1:00 PM starts with five therapists available.
- `git diff --check` on the docs.

## Decisions

- Placeholder emails, the live site, full store hours every day and all active services were the user's choices.
- The weekly roster was used rather than per-therapist weekly hours, because a published roster already overrides them.
- Names and emails are kept out of this note.

## Follow-ups

- Replace the placeholder emails with real ones, then send password-setup invitations.
- Add phone numbers, hire dates and public bios when the details arrive. Turn on Show on website from the Team page if wanted.
