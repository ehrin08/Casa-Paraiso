# Casa Paraiso Screen Flow

## Purpose

Document the implemented MVP pages, role-based navigation, shared workspace behavior, and main user journeys. Use this flow when extending Laravel routes, controllers, Blade views, or navigation.

The implemented interface uses separate dashboards for admin, receptionist, therapist, and customer. Authenticated roles use a persistent sidebar on desktop. Operational roles use a navigation drawer on smaller screens, while customers use a compact header plus bottom navigation for Appointments, Feedback, and Profile. Customers land on appointment-focused pages after login.

## Route Groups

- Public: unauthenticated pages for landing, service browsing, login, and registration.
- Auth: login, registration, password reset, logout, and authenticated redirects.
- Admin: full management access.
- Receptionist: restricted front-desk appointments, customers, and payments.
- Staff/Therapist: assigned daily operations and personal commissions.
- Customer: appointment booking, status, history, feedback, and profile access.

Route prefixes:

- `/`
- `/login`
- `/register`
- `/admin`
- `/reception`
- `/staff`
- `/customer`

## Shared Layout Rules

- Use Laravel Blade server-rendered pages with Tailwind CSS.
- Use a shared authenticated layout shell with a compact 256px role-specific desktop sidebar and consistent page-heading wrapper.
- Admin and staff collapse the sidebar into a mobile drawer.
- Customer small-screen navigation uses a persistent three-item bottom bar for My appointments, My feedback, and My profile.
- Admin and staff pages should use the persistent sidebar with task-based navigation, page title, search/filter area where needed, and clear primary action buttons.
- Customer pages should stay simpler and appointment-first, with sidebar navigation focused on My appointments, My feedback, and My profile.
- Complex management CRUD uses dedicated create/edit pages. Appointment create and admin/reception edit actions use the centered booking wizard with full-page route fallbacks. Other contextual dialogs include the atomic Admin Finish service flow, short feedback or note entry, and destructive/action confirmation.
- Use compact stat strips for detail and calendar context; reserve larger metric cards for dashboards and analytics-heavy summaries.
- Paginate authenticated record lists at the fixed server-controlled size of 15. Preserve filters and sorting across pages, show the result range on every non-empty page, and do not expose a page-size selector.
- On screens narrower than 1024px, list filters collapse behind a labeled toggle that exposes its expanded state and active-filter count. Show Clear filters only when a filter is active.
- Desktop pagination uses numbered controls; mobile pagination uses Previous, Page X of Y, and Next. Empty result sets show their empty state without pagination.
- Keep operational tables inside labeled, keyboard-focusable horizontal-scroll regions. Workspace calendar week strips and customer month grids may scroll horizontally when seven full-size date targets do not fit; booking wizard calendars fit seven columns without horizontal scrolling.
- Appointment calendars, dashboard previews, detail-history previews, and form selector collections remain unpaginated. The admin service queue uses 15-record pagination with `queue_page` and `#service-queue`.
- All role dashboards should show only actions the current role is allowed to perform.
- Admin, receptionist, and therapist sidebars show their unread customer-booking number beside Appointments or My appointments. The number updates live and clears after the appointment page is displayed; the Notifications inbox remains available as history without its own badge.
- Avoid SPA-only navigation patterns; pages should work as standard Laravel routes.

### Workspace navigation

- Admin: Daily work (Today, Appointments, Customers); Manage (Therapists, Services, Payments, Commissions, Attendance); Review (Customer rewards, Feedback, Reports); Account (Notifications, Settings, My profile). Super admins also see User accounts and System issues under Account.
- Receptionist: Daily work (Today, Appointments, Customers, Payments, Staff attendance); Account (Notifications, My profile).
- Therapist: Daily work (Today, My appointments, My attendance); Review (Customers, Payments, My commissions, Feedback); Account (Notifications, My profile).
- Customer: My care (My appointments, My feedback); Account (My profile). The mobile bottom navigation uses those same three labels.
- Each menu choice opens its own existing route and highlights only its related pages. The desktop sidebar, mobile drawer, and customer bottom navigation retain their current responsive behavior.

