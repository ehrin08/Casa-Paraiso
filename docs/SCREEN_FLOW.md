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
- Customer small-screen navigation uses a persistent three-item bottom bar for Appointments, Feedback, and Profile.
- Admin and staff pages should use the persistent sidebar with module navigation, page title, search/filter area where needed, and clear primary action buttons.
- Customer pages should stay simpler and appointment-first, with sidebar navigation focused on appointments, feedback, and profile.
- Complex management CRUD uses dedicated create/edit pages. Keep modals for contextual calendar booking, the atomic Admin Finish service flow, short feedback or note entry, and destructive/action confirmation.
- Use compact stat strips for detail and calendar context; reserve larger metric cards for dashboards and analytics-heavy summaries.
- Paginate authenticated record lists at the fixed server-controlled size of 15. Preserve filters and sorting across pages, show the result range on every non-empty page, and do not expose a page-size selector.
- On screens narrower than 1024px, list filters collapse behind a labeled toggle that exposes its expanded state and active-filter count. Show Clear filters only when a filter is active.
- Desktop pagination uses numbered controls; mobile pagination uses Previous, Page X of Y, and Next. Empty result sets show their empty state without pagination.
- Keep operational tables inside labeled, keyboard-focusable horizontal-scroll regions. Calendar week strips and customer month grids may scroll horizontally when seven full-size date targets do not fit.
- Appointment calendars, dashboard previews, service queues, detail-history previews, and form selector collections remain unpaginated.
- All role dashboards should show only actions the current role is allowed to perform.
- Avoid SPA-only navigation patterns; pages should work as standard Laravel routes.

## Receptionist Screens

- Dashboard: today’s bookings, upcoming visits, customer count, and payment activity.
- Appointments: weekly bookings and read-only therapist availability; confirmed create/edit, cancellation, no-show, and completion/payment actions.
- Customers: search, contact details, appointment/payment history, and permitted contact/operational-note updates.
- Payments: create, edit, and review manual transactions.
- Profile: shared account settings. Receptionists cannot access schedules, services, insights, reports, exports, settings, user administration, or commissions.

## Embedded Booking And Commission Screens

- Customer booking opens as a modal from My Appointments and the selected-day panel; `/customer/appointments/create` remains the fallback.
- Admin `/admin/commissions`: filtered pending/paid/adjustment records, totals, source links, and external payout recording.
- Therapist `/staff/commissions`: read-only personal pending, paid, and net totals plus personal history.

## Public Screens

### Landing Page

Access: guest and authenticated users.

Purpose:

- Introduce Casa Paraiso - Body and Wellness Spa.
- Show the four massage packages, add-on price list, business hours, and call to action.
- Use the marketing line: "Reserve your spot. You deserve this."

Primary actions:

- Guests use the single `Reserve` navigation action to open the login page.
- Authenticated users open their role workspace.
- Book an available appointment after authentication.

Main data:

- Active services.
- Add-ons as static customer-facing content only.
- Business hours: Open every day, 1:00 PM to 12:00 MN.
- Basic business information.

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

- Calendar-only weekly schedule with Bookings and Availability modes.
- In-page confirmed appointment modal opened from Add appointment or an available therapist/time cell.
- Appointment detail.
- Create appointment.
- Service queue plus appointment detail, reschedule, and cancellation controls.
- Mark completed or no-show.

Responsive behavior:

- The seven-day selector is a labeled, horizontally scrollable tab list with Left/Right and Home/End keyboard movement.
- Desktop shows the therapist timeline. Mobile shows the selected day's agenda and the Add appointment on this day action.

Main data:

- Appointment number, customer, service, staff, appointment time, status, notes.

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

Admin Availability includes a dated Sunday-to-Saturday team roster. The Admin copies the prior published week into a draft, adjusts therapist shift ranges, then publishes the whole roster. Drafts are private to Admin; published hours drive customer booking, receptionist availability, and therapist calendar coverage. A publish that would exclude a confirmed appointment is rejected and identifies the affected booking.

### Staff

Route group: `/admin/staff`

Purpose:

- Manage staff profiles, service eligibility, and schedules.

Screens:

- Team & Services workspace with independently paginated staff and embedded service-catalog lists.
- Staff detail.
- Create/edit staff account.
- Weekly schedule editor.
- Schedule exceptions.
- Staff-service assignments.

Main data:

- Staff user, profile, bookable status, assigned services, weekly availability, exceptions.
- Staff results use the standard `page` query key. The embedded service catalog uses `services_page` and returns to `#service-catalog` after paging.

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

### Customer Rewards

Route group: `/admin/promotions`

Purpose:

- Configure fixed customer reward groups and view issued reward activity without technical RFM controls.

Screens:

- Customer rewards workspace.
- Read-only customer reward detail.

Main data:

- Customer, plain-language group, paid-visit snapshot, complimentary add-on, expiry, and derived reward status.

Primary actions:

- Activate/deactivate a fixed group.
- Choose its future complimentary add-on and the global reward validity.
- View reward usage and dismiss an available reward.

### Feedback

Route group: `/admin/feedback`

Purpose:

- Review customer ratings, comments, and sentiment summaries.

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

- Appointment report.
- Transaction/revenue report.
- Customer activity report.
- Promotion suggestion report.
- Feedback sentiment report.

Main data:

- Filtered summaries and tabular records by date range, status, service, staff, customer, and sentiment.

Primary actions:

- Filter report.
- Export CSV.

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
