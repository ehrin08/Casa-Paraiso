# Casa Paraiso Database Design

## Purpose

Define the MySQL 8.4 schema implemented through Laravel migrations.

This design supports customer login, therapist scheduling, immediately confirmed appointments, receptionist operations, manual transactions, therapist commissions, RFM promotion suggestions, feedback sentiment, and management reporting.

## Design Rules

- Use Laravel migrations as the schema source of truth.
- Use unsigned big integer primary keys unless Laravel defaults change.
- Use `created_at` and `updated_at` on all business tables.
- Use `deleted_at` soft deletes only for records that should be hidden without losing history, such as services, profiles, and promotion rules.
- Store money as `decimal(10,2)`.
- Store dates and times in server time for MVP; use Asia/Manila as the business timezone in application config.
- Use MySQL 8.4-compatible column types and index names no longer than 64 characters.
- Treat 1:00 PM to 12:00 midnight as the hard booking boundary and generate appointment starts at 30-minute intervals.

## Status Values

- Appointment status: `confirmed`, `completed`, `cancelled`, `no_show`
- Payment status: `unpaid`, `partial`, `paid`, `refunded`, `void`
- Sentiment label: `positive`, `neutral`, `negative`
- Promotion suggestion status: `suggested`, `reviewed`, `applied`, `dismissed`
- Schedule exception type: `available`, `unavailable`
- User role: `admin`, `receptionist`, `staff`, `customer`
- User role also includes `super_admin`; this protected role is reserved for the configured Casa Paraiso owner email.
- Staff type: `therapist`
- Commission type: `earning`, `adjustment`
- Commission status: `pending`, `paid`

Use string columns for these status values in migrations and validate allowed values in Laravel form requests or model rules.

## Accounts

### `users`

Laravel authentication table for all login-capable users.

Key columns:

- `id`
- `name`
- `email` unique
- `google_id` nullable, unique; populated on first successful Google sign-in
- `email_verified_at` nullable
- `password` nullable legacy field; password authentication is disabled
- `role`
- `phone` nullable
- `is_active` boolean default true
- `remember_token`
- `created_at`, `updated_at`

Indexes:

- Unique index on `email`
- Index on `role`
- Index on `is_active`

Relationships:

- One user may have one staff profile.
- One user may have one customer profile.
- Receptionist users have neither staff nor customer profiles.
- Admin users record transactions; staff may review records linked to their assigned appointments.

### `staff_profiles`

Operational profile for staff users.

Key columns:

- `id`
- `user_id` unique foreign key to `users.id`
- `staff_type` string default `therapist`
- `position` nullable
- `specialization` nullable
- `bio` nullable
- `hire_date` nullable
- `is_bookable` boolean default true
- `deleted_at` nullable
- `created_at`, `updated_at`

Indexes:

- Unique index on `user_id`
- Index on `is_bookable`
- Index on `staff_type`

Relationships:

- Belongs to user.
- Has many weekly schedules, schedule exceptions, appointments, and staff-service assignments.

### `customer_profiles`

Customer profile linked to a login account.

Key columns:

- `id`
- `user_id` unique foreign key to `users.id`
- `customer_code` unique
- `birth_date` nullable
- `address` nullable
- `contact_preference` nullable
- `notes` nullable
- `first_visit_at` nullable
- `deleted_at` nullable
- `created_at`, `updated_at`

Indexes:

- Unique index on `user_id`
- Unique index on `customer_code`

Relationships:

- Belongs to user.
- Has many appointments, transactions, feedback records, and promotion suggestions.

## Services

### `services`

Spa services offered by Casa Paraiso.

Initial active services should mirror the current package menu:

- GAIA TOUCH, 60 minutes, PHP 499.00.
- TETHYS FLOW, 60 minutes, PHP 649.00.
- HESTIA WARMTH, 90 minutes, PHP 749.00.
- AURORA BREEZE, 120 minutes, PHP 849.00.

Add-ons are managed as standalone shared catalog records. The initial catalog contains Ventosa, Hot Compress, Hot Stone, 30-Minute Back Massage, and VIP Room. Admins may edit name, description, price, duration, and active state; codes remain stable and add-ons are deactivated rather than deleted. Active records may be selected with any active service. A separate RFM voucher may grant one complimentary add-on; it never discounts a paid selection. Add-on duration extends the scheduled duration.

Key columns:

- `id`
- `name`
- `slug` unique
- `description` nullable
- `duration_minutes` unsigned integer
- `price` decimal(10,2)
- `is_active` boolean default true
- `deleted_at` nullable
- `created_at`, `updated_at`

Indexes:

- Unique index on `slug`
- Index on `is_active`

Relationships:

- Has many appointments.
- Has many transactions.
- Belongs to many staff profiles through `staff_services`.

### `staff_services`

Pivot table for which staff members can perform which services.

Key columns:

- `id`
- `staff_profile_id` foreign key to `staff_profiles.id`
- `service_id` foreign key to `services.id`
- `created_at`, `updated_at`

Indexes:

- Unique composite index on `staff_profile_id`, `service_id`
- Index on `service_id`

Relationships:

- Belongs to staff profile.
- Belongs to service.

## Scheduling

### `staff_weekly_schedules`

Normal weekly working hours for bookable staff.

Key columns:

- `id`
- `staff_profile_id` foreign key to `staff_profiles.id`
- `day_of_week` tiny integer, 0 for Sunday through 6 for Saturday
- `start_time`
- `end_time`
- `ends_next_day` boolean default false
- `is_available` boolean default true
- `created_at`, `updated_at`

Indexes:

- Index on `staff_profile_id`, `day_of_week`

Rules:

- Allow multiple rows per staff and day for split shifts.
- Use `ends_next_day = true` with `end_time = 00:00:00` for a shift that reaches midnight.
- Validate the resolved end datetime is later than the start and remains inside business hours.

Relationships:

- Belongs to staff profile.

### `staff_schedule_exceptions`

Date-specific staff availability overrides.

Key columns:

- `id`
- `staff_profile_id` foreign key to `staff_profiles.id`
- `exception_date`
- `exception_type`
- `start_time` nullable
- `end_time` nullable
- `ends_next_day` boolean default false
- `reason` nullable
- `created_by` nullable foreign key to `users.id`
- `created_at`, `updated_at`

Indexes:

- Index on `staff_profile_id`, `exception_date`
- Index on `exception_date`

Rules:

- Use `exception_type = unavailable` with null times for a full-day block.
- Use `exception_type = unavailable` with start/end times for a partial-day block.
- Use `exception_type = available` to add a one-off available time range outside the weekly schedule.
- Date-specific unavailable exceptions take precedence over available and recurring windows.
- Reject changes that would leave a future confirmed appointment outside the therapist's effective schedule.

Relationships:

- Belongs to staff profile.
- Optionally belongs to the user who created the exception.

### `staff_schedule_weeks` and `staff_schedule_shifts`

Published weekly rosters provide the primary dated therapist schedule. `staff_schedule_weeks` has one Sunday `week_start_date`, optional `published_at`/`published_by`, and draft and published shift rows live in `staff_schedule_shifts`.

Rules:

- Draft shifts never affect booking availability.
- Publishing replaces only that week's published shifts after confirmed-appointment coverage is checked.
- A week without a published roster inherits the nearest earlier published roster by weekday; the legacy recurring weekly schedules remain the fallback until the first roster is published.
- Shifts use 30-minute boundaries from 1:00 PM through midnight and may be split, but cannot overlap for a therapist/date/version.
- Existing date-specific exceptions are applied after roster availability.

## Appointments

### `appointments`

Customer appointments and confirmed bookings.

Key columns:

- `id`
- `appointment_number` unique
- `customer_profile_id` foreign key to `customer_profiles.id`
- `service_id` foreign key to `services.id`
- `staff_profile_id` nullable foreign key to `staff_profiles.id`
- `preferred_staff_profile_id` nullable foreign key to `staff_profiles.id`
- `promotion_suggestion_id` nullable foreign key to `promotion_suggestions.id`
- `requested_start_at`
- `scheduled_start_at` nullable
- `scheduled_end_at` nullable
- `status`
- `customer_notes` nullable
- `internal_notes` nullable
- `confirmed_at` nullable
- `completed_at` nullable
- `cancelled_at` nullable
- `cancelled_by` nullable foreign key to `users.id`
- `created_by` nullable foreign key to `users.id`
- `updated_by` nullable foreign key to `users.id`
- `created_at`, `updated_at`

Indexes:

- Unique index on `appointment_number`
- Index on `customer_profile_id`, `status`
- Index on `staff_profile_id`, `scheduled_start_at`, `scheduled_end_at`
- Index on `preferred_staff_profile_id`
- Index on `service_id`
- Index on `status`
- Index on `requested_start_at`

