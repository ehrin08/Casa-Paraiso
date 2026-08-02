# Therapist, Receptionist, Booking, and Commission Tasks

## Goal

Organize therapists under the Staff account category, introduce a restricted Receptionist workspace for front-desk operations, let customers book appointments from their appointment calendar, and track a therapist commission of 22% for fully paid completed services.

## Task 1: Add the Therapist Staff Type

- [x] Add a `staff_type` column to `staff_profiles`.
- [x] Set the default and existing staff type to `therapist`.
- [x] Add `StaffProfile::TYPE_THERAPIST` and the list of supported staff types.
- [x] Update the Staff Profile model, factory, validation, and seed-safe defaults to use the new field.
- [x] Keep `staff` as the internal user role and retain the existing `/staff` routes.
- [x] Keep `position` as a separate free-text job title or rank.
- [x] Replace customer-facing and staff-facing "Staff" labels with "Therapist" where the person performs spa services.
- [x] Show a Therapist type badge in Team & Services screens.
- [x] Preserve existing therapist schedules, service assignments, appointment relationships, and eligibility safeguards.

## Task 2: Add the Receptionist User Role

- [x] Add `User::ROLE_RECEPTIONIST` to the supported roles.
- [x] Add `User::isReceptionist()`.
- [x] Redirect receptionist accounts to `/reception/dashboard` after login.
- [x] Add Receptionist to the roles assignable by the super administrator.
- [x] Allow the super administrator to create, activate, deactivate, and reassign receptionist accounts.
- [x] Ensure receptionist accounts do not receive Staff or Customer profiles.
- [x] Update role factories, seed-safe defaults, navigation labels, and workspace routing.

## Task 3: Build the Receptionist Workspace

- [x] Add a dedicated `/reception` route group protected by authentication, active-account, verified-email, and receptionist-role middleware.
- [x] Add a Receptionist dashboard focused on today's bookings, upcoming visits, customers, and payments.
- [x] Add Receptionist navigation for Dashboard, Appointments, Customers, Payments, and Profile.
- [x] Reuse shared operational services and components instead of duplicating appointment and payment business rules.
- [x] Keep route names, links, redirects, validation errors, and modal actions correct for the Receptionist workspace.

### Receptionist Appointment Tasks

- [x] Let receptionists view the weekly booking calendar and therapist availability.
- [x] Let receptionists create a confirmed appointment for a customer.
- [x] Let receptionists view and edit appointments.
- [x] Let receptionists cancel appointments or mark them as no-show.
- [x] Let receptionists complete an eligible appointment and record its payment.
- [x] Prevent receptionists from editing therapist schedules or availability exceptions.

### Receptionist Customer and Payment Tasks

- [x] Let receptionists search and view customer contact information.
- [x] Let receptionists view customer appointment and payment history.
- [x] Let receptionists update permitted customer contact information and operational notes.
- [x] Let receptionists create and edit payment records.
- [x] Exclude customer feedback, promotion insights, and therapist commissions from Receptionist screens.

### Receptionist Access Restrictions

- [x] Deny access to Team & Services management.
- [x] Deny access to therapist schedule management.
- [x] Deny access to RFM segments, promotion rules, and promotion suggestions.
- [x] Deny access to feedback analytics, reports, and exports.
- [x] Deny access to commission records and payout actions.
- [x] Deny access to settings and user administration.
- [x] Keep existing Admin and Super Admin permissions unchanged.

## Task 4: Merge Customer Booking into My Appointments

- [x] Load active services and eligible therapists on the customer appointment calendar page.
- [x] Add an accessible Book Appointment modal to My Appointments.
- [x] Open the modal from the main Book Appointment button.
- [x] Open the modal from the selected-day panel and preselect that date when possible.
- [x] Reuse the existing customer booking form and normal POST route.
- [x] Preserve service selection and optional preferred-therapist selection.
- [x] Preserve live availability lookup and valid 30-minute start slots.
- [x] Continue confirming and reserving successful bookings transactionally.
- [x] Reopen the modal with submitted values and inline errors when validation or availability checks fail.
- [x] Preserve accessible modal focus, keyboard navigation, Escape/close behavior, and responsive mobile layout.
- [x] Keep `/customer/appointments/create` working as a full-page fallback.
- [x] Standardize actions and success messages on "Book appointment" and remove outdated "Request a visit" wording.
- [x] Keep the existing calendar and availability JSON feeds unchanged.

## Task 5: Add the Therapist Commission Tracker

### Commission Configuration and Records

- [x] Add `casa.commissions.therapist_rate` with a fixed value of `0.22`.
- [x] Add a `therapist_commissions` table and corresponding model.
- [x] Link each commission to its therapist profile, appointment, transaction, and optional adjusted commission.
- [x] Keep commission relationships connected to soft-deleted historical therapist profiles.
- [x] Store the transaction amount used as the commission basis.
- [x] Store the snapshotted commission rate so historical records remain accurate if configuration changes later.
- [x] Store the signed commission amount rounded to two decimal places.
- [x] Add commission types `earning` and `adjustment`.
- [x] Add commission statuses `pending` and `paid`.
- [x] Store the earned date, payout date, payout administrator, and optional notes.
- [x] Enforce one primary earning commission per transaction while allowing multiple traceable adjustments.
- [x] Add commission relationships to the Staff Profile, Appointment, Transaction, and User models.