## Notifications

- `/notifications` is available to admin, super-admin, receptionist, and therapist accounts. All is the default; All/Unread navigation and GET `q` search retained customer names, services, and booking numbers. Counts follow search across all pages, and pagination remains fixed at 15.
- The cream list and booking-detail panels use a 62/38 split at 1280px and above, stacking below that width. Booking-number links select a notification UUID on the current filtered page and anchor to its details without marking it read. The first result is selected by default; search, tab, and pagination links reset selection.
- Rows retain alert snapshot summaries; the detail panel shows current customer contacts, service, actual scheduled duration, therapist, Manila schedule, received time, read status, and escaped customer notes. Missing or reassigned bookings show an unavailable state without exposing live contacts/notes or enabling booking/calendar actions.
- View booking submits the existing PATCH action, verifies recipient ownership and current booking access, marks the alert read, and opens the role-scoped detail page. Mark all as read retains its existing recipient-only PATCH workflow.
- View in calendar opens the existing role appointment index with `calendar_date=YYYY-MM-DD`, booking mode, and `#booking-calendar`. The supplied date selects its containing week and day; admin weeks begin Monday and reception/therapist weeks begin Sunday. Existing schedule-page acknowledgement behavior remains in use.

## Admin Attendance

- `/admin/attendance` opens today's date in Asia/Manila. GET `date=YYYY-MM-DD` selects a day; `all_dates=1` opens history and takes precedence. Therapist and On duty/Completed filters combine with the date mode. Legacy inclusive `date_from`/`date_to` URLs remain supported without a new date mode.
- The page uses separate filters and a fixed 15-record list beside selected record details, stacking below 1280px. Rows sort by attendance date then ID descending. GET `selected` selects only within the current page, defaults to its first record, and resets on filtering/pagination; `selected=0` closes the panel. Clear filters opens all history.
- Correct record opens the selected correction form with an action, corrected Manila date/time, reason category, and up to 300 characters of details (required for Other). The existing PATCH route also accepts legacy free-text reasons. Corrections preserve date/time-order restrictions, transaction locking, actor/source, and prior audit events. Validation errors retain values on that record; successful corrections preserve filters, clamp an empty last page, and select a remaining matching row if needed. Cancel resets the form. The panel shows current saved values and audit activity.
- Reception retains its QR station; therapists retain their immediate verified scan page. Camera access and role restrictions are unchanged.

## Receptionist Screens

- Dashboard: today’s bookings, upcoming visits, customer count, and payment activity.
- Appointments: weekly bookings and read-only therapist availability; confirmed create/edit, cancellation, no-show, and completion/payment actions.
- Customers: search, contact details, appointment/payment history, and permitted contact/operational-note updates.
- Payments: create, edit, and review manual transactions.
- Profile: shared account settings. Receptionists cannot access schedules, services, insights, reports, exports, settings, user administration, or commissions.

## Embedded Booking And Commission Screens

