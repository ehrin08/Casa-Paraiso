# Customer Booking Safeguards (T5) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop troll self-bookings with a submission rate limit, an upcoming-booking cap, a no-show pause and an admin block, enforced inside the booking transaction.

**Architecture:** A new `CustomerBookingGuard` service decides whether a customer profile may self-book. `AppointmentWorkflow::autoBook` (called only by customer self-booking) locks the profile row inside its transaction and asks the guard before reserving capacity. The controller adds a per-user `RateLimiter` check. Admins block and unblock through two PATCH routes; the state lives in three nullable `customer_profiles` columns.

**Tech Stack:** Laravel 12, Blade, Alpine.js, MariaDB 10.11, PHPUnit feature tests.

**Spec:** [2026-11-01-booking-safeguards-design.md](../specs/2026-11-01-booking-safeguards-design.md)

## Global Constraints

- Additive migration only; never `migrate:fresh` outside the `testing` database.
- Defaults: `submissions_per_window` 3, `submission_window_minutes` 10, `max_upcoming` 2, `no_show_threshold` 2, `no_show_lookback_days` 90, in `config/casa.php` under `customer_booking_limits`.
- Staff, reception and guest booking are unchanged; staff can always book a blocked customer.
- Customers never see the admin's reason text; messages name the category only.
- Block reason is required, string, max 500.
- Server-rendered forms; normal Laravel requests for block and unblock.
- Run commands through the wrapper: `./scripts/casa-docker.ps1 compose exec -T app php artisan test --filter=<Test>`.

## Review Focus

1. Staff-created upcoming bookings for the customer must not count toward `max_upcoming` (count only `created_by = customer's user_id`). Test in Task 2.
2. An admin booking a blocked or capped customer from `admin.appointments.store` must still succeed. Test in Task 3.
3. A rejected self-booking must leave no appointment, no reserved capacity and no notification. Test in Task 3.
4. Cancelled, no-show, completed and past-start appointments must not count toward the cap. Test in Task 2.
5. The rate limit is per user, not per IP: another customer from the same IP can still book. Test in Task 3.

## File Structure

- Create `database/migrations/2026_11_01_120000_add_online_booking_block_to_customer_profiles_table.php`
- Modify `app/Models/CustomerProfile.php` (fillable, casts, relation, `isOnlineBookingBlocked()`)
- Modify `config/casa.php` (new `customer_booking_limits` section)
- Create `app/Services/CustomerBookingGuard.php`
- Modify `app/Services/AppointmentWorkflow.php` (`autoBook` locks the profile and calls the guard)
- Modify `app/Http/Controllers/Customer/AppointmentController.php` (`store` rate limit, `create` passes restriction)
- Modify `resources/views/customer/appointments/create.blade.php` and `partials/form.blade.php`
- Create `app/Http/Controllers/Admin/CustomerOnlineBookingController.php`, `app/Http/Requests/CustomerOnlineBookingBlockRequest.php`
- Modify `routes/web.php`, `resources/views/admin/customers/partials/details.blade.php`, reception customer show view
- Tests: `tests/Feature/CustomerBookingGuardTest.php`, `tests/Feature/CustomerBookingSafeguardsTest.php`, `tests/Feature/AdminCustomerOnlineBookingTest.php`

---

### Task 1: Data, config and model

**Files:**
- Create: the migration above
- Modify: `app/Models/CustomerProfile.php`, `config/casa.php`
- Test: `tests/Feature/CustomerBookingGuardTest.php` (started here)

**Interfaces:**
- Produces: columns `online_booking_blocked_at` (nullable timestamp), `online_booking_block_reason` (nullable string 500), `online_booking_blocked_by` (nullable FK `users`, `nullOnDelete`); `CustomerProfile::onlineBookingBlockedBy(): BelongsTo`; `CustomerProfile::isOnlineBookingBlocked(): bool` (true when `online_booking_blocked_at` is not null); `config('casa.customer_booking_limits.*')` with the Global Constraints defaults.

- [ ] **Step 1: Write the failing test** `test_profile_reports_online_booking_block`: a factory profile is not blocked; after `update(['online_booking_blocked_at' => now(), 'online_booking_block_reason' => 'Spam', 'online_booking_blocked_by' => $admin->id])` it is blocked, `onlineBookingBlockedBy->is($admin)` is true and `online_booking_blocked_at` is a Carbon instance. Also assert `config('casa.customer_booking_limits.max_upcoming') === 2`.
- [ ] **Step 2: Run** `--filter=CustomerBookingGuardTest`. Expected: FAIL (unknown column).
- [ ] **Step 3: Implement** the migration (with `down()` dropping the FK then the columns), model fillable, `'online_booking_blocked_at' => 'datetime'` cast, relation, helper and config section.
- [ ] **Step 4: Run** the same filter. Expected: PASS.
- [ ] **Step 5: Commit** `Add online booking block columns and booking limit config`.

### Task 2: `CustomerBookingGuard`

**Files:**
- Create: `app/Services/CustomerBookingGuard.php`
- Test: `tests/Feature/CustomerBookingGuardTest.php`