### Commission Calculation and Synchronization

- [x] Add a commission synchronization service shared by appointment completion and transaction updates.
- [x] Calculate commission as 22% of the actual transaction amount.
- [x] Create an earning only when the linked appointment is completed, has an assigned therapist, and its transaction is fully paid.
- [x] Exclude unpaid, partial, refunded, void, unlinked, cancelled, and no-show records from commission earnings.
- [x] Create the earning immediately when completion records a fully paid transaction.
- [x] Create the earning later when a transaction linked to a completed appointment changes from unpaid or partial to fully paid.
- [x] Recalculate an unpaid pending earning when its transaction amount changes.
- [x] Reduce the correct pending earning to zero when its transaction becomes unpaid, partial, refunded, or void.
- [x] Keep paid commission records immutable as historical payout records.
- [x] When a paid commission's source transaction changes, create a pending signed adjustment for the difference between the previously earned amount and the newly correct amount.
- [x] Create negative adjustments for reductions, refunds, voids, or loss of paid eligibility.
- [x] Create positive adjustments when a corrected transaction increases the commission owed.
- [x] Make synchronization idempotent so repeated processing does not duplicate earnings or adjustments.

### Admin Commission Workspace

- [x] Add commission routes under `/admin/commissions` for listing, viewing, and recording payouts.
- [x] Add a Commissions entry under the Admin Payments workspace.
- [x] Show pending, paid, and net commission totals.
- [x] Show therapist, service, appointment, transaction, commission type, basis amount, rate, commission amount, earned date, status, and payout date.
- [x] Add therapist, status, and earned-date filters using the shared compact list pattern and fixed pagination.
- [x] Let Admin and Super Admin inspect the appointment and transaction behind each commission.
- [x] Let Admin and Super Admin mark an individual pending earning or adjustment as paid.
- [x] Require a payout date and allow an optional payout note when marking a commission paid.
- [x] Record the administrator who marked the payout.
- [x] Prevent paid commission records from being edited or returned to pending.

### Therapist Commission Workspace

- [x] Add read-only commission routes under `/staff/commissions`.
- [x] Add a My Commissions entry to Therapist navigation.
- [x] Show only commissions belonging to the signed-in therapist.
- [x] Show pending balance, paid total, and net commission total.
- [x] Show service, appointment, transaction amount, rate, commission or adjustment amount, earned date, status, and payout date.
- [x] Prevent therapists from changing commission status, payout details, or notes.
- [x] Deny Customer, Receptionist, and Guest access to all commission routes and data.

## Task 6: Update Documentation

- [x] Update `docs/MVP_SCOPE.md` with the Receptionist role, merged customer booking flow, and therapist commission tracker.
- [x] Update `docs/DATABASE_DESIGN.md` with the Receptionist role, Therapist staff type, commission table, relationships, statuses, and invariants.
- [x] Update `docs/SCREEN_FLOW.md` with Receptionist screens, customer modal booking, Admin commission management, and Therapist commission viewing.
- [x] Update `docs/PROJECT_MEMORY.md` with the new roles, routes, models, commission service, access boundaries, and business rules.
- [x] Remove documentation that describes new bookings as pending appointment requests.
- [x] Document that commission payout records track external settlement and do not transfer money.

## Database Safety

- [x] Create the required migrations without running them automatically.
- [x] Obtain explicit approval before running migrations against any local or production database.
- [x] Confirm the target environment before any approved database operation.

## Completion Criteria

- [x] Therapists remain Staff users and are explicitly classified as `therapist`.
- [x] Receptionists have a restricted `/reception` front-desk workspace and no commission access.
- [x] Customers can book from My Appointments without leaving the calendar workflow.
- [x] Fully paid completed services create a pending therapist commission equal to 22% of the actual transaction amount.
- [x] Admin and Super Admin can record commission payouts without modifying historical paid records.
- [x] Therapists can view only their own commission history and totals.
- [x] Post-payout transaction corrections create traceable signed adjustments.
- [x] Existing role, scheduling, booking, transaction, and payment behavior remains compatible.

## Assumptions

- Therapist is the only supported staff type in this change.
- Receptionists may view therapist availability but may not edit it.
- Admin remains the management role and retains front-desk operational capabilities.
- New bookings are confirmed immediately; no pending request or approval state is introduced.
- The 22% commission rate is system-wide with no per-service or per-therapist override.
- Only the therapist assigned to the completed appointment earns commission.
- Manual transactions without a completed linked appointment do not earn commission.
- Commission payout records document external payment and do not transfer funds.