- Booking opens in a centered three-step modal for admin, super-admin, reception, and customers. Create buttons, operational calendar selections, and admin/reception detail edit links open it. Direct create/edit URLs remain full-page fallbacks. Other workspace dialogs retain their existing presentation.
- Treatment details starts with a typed customer name and optional phone for admin, super-admin, and reception. Debounced, keyboard-accessible suggestions show existing guest/enrolled name, phone, code, and profile type; only explicit selection reuses a profile. Editing the name clears that identity. Unmatched names create reusable guests on final save, with no account required. Customer self-booking stays under the signed-in profile. Service, paid add-ons, therapist preference, and customer vouchers retain their rules.
- Date and time places the calendar on the left and a native Start time dropdown on the right, with operational Assigned therapist immediately below. Dates follow the store's every-day 1:00 PM–midnight Manila hours, independent of staff capacity. All 30-minute starts appear; lead-time, closing/duration, roster, and capacity rejections are disabled and labeled Unavailable. New times and therapist assignments require verified availability. Customer assignment remains automatic. Review shows treatment, start/end, therapist, add-ons, total, notes, operational internal notes, and permitted edit-status controls.
- Steps preserve values and validate before advancing; only the final action submits the existing Laravel form. Failed validation restores entries and the earliest affected step. Availability changes with service, duration, preference, and voucher selections; loading, failure with Retry, and empty states block unverified new selections. Confirmed reservations are rechecked transactionally on save. Historical booking details are read-only, and unchanged existing schedules allow permitted notes/status edits with retained inactive selections.
- The title, step indicator, and footer remain visible while the body scrolls. Small screens stack the calendar and time/therapist column without horizontal scrolling. Customer suggestions support Up/Down, Enter to select, and Escape to close suggestions before closing the modal. Keyboard focus stays within visible controls, returns to the opening control on close, and Escape dismisses the dialog; dismissed requests cannot reopen it.
- Admin `/admin/commissions`: filtered Pending/Paid/Net total cards, therapist/status/inclusive earned-date filters, and a fixed 15-record table beside selected payout details. Individual rows display stored basis, rate, and commission; signed adjustments retain source links. Selection uses GET `selected` within the current page and resets on filtering or pagination. The native Mark as paid disclosure records an external payout date and optional note through the existing PATCH route; errors preserve values and reopen the form. Validated list context preserves filters and clamps an empty last page after payout. Standalone details reuse the panel. `/admin/commissions/export` downloads all filtered records in table order as CSV with payout metadata and formula-safe text. Other roles retain their existing commission permissions.
- Therapist `/staff/commissions`: read-only personal pending, paid, and net totals plus personal history.

## Public Screens

### Landing Page

Access: guest and authenticated users.

Purpose:

- Introduce Casa Paraiso - Body and Wellness Spa.
- Show the admin-managed company history and role-based organizational chart when either has been published.
- Show the four massage packages, add-on price list, business hours, and call to action.
- Use the marketing line: "Reserve your spot. You deserve this."

Primary actions:

- Guests use the `Log in` action in the desktop header and mobile public menu. When active services are available, `Book an appointment` actions in the treatment catalog and visit panel open the login page; otherwise, those booking actions direct visitors to `#visit` to contact Casa Paraiso for current availability.
- Authenticated users open their role workspace.
- Book an available appointment after authentication.

Responsive behavior:

- Desktop exposes About when company-profile content is published, plus Treatments, How it works, and Visit & contact in the fixed header; small screens use an accessible compact public menu with the same available links and primary action.
- The About section combines a plain-text company narrative with an Owner → Administrator → Team chart. Blank content is not exposed publicly.
- The booking-flow copy precedes its image on small screens, while visit hours and contact details precede optional add-ons.
- When no services are active, the Treatments section continues to show the four configured signature packages as display-only reference cards, with a contact-for-availability notice and `#visit` action. Active database services completely replace these fallback cards when one or more are available.

Main data:

- Active services.
- Add-ons as static customer-facing content only.
- Business hours: Open every day, 1:00 PM to 12:00 MN.
- Basic business information.
- Admin-managed company history and organizational role labels, summaries, and ordering.

### Service Listing

Access: guest and authenticated users.

Purpose:

- Let visitors view available spa services before logging in.

Primary actions:

- View service details.
- Register or login to book an appointment.

Main data:

- Active service name, description, duration, and price.
- Initial active service names should be GAIA TOUCH, TETHYS FLOW, HESTIA WARMTH, and AURORA BREEZE.

## Auth Screens

### Login

Access: guest users.

Purpose:

- Authenticate with a verified email and password or Google OAuth. Unknown verified Google accounts and public registrations become customers; privileged emails must be pre-authorized.
- Keep the login page focused on password and Google sign-in without promoting account creation; public registration remains available directly at `/register`.
- On desktop, keep the overall login shell fixed to the viewport and allow only the form pane to scroll when short heights, zoom, messages, or expanded guidance require it. Mobile retains normal document scrolling.
- Place the Guests and Team guidance inside one collapsed `Sign-in instructions` disclosure.

Post-login redirect:

- Admin users go to `/admin/dashboard`.
- Staff users go to `/staff/dashboard`.
- Customer users go to `/customer/appointments`.

### Register

Access: guest users.

Purpose:

- Allow customers to register with name, email, optional phone, and password, then require email verification before workspace access. Google remains available as an alternative from the login screen.

Rules:

- Public registration creates customer accounts only.
- Admin and staff accounts should be created by admin users.

Post-registration redirect:

- Customer users go to `/customer/appointments`.

## Admin Screens

Admin navigation uses sidebar modules.

### Admin Dashboard

Route: `/admin/dashboard`

Purpose:

- Give admin a management summary.

Main data:

- Today appointments.
- Confirmed appointments waiting in the service queue.
- Recent transactions.
- Revenue summary.
- Feedback sentiment summary.
- Promotion suggestions needing review.

Primary actions:

- Run the service queue.
- Add appointment.
- Add transaction.
- View reports.

### Appointments

Route group: `/admin/appointments`

Purpose:

- Manage confirmed customer bookings and historical appointment outcomes.

Screens:

- Manila-local daily status cards, followed by today's plus overdue confirmed visits in a full-width, 15-record table.
- Monday–Sunday booking schedule with therapist selection, expandable service/status filters, week navigation, and existing Availability mode.
- Add appointment opens the existing creation panel; availability retains its therapist/time-cell workflows.
- Appointment detail.
- Create appointment.
- Service queue plus appointment detail, reschedule, and cancellation controls.
- Mark completed or no-show.
- Finish opens the atomic completion/payment form; Finish and No-show remain disabled until the visit starts.
- View all appointments opens `/admin/appointments/records`: customer/number search, status/therapist/scheduled-date filters, and fixed 15-record pagination.

Responsive behavior:

- The seven-day selector is a labeled, horizontally scrollable tab list with Left/Right and Home/End keyboard movement.
- Desktop Bookings shows seven day columns and separate lanes for simultaneous appointments, from 1 PM through midnight. Availability shows the selected-day therapist timeline. Mobile shows the selected day's agenda and the Add appointment on this day action.

Main data:

- Appointment number, customer, service, staff, appointment time, status, notes.

Appointment detail (`/admin/appointments/{appointment}`):

- Customer identity, booking date/time in Asia/Manila, assigned/preferred therapist, base and scheduled durations, treatment and paid add-on rows, and separate complimentary RFM vouchers.
- Customer/internal notes and newest-first creation/status activity with actor names.
- Visit status with Finish service, No-show, and Cancel controls; Finish and No-show are disabled before the visit starts, and terminal visits have no transition controls.
- Expected booking total, latest transaction status/amount, links to all transactions, and feedback availability. Partial payment status does not imply a tracked paid amount or balance.
- Desktop uses booking and visit/payment columns; below 1280px the cards stack. Shared navigation stays unchanged.

Primary actions:

- Finish a service and record its transaction atomically.
- Mark a confirmed visit no-show.
- Click an open therapist time to create a confirmed internal appointment.
- On mobile, use Add appointment on this day and select an available therapist in the same modal.
- Switch to Availability mode to add recurring shifts or date exceptions.
- Reschedule.
- Cancel.
- Complete and record transaction.

### Customers

Route group: `/admin/customers`

Purpose:

- Manage customer records and behavior history.

Screens:

- Customer list.
- Customer detail.
- Customer appointment history.
- Customer transaction history.
- Customer feedback history.
- Customer promotion suggestions.

Main data:

- Profile, contact details, appointments, transactions, feedback, RFM-related activity.

Primary actions:

- View customer history.
- Update customer notes.
- Review promotion suggestions.

### Weekly Therapist Roster