**Interfaces:**
- Consumes: Task 1 columns, helper and config.
- Produces:
  - constants `RESTRICTION_ADMIN_BLOCK = 'admin_block'`, `RESTRICTION_NO_SHOWS = 'no_shows'`, `RESTRICTION_UPCOMING_CAP = 'upcoming_cap'`;
  - `restriction(CustomerProfile $profile): ?string`, checking admin block, then no-shows, then cap, and returning the first match;
  - `noShowCount(CustomerProfile $profile): int`: `status = no_show`, `scheduled_start_at >= now()->subDays(lookback)`;
  - `upcomingSelfBookingCount(CustomerProfile $profile): int`: `status = confirmed`, `scheduled_start_at > now()`, `created_by = $profile->user_id`;
  - `message(string $restriction): string`;
  - `assertCanBook(CustomerProfile $profile): void`, throwing `ValidationException::withMessages(['booking' => $this->message($restriction)])`.

Customer copy (exact):
- admin_block and no_shows: "Online booking is paused for your account. Please call or visit the spa to book."
- upcoming_cap: "You already have :max upcoming online bookings. You can book again once one is completed or cancelled." (`:max` from config)

- [ ] **Step 1: Write failing tests:**
  - `test_allows_customer_without_restrictions`: `restriction()` is null.
  - `test_admin_block_wins`: blocked profile with 2 no-shows gives `admin_block`.
  - `test_two_recent_no_shows_pause_booking`: no-shows 10 and 80 days ago give `no_shows`.
  - `test_old_no_shows_do_not_count`: one at 10 days and one at 91 days gives null, and `noShowCount` is 1.
  - `test_cap_counts_only_upcoming_confirmed_self_bookings`: 2 future confirmed self-bookings give `upcoming_cap`. Null when instead the profile has 1 future self-booking plus one each of: a future confirmed booking with `created_by` = admin; cancelled; completed; no-show older than 90 days; confirmed with a past start.
  - `test_assert_can_book_throws_category_message`: `ValidationException` whose `errors()['booking'][0]` equals the cap copy with 2, and admin-block copy never contains the stored reason.
- [ ] **Step 2: Run** `--filter=CustomerBookingGuardTest`. Expected: FAIL (class not found).
- [ ] **Step 3: Implement** the guard.
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Commit** `Add customer booking guard`.

### Task 3: Enforce in self-booking

**Files:**
- Modify: `app/Services/AppointmentWorkflow.php` (`autoBook`, inside its `DB::transaction` closure, before capacity is reserved)
- Modify: `app/Http/Controllers/Customer/AppointmentController.php` (`store`)
- Modify: `resources/views/customer/appointments/partials/form.blade.php` (show `$errors->get('booking')` at the top of the form with `x-input-error`)
- Test: `tests/Feature/CustomerBookingSafeguardsTest.php` (copy `bookingFixture()` from `CustomerBookingNotificationTest`)

**Interfaces:**
- Consumes: `CustomerBookingGuard::assertCanBook()`.
- Inside the transaction: `CustomerProfile::query()->lockForUpdate()->findOrFail($attributes['customer_profile_id'])`, then `assertCanBook`. Inject the guard through the `AppointmentWorkflow` constructor. Confirm `withAppointmentNumberRetry` rethrows `ValidationException` without retrying.
- Rate limit at the top of `store`, before `assertBookableStart`: key `'customer-booking:'.$request->user()->id`. Use `RateLimiter::tooManyAttempts(key, submissions_per_window)` then `RateLimiter::hit(key, window_minutes * 60)`. When limited, return `back()->withInput()->withErrors(['booking' => __('Too many booking attempts. Please wait :minutes minutes and try again.', ['minutes' => ceil(availableIn / 60)])])`.

- [ ] **Step 1: Write failing tests:**
  - `test_capped_customer_cannot_book_and_nothing_is_held`: create 2 future confirmed self-bookings, then POST a third. Assert session error `booking`, appointment count unchanged and no `NewCustomerBookingNotification` sent (`Notification::fake()`).
  - `test_cancelling_frees_a_slot`: with one of the two cancelled, the POST redirects to `customer.appointments.history` and a new appointment exists.
  - `test_blocked_customer_cannot_book`: an admin-blocked profile gets error `booking`, and the response does not contain the reason text.
  - `test_fourth_submission_in_window_is_rate_limited`: 3 POSTs (invalid starts are fine, they still count), then the 4th returns error `booking` containing "Too many booking attempts". A second customer can still book immediately afterwards.
  - `test_admin_can_still_book_a_blocked_customer`: POST `admin.appointments.store` for a blocked, capped customer succeeds. Reuse the admin store payload shape from `AppointmentCrudRemediationTest`.
