---
type: session-log
date: 2026-11-01
---

# Customer booking safeguards (T5)

## Task

Planned task T5 from [PLANNED_TASKS.md](../../PLANNED_TASKS.md): prevent troll appointments. The owner agreed the safeguards and approach A in the [design](../../superpowers/specs/2026-11-01-booking-safeguards-design.md), then the [plan](../../superpowers/plans/2026-11-01-booking-safeguards.md) was built inline.

## What Changed

- **Data and config:**
  - Additive migration `2026_11_01_120000_add_online_booking_block_to_customer_profiles_table` adds `online_booking_blocked_at`, `online_booking_block_reason` (500) and `online_booking_blocked_by` (FK `users`, null on delete).
  - `CustomerProfile::isOnlineBookingBlocked()` and the `onlineBookingBlockedBy()` relation.
  - `config/casa.php` `customer_booking_limits`: 3 submissions per 10 minutes, 2 upcoming self-bookings, 2 no-shows in 90 days.
- **Guard:**
  - New `App\Services\CustomerBookingGuard` checks, in order:
    - an admin block;
    - 2 or more no-shows in the lookback window;
    - 2 upcoming confirmed bookings the customer created (`created_by` = the customer's user).
  - It gives customers a category message only, never the admin's reason.
- **Enforcement:**
  - `AppointmentWorkflow::autoBook` (customer self-booking only) locks the customer profile row inside its transaction and calls the guard before reserving capacity.
  - `Customer\AppointmentController@store` rate-limits per user with the key `customer-booking:{id}`.
  - `@create` replaces the booking form with a notice when a restriction applies. That covers the booking dialog too.
- **Admin:**
  - `admin.customers.online-booking.block` and `.unblock` (PATCH, `Admin\CustomerOnlineBookingController`, `Admin\CustomerOnlineBookingBlockRequest` with a required reason of at most 500 characters).
  - An Online booking card on the customer detail page and in the customer modal, with two new toast messages.
  - Reception customer detail shows a read-only "Online booking paused" badge.
- **Tests:** `CustomerBookingGuardTest`, `CustomerBookingSafeguardsTest`, `AdminCustomerOnlineBookingTest`.
- **Docs:** [PLANNED_TASKS.md](../../PLANNED_TASKS.md), [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md), [DATABASE_DESIGN.md](../../DATABASE_DESIGN.md), [SCREEN_FLOW.md](../../SCREEN_FLOW.md), [HOME.md](../../HOME.md).

## Verification

- New and related suites passed. These covered:
  - customer booking, appointments and calendar scheduling;
  - guest booking and rewards;
  - the admin customer modal, role workspace and reception.
- Full Laravel suite passed: 558 tests before the review fixes, then 561 tests and 5,864 assertions after them.
- JavaScript tests passed: 92 of 92.
- `npm run build` passed.
- `casa-docker.ps1 start` passed with healthy services. The retained `mysql` orphan container was left alone.
- The new migration was applied to the local database with `php artisan migrate` (additive only).
- `git diff --check` passed.
- A fresh reviewer read the whole branch. It found no critical problems and confirmed the five review-focus cases. The fixes for its findings are below.
- Not done: an interactive browser check. The local database has no seeded test accounts, and none were created.

## Decisions

- Only bookings the customer made count toward the cap. Staff can always book a blocked or capped customer at the desk.
- The no-show pause cannot be lifted early by admins. It ends when older no-shows leave the 90-day window.
- Block uses an inline form with a required reason in the card rather than a dialog, so it works the same on the full page and in the customer panel. Unblock is one button.
- Block and unblock redirect to the full customer page, including when used from the list panel.

### Review fixes

- The booking form carries `_panel=/customer/appointments/create` when it is rendered in the dialog. A refused submit now reopens the dialog with the message, where before it closed silently. Test: `test_rejected_dialog_submit_reopens_the_booking_dialog_with_the_message`.
- The admin card shows "Upcoming booking limit reached" for capped customers instead of "Allowed" (`test_detail_page_shows_upcoming_limit_reached`).
- The details partial now computes the restriction itself, so the list's recovery modal also shows the no-show pause (`test_list_recovery_modal_reports_no_show_pause`).
- The booking-limits notes in `DATABASE_DESIGN.md` were moved below the guest rules.

## Follow-ups

- Browser-check as a local customer (the notice in the booking dialog) and as an admin (block and unblock), with local test accounts.
- Deploy with the next release. It includes one additive migration, so take a private dump before migrating the live database.
- Deferred minor review points:
  - Lock order: self-booking locks the customer then the therapists. That could rarely deadlock with a staff booking for the same customer at the same moment; InnoDB resolves it, and staff would see a one-off error.
  - Submissions that fail form validation do not count toward the rate limit.
  - A whitespace-only block reason from the customer panel fails without reopening the panel.
  - The wait message says "1 minutes".
  - The spec names `RateLimiter::attempt`, but the code uses `tooManyAttempts` and `hit`.
- Set T5 to Done in the `Tasks` tab of the "Casa Paraiso Tracker" Google Sheet.
