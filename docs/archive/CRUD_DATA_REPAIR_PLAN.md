# CRUD Data Repair Plan

This plan records the read-only findings from the 2026-07-12 CRUD audit. No repair in this document has been executed. The project database safety gate requires explicit approval for the exact records and operation before any update is run.

> Superseded for future operations: the pending appointment workflow was retired on 2026-07-14. This document preserves the 2026-07-12 repair record; its pending-request recommendations are historical only.

## Current Findings

| Appointment | Status | Service | Affected field | Current value | Issue |
| --- | --- | --- | --- | --- | --- |
| `APT-DEMO-PENDING` | Pending | GAIA TOUCH | `preferred_staff_profile_id` | `1` | Profile 1 is soft-deleted. |
| `APT-DEMO-CONFIRMED` | Confirmed | TETHYS FLOW | `staff_profile_id` | `1` | Profile 1 is soft-deleted, so no active staff workspace owns the booking. |
| `APT-DEMO-COMPLETED` | Completed | GAIA TOUCH | `staff_profile_id` | `1` | Profile 1 is soft-deleted; the historical therapist identity is hidden by default relationships. |

Active staff profile `2` belongs to Demo Therapist and is active/bookable. It is currently eligible for TETHYS FLOW, HESTIA WARMTH, and AURORA BREEZE, but not GAIA TOUCH.

## Pre-Execution Read-Only Recheck

Rechecked on 2026-07-12 at 11:04 AM Asia/Manila. No data was changed.

- `APT-DEMO-CONFIRMED` is scheduled for 3:00 PM–4:00 PM Asia/Manila on 2026-07-12.
- Demo Therapist (staff profile `2`) has an available 1:00 PM–12:00 midnight working window, performs TETHYS FLOW, and has no confirmed overlap for that hour.
- Reassignment to profile `2` is therefore a valid recommended repair only while the appointment remains future and confirmed. If the scheduled start has passed before approval, do not backdate a reassignment; record the actual completed, cancelled, or no-show outcome chosen by the business instead.
- `APT-DEMO-PENDING` remains pending for GAIA TOUCH. Because no active therapist currently performs GAIA TOUCH, the recommended minimal repair is to clear `preferred_staff_profile_id` while keeping the request pending.
- `APT-DEMO-COMPLETED` remains valid historical evidence. Its original profile `1` reference should not be rewritten; the application now loads that soft-deleted identity for historical display.

## Approved Execution Record

The user explicitly approved the documented operations on 2026-07-12. The repair was executed at approximately 11:10 AM Asia/Manila after a successful dry run.

### Backup

- File: `storage/app/backups/casa_paraiso_pre_crud_repair_20260712_1110.sql`
- Size: 43,567 bytes
- SHA-256: `096BD133FAAD55A6D54EB57B776EB0A11DFF4F6587B141D5BF80CF268343B015`
- Created with MariaDB `mariadb-dump` using a consistent single transaction before any repair write.

### Execution

```powershell
.\scripts\casa-docker.ps1 compose exec -T laravel.test php artisan casa:repair-approved-appointment-references
.\scripts\casa-docker.ps1 compose exec -T laravel.test php artisan casa:repair-approved-appointment-references --execute
```

The first command was a read-only dry run. The second command applied both approved operational changes atomically through the appointment workflow.

### Before And After

| Appointment | Before | After |
| --- | --- | --- |
| `APT-DEMO-CONFIRMED` | Confirmed, `staff_profile_id = 1` (deleted) | Confirmed, `staff_profile_id = 2` (Demo Therapist); schedule retained at 3:00 PM–4:00 PM |
| `APT-DEMO-PENDING` | Pending, `preferred_staff_profile_id = 1` (deleted) | Pending, preference cleared; no staff or schedule reservation added |
| `APT-DEMO-COMPLETED` | Completed, `staff_profile_id = 1` (deleted) | Unchanged as historical evidence |

The affected operational records received internal audit notes and `updated_by = 2`. The post-repair `casa:audit-crud-integrity --verbose` command reported zero issues across every check.

## Recommended Treatment

### Historical records

- Preserve the original staff/profile reference for completed history.
- Load soft-deleted staff and customer profiles for historical display.
- Do not reassign completed appointments to a different therapist merely to remove an orphan warning.

### Active confirmed appointments

- Recheck whether the appointment is still operationally active.
- If it remains confirmed and must be serviced, choose an active, bookable therapist who performs the service and whose schedule covers the appointment.
- Use the normal appointment workflow so overlap, schedule, status-log, and audit fields remain correct.
- Do not issue a direct SQL reassignment that bypasses scheduling checks.
- For the current `APT-DEMO-CONFIRMED` record, the recommended approved operation is reassignment to Demo Therapist (profile `2`) through `AppointmentWorkflow`, provided the visit is still future and confirmed when execution begins.

### Pending preferences

- Clear an unavailable preferred therapist only after business confirmation, or replace it with a customer-approved active therapist.
- Keep the request pending; a preference must not reserve capacity.
- For the current `APT-DEMO-PENDING` record, the recommended approved operation is to clear the deleted preference. Adding GAIA TOUCH eligibility to another therapist is a separate business decision and must not be inferred from this repair.

## Required Approval Before Execution

Record approval must name:

1. Each appointment number to change.
2. The intended new therapist or decision to clear the preference.
3. Whether any service eligibility assignment may also change.
4. Whether the confirmed appointment should instead be cancelled, completed, or marked no-show.
5. Confirmation that a backup/export exists.

## Safe Execution Sequence

1. Export the target database before repair.
2. Record the affected rows and their original values.
3. Put the application in a short maintenance window if the records are actively used.
4. Execute the repair through an application service or one-time Artisan command inside a database transaction.
5. Recheck staff eligibility, schedule coverage, and confirmed overlap while holding the relevant locks.
6. Write an appointment status/audit note explaining the repair.
7. Verify the admin, staff, customer, calendar, and report views.
8. Export the repaired records and store the before/after evidence with the handover documents.

## Rollback

If verification fails:

1. Restore the captured original foreign keys and status metadata inside a transaction, or restore the pre-repair database export.
2. Clear application caches only after the database state is confirmed.
3. Repeat the read-only orphan and status-integrity audit.
4. Document the failure and do not retry with a different therapist without new approval.

## Production Notes

- Use Hostinger hPanel/phpMyAdmin exports for shared-hosting backups and restores.
- Never place production credentials in this repository or command history.
- Test the repair against an isolated copy before applying it to production.
- Docker commands are for local development only; production remains Hostinger shared/web hosting unless the deployment target changes.