Therapists → Manage schedule opens `/admin/staff-schedules`, a Sunday–Saturday team workspace with name search, optional therapist filter, 15 therapists per page, a desktop grid, and mobile day cards. Profile schedule links open that page filtered to the therapist. Start editing clones the complete effective team into a draft; clicking a shift edits it, and Remove is a separate confirmed form. Apply hours reviews replacement/clearing of selected therapist/day cells. Copy published week identifies the last earlier published source (or recurring fallback) and reviews draft replacement before saving. Live hours, draft changes, inherited source week, date exceptions, and effective hours are visible. Saving a draft shows affected bookings; Review and publish checks the entire team and repeating period, blocking any uncovered future/in-progress confirmed visit. Full-page forms preserve inputs/filters after errors, with optional read-only native-dialog loading. Past weeks and elapsed days are read-only; published schedules continue repeating until another explicit publication.

### Staff

Route group: `/admin/staff`

Purpose:

- Manage staff profiles, service eligibility, and schedules.

Screens:

- Therapists workspace with a 15-record list beside a selected profile and Profile, Services, Schedule, and Availability sections; service catalog management has its own Services workspace.
- Staff detail.
- Create/edit staff account.
- Weekly schedule editor.
- Schedule exceptions.
- Staff-service assignments.

Main data:

- Staff user, profile, bookable status, assigned services, weekly availability, exceptions.
- Staff results use the standard `page` query key. Optional `selected` identifies a therapist on the current result page; `tab` selects the detail section. Filter, sort, and page changes reset selection. Search includes assigned service names, and weekly summaries show effective Sunday–Saturday working hours in Asia/Manila.

Primary actions:

- Create staff.
- Assign services.
- Edit schedule.
- Add schedule exception.

### Services

Route group: `/admin/services`

Purpose:

- Manage spa service catalog.

Screens:

- Service list.
- Create/edit service.
- Service detail.

Main data:

- Service name, description, duration, price, active status.

Primary actions:

- Add service.
- Update service.
- Activate/deactivate service.

### Transactions

Route group: `/admin/transactions`

Purpose:

- Manage manual payment and service transaction records.

Screens:

- Transaction list.
- Create transaction.
- Transaction detail.

Main data:

- Transaction number, customer, appointment, service, amount, payment status, payment method, recorded by, date.

Primary actions:

- Record payment.
- Update payment status.
- View customer transaction history.

Payments list:

- Paid total and Unpaid total summarize the filtered results across all pages. Only `paid` and `unpaid` amounts contribute to their respective cards; partial, refunded, and void amounts are excluded.
- Search transaction number, customer name/email, or service; filter payment status and optional `visit_month` (`YYYY-MM`). Visit-month boundaries use the linked appointment's scheduled start in Asia/Manila; All dates includes unlinked transactions.
- Table rows show ID, customer name/email, Manila visit date/time, peso amount, method, status, and View/Edit actions. Unlinked records show No linked visit and missing methods show Not set.
- Keep fixed 15-record pagination, sorting, full-page payment forms, and a secondary Revenue report link.
- `admin.transactions.export` (`GET /admin/transactions/export`) streams every matching payment in the selected order with ID, Customer, Email, Visit date, Amount, Payment method, and Status columns. It applies CSV formula protection. Reports retain their separate payment-date export.

### Customer Rewards

Route group: `/admin/promotions`

Purpose:

- Configure fixed customer reward groups and view issued reward activity without technical RFM controls.

Screens:

- Customer rewards workspace.
- Automatic reward settings form at `/admin/promotions/settings` (`admin.promotions.settings.edit`).
- Customer reward detail with confirmed dismissal for available vouchers.

Main data:

- Customer, plain-language group, paid-visit snapshot, complimentary add-on, expiry, and derived reward status.
- The workspace displays four filtered lifecycle counts and customer/contact, reward, issued date, expiry, status, and View columns beside a read-only settings summary. Search, lifecycle, and optional inclusive Manila issued-date ranges share the fixed 15-record history; the default is all dates.

Primary actions:

- Activate/deactivate a fixed group.
- Choose its future complimentary add-on and the global reward validity.
- View reward usage and dismiss an available reward.
- Open the dedicated settings form from Automatic reward settings or Edit. Save future configuration through the existing PATCH route and return to history; validation keeps form values and existing rewards are preserved.
- View opens the existing detail page; its confirmed Dismiss reward action appears only for an available, usable voucher.