- [ ] **Step 2: Run** `--filter=CustomerBookingSafeguardsTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the workflow and controller changes and the form error.
- [ ] **Step 4: Run** the filter, then `--filter=CustomerBooking` and `--filter=CalendarSchedulingTest`. Expected: PASS. Existing booking tests must not trip the rate limiter; call `RateLimiter::clear` in `setUp` only if a test needs it.
- [ ] **Step 5: Commit** `Enforce booking safeguards in customer self-booking`.

### Task 4: Booking page notice

**Files:**
- Modify: `Customer\AppointmentController@create` (pass `'bookingRestriction' => $customerProfile ? $guard->restriction($customerProfile) : null` and `'bookingRestrictionMessage'`)
- Modify: `resources/views/customer/appointments/create.blade.php`: when a restriction is set, render a notice card with the guard message and a "Back to appointments" link instead of `@include('customer.appointments.partials.form')`. The same view feeds the centered booking dialog, so both are covered.
- Test: `tests/Feature/CustomerBookingSafeguardsTest.php`

- [ ] **Step 1: Write the failing test** `test_booking_page_shows_notice_instead_of_form_when_restricted`: a blocked customer GET `customer.appointments.create` sees "Online booking is paused" and does not see `name="requested_start_at"`. An unrestricted customer sees the form.
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the filter plus `--filter=CustomerBookingModalTest`. Expected: PASS.
- [ ] **Step 5: Commit** `Show online booking notice to restricted customers`.

### Task 5: Admin block and unblock, reception badge

**Files:**
- Create: `app/Http/Controllers/Admin/CustomerOnlineBookingController.php` with `block(CustomerOnlineBookingBlockRequest $request, CustomerProfile $customer): RedirectResponse` and `unblock(Request $request, CustomerProfile $customer): RedirectResponse`
- Create: `app/Http/Requests/CustomerOnlineBookingBlockRequest.php` with rules `reason: required|string|max:500`
- Modify: `routes/web.php` in the `role:super_admin,admin` group, before `Route::resource('customers', …)`:
  - `PATCH customers/{customer}/online-booking/block` named `customers.online-booking.block`
  - `PATCH customers/{customer}/online-booking/unblock` named `customers.online-booking.unblock`
- Modify: `resources/views/admin/customers/partials/details.blade.php`. Add an "Online booking" card in the side column next to Care notes:
  - State: Allowed; Blocked by admin (reason, `onlineBookingBlockedBy?->name ?? 'Former staff member'`, Manila date); or Paused by no-shows (`noShowCount` in the last 90 days).
  - Block opens an `x-modal` with a required reason textarea, following the notes modal pattern with its `_modal` hidden field.
  - Unblock is a confirmed PATCH form.
  - Pass the guard result from `Admin\CustomerController@show`.
- Modify: reception customer show view. Add a read-only "Online booking paused" badge when `restriction()` is `admin_block` or `no_shows`.
- Test: `tests/Feature/AdminCustomerOnlineBookingTest.php`

**Interfaces:**
- `block` sets the three columns (`blocked_by = auth id`) and redirects to `admin.customers.show` with `status` `customer-online-booking-blocked`. `unblock` nulls them and uses `customer-online-booking-unblocked`.

- [ ] **Step 1: Write failing tests:**
  - `test_admin_blocks_with_reason`: the columns are set and the redirect is correct.
  - `test_reason_is_required`: session error `reason`, nothing changes.
  - `test_super_admin_can_unblock`: all three columns are null.
  - `test_receptionist_and_customer_cannot_block`: 403.
  - `test_detail_page_shows_state`: shows "Blocked by admin" with the reason and the admin name. A profile with 2 recent no-shows shows "Paused by no-shows".
  - `test_modal_detail_shows_card`: `?modal=1` with `X-Casa-Panel: 1` contains "Online booking".
  - `test_reception_sees_badge`.
- [ ] **Step 2: Run** `--filter=AdminCustomerOnlineBookingTest`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the filter plus `--filter=AdminCustomerModalTest` and `--filter=RoleWorkspaceTest`. Expected: PASS.
- [ ] **Step 5: Commit** `Let admins block and unblock online booking`.

### Task 6: Docs and full gate

**Files:** `docs/PLANNED_TASKS.md` (tick Task 5), `docs/PROJECT_MEMORY.md`, `docs/DATABASE_DESIGN.md`, `docs/SCREEN_FLOW.md`, the design doc status, a session note, and `docs/notes/SESSIONS.md`.

- [ ] **Step 1:** Run the full Laravel suite: `./scripts/casa-docker.ps1 compose exec -T app php artisan test`. Expected: all pass.
- [ ] **Step 2:** Run the JavaScript tests and the asset build: `./scripts/casa-docker.ps1 compose exec -T app node --test 'tests/js/*.test.mjs'` and `./scripts/casa-docker.ps1 compose exec -T app npm run build`. Expected: pass.
- [ ] **Step 3:** Run `./scripts/casa-docker.ps1 start`. Expected: healthy services.
- [ ] **Step 4:** Browser-check as a local customer: the cap notice and the blocked notice in the booking dialog. As an admin: block and unblock from the full page and the modal.
- [ ] **Step 5:** Update the docs, write the session note, run `git diff --check`, and commit `Record booking safeguards (T5)`.
