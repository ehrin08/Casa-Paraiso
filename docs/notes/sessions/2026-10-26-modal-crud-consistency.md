---
type: session-log
date: 2026-10-26
---

# Modal CRUD and consistent dialogs

## Task

Move CRUD actions into modals where it makes sense, on the `redesign` branch, and make dialogs behave and look the same across the project. Choices made with the user:

- Convert the simple and medium create/edit forms.
- Add confirmations to every action that changed state with no confirmation step.
- Use one shared look and one form pattern, rather than rewriting the native-dialog editors.
- Keep full-page create/edit URLs as fallbacks.

Deletes and status changes were already confirmation modals. No dedicated delete or confirm pages existed.

## What Changed

- **Form panel.** Create/edit for add-ons, services, therapists, schedule exceptions, weekly hours (from the calendar chooser) and admin/reception payments now open in the existing workspace panel loader. It shows a centered `is-form` dialog (48rem/90dvh).
  - `resources/js/panel-form.js` holds URL detection, the hidden `_panel` return field, safe-path checks and one-time restore.
  - Form panels always fetch fresh, and `data-panel-dismiss` Cancel links close the panel.
- **Validation recovery.** The new `RestorePanelForm` web middleware reflashes errors and input on the next non-panel GET for a safe `_panel` path. `x-modal-host` renders `data-panel-restore`, and the loader reopens the same panel with the errors and entered values.
- **Confirmations.** `x-confirm-action` and `x-confirm-submit` now use the new `x-modal-shell` (glass bar, body, sticky footer). They are centered and labelled, and accept `tone="danger"` and `dismiss-label`. New confirmations:
  - system-issue Resolve and Reopen
  - reception Cancel and No-show, with an optional reason
  - roster shift removal, replacing the inline `<details>`
- **Contextual modals.** Reception Finish service is a modal that reuses the admin completion partial. Account deletion is a modal; both reopen on validation errors through `_modal`.
- **Consistency.** Existing destructive confirmations use the danger tone and named keep labels such as "Keep booking" and "Keep appointment". The Settings chart and roster native-dialog backdrops match the shared modal.
- **Fixes and clean-up.**
  - The add-on create and edit pages printed `@include('admin.addons.form')` as text instead of the form; they are reformatted. The add-on form is readable markup again, and saves now return to the list.
  - The unreachable `@if (false)` branches in the therapist appointment page are removed.
- **Docs.** The policy is updated in [BRAND_UI_GUIDE.md](../../BRAND_UI_GUIDE.md), [SCREEN_FLOW.md](../../SCREEN_FLOW.md) and [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md).

## Verification

- **New tests.**
  - `PanelFormRecoveryTest` covers add-on and service round trips, the restore marker being consumed once, and rejection of unsafe `_panel` values (protocol-relative, absolute, outside the prefixes, script characters, backslash).
  - `ModalConfirmationTest` covers reception Finish recovery, the reception outcome confirmations, and account deletion recovery.
  - `tests/js/panel-form.test.mjs` covers the panel-form helpers, and `appointment-preview-panel.test.mjs` gained a form-panel case.
- **Updated tests.** `ComplexCrudPageNavigationTest` now asserts form-panel links and full-page fallbacks. `SystemErrorReportingTest` and `WeeklyRosterTest` assert the new confirmations. `ModalInfrastructureTest` follows the new `openPanel` signature.
- **Suites.**
  - Targeted feature set: 209 passed.
  - Full Laravel suite: 498 passed (5,561 assertions).
  - JavaScript: 92 of 92 passed.
  - `npm run build` succeeded; the chunk-size warning was already there.
  - Docker `start`/`status` through the wrapper is healthy, and `/login` returns 200.
  - `git diff --check` is clean.
- **Browser checks.** These ran on an isolated `compose.testing.yaml` project (`casa-paraiso-modal-review`, port 18002) with a private, seeded MariaDB, signed in as the seeded test admin. The project and its volumes were removed afterwards.
  - **Add add-on panel:** centered, with focus in Name. A server-side error (duration 999) redirected and reopened the panel with the message and the entered values. A valid save returned to the list.
  - **Deactivate confirmation:** centered with a red confirm button and initial focus on Cancel. Escape returned focus to the row's trigger.
  - **Service edit at 375px:** multipart form, no page overflow, internal scrolling. Cancel closed the panel and refocused Edit.
  - **Record payment panel:** focus on the first select.
  - **Direct `/admin/addons/create`:** renders the full page.
- **Not checked in a browser:** the reception, roster, system-issue and account-deletion dialogs. Their markup and recovery are covered by feature tests.

## Decisions

- Reused the panel loader instead of embedding per-row form modals, which keeps one form source for both the modal and the full page.
- Booking-wizard panels are not restored after server validation. The wizard reopens on its own step logic, and restoring it was out of scope.
- Account deletion and roster shift removal now need JavaScript, like the other confirmation modals.

## Follow-ups

- [ ] Browser-check the reception, therapist-roster, system-issue and customer account-deletion dialogs with signed-in test accounts.
- [ ] Decide whether booking-wizard panels should also reopen after server validation.
- [ ] Consider moving the remaining hand-built dialogs (feedback, customer notes, completion) onto `x-modal-shell`.