### Feedback

Route group: `/admin/feedback`

Purpose:

- Review customer ratings, comments, and sentiment summaries.

The admin and super-admin index shows all history by default. Three sentiment cards precede search/service/sentiment filters, with optional Manila submission dates and topic inside More filters. All active filters scope the review list and every aggregate across all pages. A full service sentiment table sits beside positive-share topic finding meters at desktop widths; smaller screens stack them. Topic percentages use stored findings, including both polarities from one review, rather than counting all reviews as topic mentions.

The five-column Customer reviews table includes customer detail links, email or guest phone, treatment/service, Manila date, sentiment, and escaped 180-character comment previews. Long comments link to the existing feedback detail, which retains ratings, classification evidence, and manual review. Native GET submissions, mobile filter disclosure, no-JavaScript Apply filters, keyboard-focusable table scrolling, and fixed 15-record pagination remain available.

Screens:

- Feedback list.
- Feedback detail.
- Sentiment summary.

Main data:

- Customer, appointment, service, rating, comment, sentiment label, submitted date.

Primary actions:

- Filter feedback.
- View related appointment/customer.

### Reports

Route group: `/admin/reports`

Purpose:

- Support management decisions without direct database access.

Screens:

- A dashboard with Total revenue, Completed appointments, Unique customers, revenue bars, appointment-status and sentiment doughnuts, top-five services by revenue, and Customers by RFM group.
- Appointments, Transactions, Customers, Rewards, and Feedback record tabs. Rewards retains the internal `promotions` type.

Main data:

- `range` defaults to `this_month` in Asia/Manila; other choices are `last_month`, `last_30_days`, `all`, and `custom`. Explicit `date_from`/`date_to` override the preset and may have either bound. Dates are inclusive, using an exclusive next-day upper boundary in queries.
- Revenue and service rankings use fully paid transactions by `paid_at`. Completed appointments, distinct guest/enrolled customers with completed visits, and appointment statuses use scheduled dates with requested-date fallback. Feedback uses submission dates. Customer/reward record dates retain profile creation/reward issuance semantics.
- Calendar-month comparisons use the preceding calendar month; other bounded selections use the preceding equal-length period. Unbounded ranges omit comparisons; a zero prior value displays No prior activity.
- RFM classifies the entire customer base created by the range-end cutoff, capped at now, including retained soft-deleted profiles. Lifetime paid transactions linked to completed visits use existing preset thresholds and precedence; both payment and scheduled/requested visit dates must fall on or before the cutoff. Customers without matching history are Unassigned. Percentages use all profiles; average revenue uses selected-period paid revenue divided by each group's customer count. Reports are read-only and never issue or change rewards.
- Revenue buckets are daily below eight days, weekly through 90 days, and monthly thereafter, with zero buckets included. Charts and legends render on the server.

Primary actions:

- Select a date range or category. `category=all` shows every panel; focused categories select their matching tab. Appointments shows completion/customer metrics and status, Transactions shows revenue and service rankings, Customers shows unique customers and RFM, Rewards shows RFM, and Feedback shows sentiment. Tab changes retain All reports or switch the focused category.
- Search and status filters narrow only the selected record tab and CSV, preserving sorting and fixed 15-record pagination. Dashboard panels use the common date range. GET forms include no-JavaScript controls.
- Export all matching records from the active tab through the existing streamed, formula-safe CSV route; downloads remain excluded from Turbo. Existing `/admin/reports/insights` JSON keys and legacy period behavior stay compatible.

### Settings

Route group: `/admin/settings`

Purpose:

- Manage the limited business details and operational defaults safe for administrators to edit.

Screens:

- Business profile and public contact information.
- Default payment method used to prefill new Admin and Receptionist records.
- Read-only operating safeguards and environment security readiness.
- Protected user-management link for the Super Administrator only.

Main data:

- Business name, contact email, phone, address, default payment method, fixed operating assumptions, and configuration readiness indicators.

Primary actions:

- Update business details.
- Update the default payment method.
- Open protected user administration when signed in as the configured Super Administrator.

Rules:

- Admin and Super Administrator may update business settings; Receptionist, Therapist, Customer, and Guest are denied.
- User activation, role assignment, and provisioning remain exclusive to the protected Super Administrator.
- Hours, timezone, booking interval, commission rate, and security environment values are read-only in this workspace.

## Staff Screens

Staff navigation uses sidebar modules focused on daily operations.

### Staff Dashboard

Route: `/staff/dashboard`

Purpose:

- Give staff a daily work view.

Main data:

- Today assigned appointments.
- Upcoming confirmed appointments.
- Recently completed appointments.

Primary actions:

- Open assigned appointment.

### Staff Appointments

Route group: `/staff/appointments`

Purpose:

- Let staff view their assigned appointments.

Screens:

- Personal weekly calendar with assigned appointments.
- Appointment detail.

Rules:

- Staff can view assigned appointments.
- Staff can mark an assigned confirmed appointment no-show and finish an assigned arrived appointment; finishing records its transaction atomically. All other appointment mutations and the standalone transaction workspace remain read-only.
- Staff availability is read-only and maintained by admin.
- Staff cannot access admin-only settings.

Primary actions:

- Open assigned appointment details.
- Finish an arrived appointment and record its transaction atomically.
- Mark no-show.

### Staff Customers

Route group: `/staff/customers`

Purpose:

- Let staff view customer details needed for service delivery.

Screens:

- Customer lookup.
- Customer detail.

Main data:

- Customer contact details, appointment history, relevant notes, feedback history.

Rules:

- Staff can view operational customer information.
- Staff should not manage system-level user settings.

### Staff Transactions

Route group: `/staff/transactions`

Purpose:

- Let staff review transactions linked to assigned appointments.

Screens:

- Create transaction from appointment.
- Read-only transaction list and detail for assigned appointments.
- Transaction detail.

Primary actions:

- Record payment.
- Update payment status where allowed.

### Staff Feedback

Route group: `/staff/feedback`

Purpose:

- Let staff view feedback related to services and appointments.

Screens:

- Feedback list.
- Feedback detail.

Rules:

- Staff can view feedback but should not edit submitted customer feedback.

## Customer Screens

Customer navigation is appointment-first.

### My Appointments

Route: `/customer/appointments`

Purpose:

- Primary customer landing page after login.

Main data:

- Monthly calendar of confirmed upcoming appointments and appointment history.
- A dedicated, filterable appointment-history page with status and date-range filters and links to full visit details.
- Selected-day visit details and booking status.

Responsive behavior:

- The month grid remains a calendar rather than becoming a paginated list and scrolls inside a labeled keyboard-focusable region on narrow screens.

Primary actions:

- Book an appointment.
- View appointment details in a modal.
- Cancel a confirmed booking before its start.
- Submit feedback for completed appointment.

### My Appointment History

Route: `/customer/appointments/history`

Purpose:

- Let customers filter and review their appointment records away from the booking calendar.

### Book Appointment

Route: `/customer/appointments/create`

Purpose:

- Let customers reserve an available appointment immediately.
- Use the dedicated page as the primary booking experience rather than embedding the full calendar form in the appointment list.

Main data:

- Active services.
- Available staff or preferred staff where supported.
- Paid add-on catalog with individual prices.
- Admin Add-ons management with create/edit and activate/deactivate controls.
- Eligible unused RFM add-on vouchers.
- Preferred date/time.
- Customer notes.

Rules:

- Successful submissions start as `confirmed` and reserve therapist capacity immediately.
- The server locks eligible therapist rows and rechecks availability before saving.
- An available preferred therapist is assigned first; otherwise the least-booked eligible therapist is selected.
- A lost concurrency race returns a slot-unavailable error without creating an appointment.
- The customer may attach at most one eligible add-on voucher; its package price remains unchanged, while a 30-Minute Back Massage voucher extends the reservation by 30 minutes.
- The customer, Admin, or Receptionist may select multiple paid add-ons. Their prices are added to the payment default; only 30-Minute Back Massage extends the reservation by 30 minutes.
- A voucher is separate from paid add-ons and cannot duplicate a paid selection.
- Cancelling or marking the appointment no-show releases its voucher for later use.

