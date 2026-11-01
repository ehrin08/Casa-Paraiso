# Customer Booking Safeguards (T5) Design

Date: 2026-11-01. Status: approved and implemented 2026-11-01. Source: [Planned Tasks](../../PLANNED_TASKS.md) Task 5.

## Intent

Customer self-bookings are confirmed immediately by `AppointmentWorkflow::autoBook` and reserve therapist capacity. Nothing limits how many a customer makes or how fast, so one careless or malicious account can hold slots that real customers then cannot book. No incident has happened yet; this is preventive.

## Goals

1. **Submission rate limit:** at most 3 booking submissions per customer account in 10 minutes.
2. **Active booking cap:** at most 2 upcoming confirmed self-bookings per customer at a time.
3. **No-show block:** 2 or more no-shows in the last 90 days stop online self-booking.
4. **Admin block:** admins and super admins can stop one customer's online self-booking, with a reason, without deactivating the account.

Customers see a clear message when a limit stops them, and admins see why a customer cannot book online.

## Non-goals

- Late-cancellation tracking (needs new data; revisit later).
- Staff approval of self-bookings (would change instant confirmation for everyone).
- Changes to staff, reception or guest booking. Staff can still book any customer at the desk, including blocked ones.
- Phone verification. Email verification already gates the customer routes (`verified` middleware).

## Anti-goals

- A legitimate regular who books one visit at a time must never notice these rules.
- A limit must never leave a half-made booking or a held slot behind.

## Constraints

- Additive migration only; existing customer data is untouched.
- Limits are enforced inside the existing booking transaction, because confirmed appointments reserve therapist capacity.
- Server-rendered forms; block and unblock are normal Laravel requests.
- Thresholds live in `config/casa.php` so the owner can tune them.

## Approaches

- **A. A booking-guard service inside the booking transaction (chosen).** One service checks the cap, no-show rule and admin block inside `autoBook`'s transaction after locking the customer profile row. The rate limit runs in the controller through Laravel's `RateLimiter`. Chosen because it cannot be raced by simultaneous submissions and matches how capacity is protected.
- **B. Checks in `CustomerAppointmentStoreRequest`.** Simplest, but two concurrent submissions could both pass the cap. Rejected.
- **C. Like A, with a `booking_restrictions` history table.** Full audit trail, but a new table and screens for an action expected to be rare. Rejected for now.

## Design

### Configuration

New `config/casa.php` section `customer_booking_limits`:

| Key | Default | Meaning |
|-----|---------|---------|
| `submissions_per_window` | 3 | Booking submissions allowed per account per window |
| `submission_window_minutes` | 10 | Rate-limit window |
| `max_upcoming` | 2 | Upcoming confirmed self-bookings allowed at once |
| `no_show_threshold` | 2 | No-shows that trigger the block |
| `no_show_lookback_days` | 90 | Window for counting no-shows |

### Data

Additive migration on `customer_profiles`, all nullable:

- `online_booking_blocked_at` (timestamp)
- `online_booking_block_reason` (string, max 500)
- `online_booking_blocked_by` (foreign key to `users`, null on delete)

### Enforcement

- **Rate limit:** `Customer\AppointmentController@store` calls `RateLimiter::attempt` keyed by user ID before validating the slot. Every submission counts, successful or not. On exceeding it, the customer is returned to the form with a validation error stating how many minutes to wait.
- **Guard:** new `App\Services\CustomerBookingGuard`. `autoBook` is only called by customer self-booking, so inside its transaction it locks the customer profile row (`lockForUpdate`) and calls the guard before reserving capacity. The guard checks, in order:
  1. admin block;
  2. no-show count (`status = no_show`, scheduled start within the lookback window);
  3. upcoming confirmed appointments for the profile with a start in the future, against `max_upcoming`.
  A failure throws a `ValidationException` on a `booking` key, so the transaction rolls back and nothing is held.
- The guard also exposes a read-only `restriction(CustomerProfile)` that returns the active reason or null, for display.

### Customer experience

- Booking page and booking dialog: when a restriction applies before submitting, show a notice instead of the form ("Online booking is paused for your account. Please call or visit the spa to book."). The cap message says they already have the maximum upcoming bookings and can book again once one is completed or cancelled.
- Messages never reveal the admin's private reason text; they name the category only.

### Admin experience

- Admin and super-admin customer detail (full page and modal) gets an "Online booking" card:
  - shows Allowed, Blocked by admin (reason, who, when), or Paused by no-shows (count in window);
  - Block opens a confirmation with a required reason; Unblock clears the three columns.
- New PATCH routes under the existing admin and super-admin customer routes, guarded like the current customer note edits.
- The no-show pause cannot be cleared by admins in this version; it lifts once old no-shows leave the lookback window. Admins can still book the customer from the desk.

### Tests

Feature tests for each rule, including: cap reached, cap freed by cancellation, no-show block within and outside the window, admin block and unblock with permission checks, rate limit message, and that a blocked booking leaves no appointment or capacity behind. Then the full gate from `AGENTS.md`.

## Builder's calls

- Exact message wording, card layout and route names follow existing customer-detail patterns.
- Whether receptionists can see the restriction (proposed: read-only badge on reception customer views, if cheap).