Rules:

- New customer and admin-created bookings start as `confirmed` and require an assigned eligible therapist and scheduled time.
- Customer self-booking requires at least 30 minutes of lead time in `Asia/Manila`; staff-operated booking and rescheduling may use any future aligned start.
- The interface exposes one appointment time. `scheduled_start_at` is canonical, while `requested_start_at` remains as a synchronized compatibility field for the existing schema.
- A preferred therapist is a customer preference only; final assignment remains in `staff_profile_id`.
- Customer booking locks eligible therapist rows, rechecks availability, assigns one therapist, and reserves capacity in one transaction.
- An available preferred therapist is assigned first. Otherwise choose the therapist with the fewest future confirmed bookings, then the lowest profile ID.
- `confirmed` appointments must have `staff_profile_id`, `scheduled_start_at`, and `scheduled_end_at`.
- `scheduled_end_at` should be calculated from service duration unless manually adjusted by admin/staff.
- Prevent overlapping `confirmed` appointments for the same staff member, including concurrent customer submissions.
- Completed appointments can be used for transaction and RFM reporting.

Relationships:

- Belongs to customer profile, service, optionally staff profile, and optionally one promotion suggestion used as an add-on voucher.
- Has many paid appointment add-on snapshots.

### `appointment_addons`

Paid add-on snapshots attached to an appointment.

Key columns:

- `appointment_id` foreign key to `appointments.id`
- `addon_code`, `addon_name`, `price`, and `duration_minutes`

Rules:

- One row per appointment/add-on code.
- Snapshot catalog values so later configuration changes do not alter booking history.
- Payment defaults add these prices to the base service price. RFM voucher add-ons do not create a paid row.
- Has many transactions.
- Has one feedback record in the MVP.
- Has many status logs.

### `appointment_status_logs`

Audit trail for appointment status changes.

Key columns:

- `id`
- `appointment_id` foreign key to `appointments.id`
- `from_status` nullable
- `to_status`
- `changed_by` nullable foreign key to `users.id`
- `reason` nullable
- `created_at`, `updated_at`

Indexes:

- Index on `appointment_id`
- Index on `to_status`
- Index on `changed_by`

Relationships:

- Belongs to appointment.
- Optionally belongs to the user who changed the status.

## Transactions

### `transactions`

Manual service payment and transaction records.

Key columns:

- `id`
- `transaction_number` unique
- `customer_profile_id` foreign key to `customer_profiles.id`
- `appointment_id` nullable foreign key to `appointments.id`
- `service_id` nullable foreign key to `services.id`
- `amount` decimal(10,2)
- `payment_status`
- `payment_method` nullable
- `paid_at` nullable
- `recorded_by` foreign key to `users.id`
- `notes` nullable
- `created_at`, `updated_at`

Indexes:

- Unique index on `transaction_number`
- Index on `customer_profile_id`, `created_at`
- Index on `appointment_id`
- Index on `service_id`
- Index on `payment_status`
- Index on `recorded_by`

Rules:

- Use `payment_status = unpaid` when recording an unpaid service balance.
- Use `payment_status = paid` when the full amount is received.
- Use `payment_status = partial` when only part of the amount is received.
- Use only completed or confirmed appointments for normal service transactions.
- Include completed, paid transactions in monetary RFM calculations by default.

Relationships:

- Belongs to customer profile.
- Optionally belongs to appointment and service.
- Belongs to recorder user.

### `therapist_commissions`

Immutable paid payout history and pending commission reconciliation records.

Key columns:

- `staff_profile_id`, `appointment_id`, and `transaction_id` foreign keys
- `primary_transaction_id` nullable unique foreign key populated only for primary earning rows
- `adjusts_commission_id` nullable self-reference for adjustment rows
- `commission_type`, `status`, `basis_amount`, `commission_rate`, and signed `commission_amount`
- `earned_at`, nullable `paid_at`, nullable `paid_by`, and nullable `notes`

Rules:

- Create one primary earning per transaction only when the appointment is completed, has an assigned therapist, and the transaction is fully paid.
- Snapshot the system rate on creation. Recalculations for that earning continue using its stored rate.
- Pending earnings may be recalculated or reduced to zero. Paid rows never change.
- Corrections after payout create or update one pending signed reconciliation adjustment; settled adjustments remain immutable.
- Payout records document external settlement and do not transfer funds.