Primary actions:

- Confirm booking.

### Appointment Detail

Route: `/customer/appointments/{appointment}`

Purpose:

- Let customers view status and details for one appointment.

Main data:

- Appointment number, service, appointment time, assigned staff, status, notes.

Primary actions:

- Submit feedback for completed appointment.
- Cancel a confirmed appointment before its scheduled start.

### Feedback

Route group: `/customer/feedback`

Purpose:

- Let customers submit and view their own service feedback.

Screens:

- Feedback form.
- My feedback history.

Rules:

- One feedback record per completed appointment in the MVP.
- Customers can view their own feedback only.

### Profile

Route: `/customer/profile`

Purpose:

- Let customers manage their own profile.

Main data:

- Name, email, phone, address, contact preference.

Primary actions:

- Update profile.
- Change an existing password through the auth flow.
- Reconfirm a linked Google identity to create a first password and enable conventional email/password login.

## Primary User Journeys

### Customer Appointment Booking

1. Customer registers or logs in.
2. Customer lands on My Appointments.
3. Customer opens Book Appointment.
4. Customer selects service and preferred date/time.
5. System atomically assigns an available therapist and creates a `confirmed` appointment.
6. Customer sees the reserved time and therapist in My Appointments.

### Service Completion And Payment

1. Admin opens a ready confirmed appointment from the chronological service queue.
2. Admin enters payment details and finishes the service.
3. The completed status, audit log, and linked transaction are saved atomically.
4. The transaction becomes part of customer history and RFM calculation input.

### Feedback Submission

1. Customer opens a completed appointment.
2. Customer submits rating and comment.
3. System stores sentiment label.
4. Admin sees feedback in feedback reports and dashboard summaries.

### RFM Add-on Voucher Booking

1. The system evaluates completed paid transactions against active RFM segments and rules.
2. An eligible suggestion snapshots one configured complimentary add-on.
3. During booking, the customer optionally attaches one available voucher to the appointment.
4. The voucher becomes applied when the appointment is confirmed, while the service price, payment amount, duration, and commission basis remain unchanged.
5. Cancellation or no-show releases the voucher; the snapshot remains stored for audit and reporting.

## Access Matrix

| Area | Admin | Receptionist | Therapist | Customer | Guest |
| --- | --- | --- | --- | --- | --- |
| Public landing/services | Yes | Yes | Yes | Yes | Yes |
| Role dashboard | Admin | Reception | Therapist | Appointments | No |
| Appointments management | All | Front-desk operations | Assigned | Own booking/cancellation | No |
| Services and schedules | Manage | Availability view only | Assigned view | Active services | Public services |
| Customer records | All | Contact and operational history | Operational access | Own profile | No |
| Transactions | All | Create and edit | Related read-only | No | No |
| Commissions | Manage payouts | No | Own read-only | No | No |
| Promotions, feedback insights, reports | Yes | No | Related feedback only | Own eligible booking vouchers and feedback | No |
| Settings and users | Admin/super admin | Own profile | Own profile | Own profile | No |

## MVP Coverage Check

- Appointment scheduling is covered by automated customer booking, admin appointment management, and staff assigned schedules with scoped completion and no-show actions.
- Service and staff management are covered by admin modules.
- Customer records are covered by admin customer screens and staff customer lookup.
- Manual transactions are covered by admin and receptionist transaction screens; therapists have related read-only payment records.
- RFM promotion suggestions are covered by admin configuration/reporting and customer add-on voucher selection during booking.
- Feedback and sentiment analytics are covered by customer feedback and admin feedback screens.
- Reports and exports are covered by admin reports.
- Super administrators can review grouped system-error reports at `/admin/system-errors`, inspect sanitized occurrence history, and resolve or reopen reports. Customers and staff receive safe branded error pages with a request reference instead of technical diagnostics.
