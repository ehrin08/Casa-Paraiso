# Casa Paraiso CRUD Remediation Checklist

Created from the CRUD audit completed on 2026-07-12.

> Historical note: pending appointment creation and transition coverage below was superseded when the pending workflow was retired on 2026-07-14.

## Baseline

- [x] Audit admin CRUD screens in the authenticated browser.
- [x] Inspect routes, controllers, requests, models, services, views, migrations, and existing tests.
- [x] Run PHP syntax checks and compile Blade templates.
- [x] Run the isolated Laravel Feature suite: 70 tests and 470 assertions passed.
- [x] Resolve the remediation items below before treating CRUD behavior as production-ready.

## P0 — Critical Data and Request Failures

### Admin appointment persistence

- [x] Persist `status` before the first save of an admin-created appointment.
- [x] Initialize `scheduled_end_at` for confirmed admin creates and updates.
- [x] Confirm pending, confirmed, completed, cancelled, and no-show admin create paths return controlled results rather than database or PHP errors.
- [x] Add feature tests for `admin.appointments.store` and `admin.appointments.update` covering every supported status.

### Orphaned staff assignments and historical identity

- [x] Repair operational appointments referencing soft-deleted staff profile `1`; preserve the completed historical reference.
- [x] Use `withTrashed()` relationships for historical staff, customer, service, and rule identity; preserve the original foreign keys rather than inventing replacement identities.
- [x] Prevent role changes from silently hiding names on appointments, transactions, feedback, promotions, and reports.
- [x] Block role or account changes that would leave future confirmed appointments without an eligible therapist.
- [x] Add regression tests for role changes with historical and future records.

### Customer account deletion

- [x] Define deletion as sign-in removal plus profile anonymization while retaining de-identified business history.
- [x] Replace the current hard-delete path with a foreign-key-safe workflow.
- [x] Preserve legally and operationally required appointment, payment, feedback, and promotion records.
- [x] Add successful deletion/anonymization tests for customers with and without related records.

## P1 — Core CRUD Integrity

### Appointment validation and lifecycle

- [x] Separate appointment creation rules from update and outcome rules.
- [x] Allow staff to record completion, no-show, or cancellation after the original requested time.
- [x] Allow an existing appointment to retain a service that was later deactivated.
- [x] Introduce an explicit status-transition matrix.
- [x] Clear or preserve confirmed, completed, and cancelled timestamps consistently during allowed transitions.
- [x] Reject completed or no-show states for unscheduled appointments.
- [x] Add tests for every allowed and forbidden transition.

### Therapist eligibility safeguards

- [x] Run the future-booking conflict guard before deactivating a staff account.
- [x] Run the guard before turning off `is_bookable`.
- [x] Run the guard before removing a service required by future confirmed appointments.
- [x] Present affected appointment links when an eligibility change is blocked.
- [x] Add rollback tests for account, bookable, and service-assignment changes.

### Scheduling rules

- [x] Enforce future scheduled times where required.
- [x] Enforce 30-minute start intervals on the server.
- [x] Keep the 1:00 PM–12:00 midnight Asia/Manila booking window authoritative.
- [x] Add staff rescheduling controls to the appointment UI.
- [x] Recheck therapist eligibility, working windows, and overlaps transactionally during rescheduling.
- [x] Add boundary tests for opening time, midnight endings, invalid minutes, past schedules, and overlaps.

### Promotions and RFM

- [x] Make promotion generation idempotent for an existing customer/rule/metrics review item.
- [x] Prevent repeated generation from duplicating the review queue.
- [x] Ensure the New Customer segment matches customers with zero paid completed visits.
- [x] Preserve `applied_at` and `dismissed_at` unless a real status transition occurs.
- [x] Clear mutually exclusive promotion timestamps when reversing status.
- [x] Allow review notes to be cleared intentionally.
- [x] Add repeat-generation, zero-visit, reversal, timestamp, and note-clearing tests.

### Reports and exports

- [x] Filter appointment reports using the same business date displayed to the user.
- [x] Use scheduled date when assigned and requested date otherwise, consistently in HTML, filters, sorting, and CSV.
- [x] Filter transaction revenue records by `paid_at` rather than record creation time.
- [x] Apply date and status filters to customer reports.
- [x] Show all promised customer metrics consistently in HTML and CSV output.
- [x] Neutralize CSV values beginning with `=`, `+`, `-`, or `@`.
- [x] Replace the silent 5,000-row cutoff with a lazy streamed export.
- [x] Add content-level tests for report dates, filters, metrics, formula safety, and exports beyond 5,000 rows.

### Transactions

- [x] Define valid payment status, method, and paid-date combinations.
- [x] Clear `paid_at` and payment method when a transaction becomes unpaid or void.
- [x] Require a payment method and paid date for partial, paid, and refunded transactions.
- [x] Retain and require the original payment method/date for refunded transactions.
- [x] Make transaction-number allocation concurrency-safe with unique-index retry handling.
- [x] Add admin and staff transaction update tests, including invalid state combinations.

## P2 — Completeness, Quality, and Hardening

### Customer and user management

- [x] Add customer address and contact-preference fields to profile CRUD.
- [x] Prevent administrators from editing a Google-linked email; Google sign-in is the only relink/update path.
- [x] Treat identity relinking on administrator email edit as not applicable because linked email editing is rejected.
- [x] Add a configurable expiry time to Google reauthentication used for account deletion.

### Feedback and sentiment

- [x] Add token boundaries and negation handling to rule-based sentiment classification.
- [x] Return a validation error instead of a database exception during concurrent duplicate feedback submissions.
- [x] Add tests for negation, mixed sentiment, ownership, duplicates, and duplicate-race behavior.

### Promotion configuration

- [x] Build the planned RFM segment management screens.
- [x] Build promotion-rule create, edit, activate, and deactivate workflows.
- [x] Add authorization, validation, and CRUD tests for both modules.

### Concurrency and numbering

- [x] Make appointment-number allocation safe under simultaneous creates.
- [x] Add controlled retry behavior for unique-number collisions.
- [x] Add collision-focused tests for appointment and transaction creation.

### Code quality

- [x] Resolve all 18 Laravel Pint issues present after remediation (the original 17 plus one newly introduced style issue).
- [x] Run `./vendor/bin/pint --test` until it passes across 161 files.
- [x] Keep fixes focused and avoid unrelated refactors.

## Final Verification Gate

- [x] Run the full isolated Feature suite: 104 tests and 828 assertions passed.
- [x] Add tests for every implemented defect above; do not rely only on the original 70-test baseline.
- [x] Run PHP syntax checks (78 changed/new files), Blade compilation, Composer validation, frontend build, and Pint.
- [x] Verify create, read, update, deactivate/delete, validation, and authorization behavior for each role in the browser. Admin, customer, and staff workspaces rendered with clean consoles; cross-role routes returned 403 as expected.
- [x] Confirm the post-fix browser console and Laravel logs contain no new CRUD errors.
- [x] Run the read-only consistency audit. It reports only the two approval-gated soft-deleted staff references documented in `CRUD_DATA_REPAIR_PLAN.md`.
- [x] Verify reports and CSV exports against known requested, scheduled, created, and paid dates, including content beyond 5,000 rows.
- [x] Document the approved data-repair operation, backup hash, before/after values, and successful post-repair audit.
- [x] Update deployment and handover documentation with the final migration, test, backup, and rollback procedure.