### `application_settings`

Singleton-style editable business defaults used by the Admin Settings workspace.

Key columns:

- `id`
- `business_name`
- `contact_email` nullable
- `contact_phone` nullable
- `business_address` nullable
- `location_landmarks`, `facebook_url`, `messenger_url`, and `map_url` nullable public contact fields
- `company_history` nullable plain-text public narrative
- `organization_chart` nullable JSON object containing the owner tier, administrator tier, and up to eight team roles
- `default_payment_method`
- `updated_by` nullable foreign key to `users.id`
- `created_at`, `updated_at`

Rules:

- The application uses the first row and writes the canonical row with `id = 1`.
- Safe configuration defaults are used before the migration is applied, so public and payment forms remain available while saving is disabled.
- The payment default only prefills forms and never marks a transaction paid or initiates a transfer.
- Admin-managed company-profile content is escaped when rendered publicly; an entirely blank history and chart keep the landing-page About section hidden.
- Organizational chart content is role-based and does not query or expose staff accounts.
- Booking hours, intervals, timezone, commission rate, and security controls remain configuration-controlled.

## RFM Promotions

### `rfm_segments`

Five system-controlled customer-reward presets plus retained legacy segment records for historical references.

Key columns:

- `id`
- `name`
- `preset_key` nullable unique identifier for a fixed customer-reward group
- `description` nullable
- `addon_code` nullable configured complimentary add-on for future rewards
- `recency_min_days` nullable
- `recency_max_days` nullable
- `frequency_min` nullable
- `frequency_max` nullable
- `monetary_min` nullable decimal(10,2)
- `monetary_max` nullable decimal(10,2)
- `is_active` boolean default true
- `created_at`, `updated_at`

Indexes:

- Index on `is_active`

Relationships:

- Has many promotion rules.
- Has many promotion suggestions.

### `promotion_rules`

Legacy rule definitions retained only for historical suggestion references. New customer rewards use the preset segment directly.

Key columns:

- `id`
- `rfm_segment_id` foreign key to `rfm_segments.id`
- `name`
- `description` nullable
- `suggested_offer` nullable
- `addon_code` nullable configuration-backed add-on identifier
- `is_active` boolean default true
- `deleted_at` nullable
- `created_at`, `updated_at`

Indexes:

- Index on `rfm_segment_id`
- Index on `is_active`

Relationships:

- Belongs to RFM segment.
- Has many promotion suggestions.

### `promotion_suggestions`

Stored customer-reward snapshots that may become customer-selectable add-on vouchers and remain available for audit and reporting.

Key columns:

- `id`
- `customer_profile_id` foreign key to `customer_profiles.id`
- `rfm_segment_id` nullable foreign key to `rfm_segments.id`
- `promotion_rule_id` nullable foreign key to `promotion_rules.id`
- `recency_days` nullable integer
- `frequency_count` nullable integer
- `monetary_total` nullable decimal(10,2)
- `suggested_offer`
- `addon_code` nullable snapshot of the configured add-on
- `status`
- `reviewed_by` nullable foreign key to `users.id`
- `reviewed_at` nullable
- `applied_at` nullable
- `dismissed_at` nullable
- `expires_at` nullable redemption deadline snapshot
- `notes` nullable
- `created_at`, `updated_at`

Indexes:

- Index on `customer_profile_id`, `status`
- Index on `rfm_segment_id`
- Index on `promotion_rule_id`
- Index on `addon_code`
- Index on `status`
- Index on `created_at`

Rules:

- Create a reward automatically when a completed appointment’s transaction first becomes paid and a fixed active preset matches.
- Never apply monetary or percentage discounts.
- An available reward is customer-selectable during booking; selection reserves it. A reward remains valid for a confirmed appointment even if its expiry passes later.
- At most one available or reserved reward may exist for a customer. Admin may dismiss only an available, unexpired reward; cancellation or no-show releases a reservation with its original expiry.
- User-facing reward states are derived as available, reserved, used, dismissed, or expired; no scheduler is required.
- Keep old suggestions for audit and reporting.

Relationships:

- Belongs to customer profile.
- Optionally belongs to RFM segment and promotion rule.
- Optionally belongs to reviewer user.

## Feedback And Sentiment

### `feedback`

Customer service feedback and simple sentiment classification.

Key columns:

- `id`
- `customer_profile_id` foreign key to `customer_profiles.id`
- `appointment_id` nullable foreign key to `appointments.id`
- `service_id` nullable foreign key to `services.id`
- `rating` tiny integer
- `comment` nullable
- `sentiment_label`
- `sentiment_score` nullable decimal(5,2)
- `sentiment_analysis_version` nullable classifier version
- `sentiment_evidence` nullable JSON explanation (rating label, lexical score, matched-word count)
- `submitted_at`
- `created_at`, `updated_at`

Indexes:

- Index on `customer_profile_id`
- Index on `appointment_id`
- Index on `service_id`
- Index on `rating`
- Index on `sentiment_label`
- Index on `submitted_at`

The additive `feedback_topics` table stores normalized topic findings (`topic_key`, `polarity`, and matched terms) with a unique feedback/topic/polarity constraint and topic/polarity index. Current code-controlled topics are care quality, therapist service, cleanliness/ambience, scheduling/wait time, value/pricing, and pain/comfort.

The hybrid extension adds private append-only `feedback_sentiment_runs` for rules/model/reviewed candidates and `feedback_annotations` for admin adjudication. `feedback.sentiment_source` and `feedback.sentiment_confidence` identify the authoritative projection; customer-facing resources do not expose model diagnostics.

Rules:

- Validate rating from 1 to 5.
- Default sentiment logic maps high ratings to positive, middle ratings to neutral, and low ratings to negative, then refines with code-controlled English, Tagalog, and Taglish keyword, phrase, and nearby-negation rules.
- Classifier version `2.0.0` also stores deterministic evidence and topic findings; `casa:reclassify-sentiment` remains dry-run-first and synchronizes derived metadata and topic rows when `--apply` is explicitly used.
- Allow only one feedback record per appointment in the MVP.

Relationships:

- Belongs to customer profile.
- Optionally belongs to appointment and service.

## Reporting Support

No separate reporting tables are required for the MVP. Reports should query existing appointment, transaction, promotion suggestion, and feedback tables.

Recommended report filters:

- Date range
- Appointment status
- Staff member
- Service
- Payment status
- Customer
- Promotion suggestion status
- Sentiment label

Recommended export formats:

- CSV for tabular reports
- PDF only if later required by capstone documentation or business handover

## Seed Data

Initial seeders should create:

- Admin user
- Optional demo staff users
- Optional demo customer users
- Default Casa Paraiso package services with duration and price
- Static public content for add-on prices and business hours
- Staff-service assignments
- Weekly staff schedules
- Default RFM segments
- Default promotion rules

Suggested default RFM segments:

- New customer
- Loyal customer
- At-risk customer
- High-value customer
- Inactive customer

Suggested default payment methods:

- Cash
- GCash
- Bank transfer
- Other

## Migration Order

Use this order when creating Laravel migrations:

1. `users`
2. Laravel auth support tables, if selected by the scaffold
3. `staff_profiles`
4. `customer_profiles`
5. `services`
6. `staff_services`
7. `staff_weekly_schedules`
8. `staff_schedule_exceptions`
9. `appointments`
10. `appointment_status_logs`
11. `transactions`
12. `therapist_commissions`
13. `application_settings`
14. `rfm_segments`
15. `promotion_rules`
16. `promotion_suggestions`
17. `feedback`

## Acceptance Scenarios

- A customer account can instantly book an available service date/time.
- The system atomically assigns the preferred available therapist or the least-booked eligible therapist.
- The system can detect an overlapping confirmed appointment for the same staff member.
- Admin can finish an appointment and atomically record its manual transaction.
- Fixed RFM presets automatically issue at most one eligible customer reward after a completed paid transaction.
- A customer can attach one eligible add-on voucher during booking without changing the appointment price or duration.
- Admin can configure fixed customer rewards and dismiss an available reward; appointment cancellation/no-show releases a reserved voucher.
- Customer can submit one feedback record for a completed appointment.
- Admin can filter reports by appointment, transaction, promotion, and feedback fields without direct database access.

## System Error Reporting

- `system_error_reports` groups matching HTTP 4xx/5xx responses by status, category, exception class, route, and application source frame. It stores encrypted sanitized diagnostics, first/last seen timestamps, count, and resolution metadata.
- `system_error_occurrences` keeps one request-reference record per captured response, with method, query-free path, route, optional user, and occurrence timestamp.
- Occurrences are retained for 30 days. Resolved reports with no retained occurrences are pruned; unresolved reports remain available until reviewed.
