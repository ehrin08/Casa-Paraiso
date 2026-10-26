# Casa Paraiso Frontend Consistency Audit

**Reviewed:** October 18, 2026, Asia/Manila

**Baseline:** current working tree, including existing uncommitted work; HEAD `3919581fb63d67d03ebcfc2bad17c3f7dc029005`

**Result:** the brand is broadly aligned; shared UI rules and some customer entry points need correction. Application code was left unchanged.

## Evaluation result

The cream, palm green, cacao, original logo, Manrope interface text, Cormorant display headings, and real spa photography give the application a recognizable Casa Paraiso identity. The public homepage and newer workspaces communicate the intended warm, calm spa experience. A wholesale redesign would discard useful work.

The main consistency problem is the gap between newer, scoped workspaces and older screens using the generic components. Several older titles collapse to body-text size. Dialogs share the palette but differ in naming, action language, and accessibility support. Booking has a good persistent header/footer, yet its smaller controls and calendar cells miss the project's target-size rules.

| Area | Assessment | Practical implication |
| --- | --- | --- |
| Brand identity and imagery | Aligned | Preserve the existing palette, logo, photos, and editorial marketing treatment. |
| Page hierarchy and typography | Partial | Fix the shared heading override and the remaining small functional text. |
| Forms and controls | Partial | Standardize error associations, resting field boundaries, and interaction targets. |
| Dialogs and contextual workflows | Partial | Keep the current modal policy; clarify names and safe/destructive actions. |
| Responsive structure | Generally consistent | No page-wide overflow was measured in the captured states; some contained controls are still too narrow. |
| Customer entry and terminology | Gaps | Email registration is undiscoverable from the normal entry path; some copy retains the old request model. |

**Heuristic usability score: 6/10.** Start at 10; deduct two points for the missing new-customer email-registration path, one for inconsistent accessible naming, and one for action/visual consistency. Related findings are grouped rather than penalized repeatedly. This is an expert review of the recorded states, not a user-study result.

The quick diagnostic passed site identity, visible primary actions for existing users, search in normal JavaScript use, loading feedback, understandable visible validation, available Back/Close actions, and operation without hover. Navigation for new email customers, complete control labeling, and predictable action language require work. Reaching 10/10 requires resolving the findings below and repeating their acceptance checks.

## Coverage and evidence

- Inventoried **173 Blade templates**, including **85 page-bearing/layout candidates**. These counts are source inventory counts, not 85 distinct active routes.
- Captured **206 views across 103 page/interaction states**: public/authentication, customer, receptionist, therapist, administrator, and super-administrator.
- Checked the public homepage, privacy and guest forms; every role's main workspaces; representative create/edit/detail forms; booking and history; feedback; payments; commissions; attendance displays; rewards; reports; settings; notifications; user accounts; and system issues.
- Core desktop checks use 1440×900 CSS pixels. Mobile checks use 390×844 or 390×845; the one-pixel difference comes from browser zoom rounding. Additional checks cover 320px, tablet widths, 1023/1024px navigation boundaries, and 1279/1280px panel boundaries. Earlier 355px/1309px observations are retained as extra evidence; core reception, therapist, and customer pages were repeated at the planned widths.
- Collected DOM measurements and screenshots; visually reviewed representative marketing, operational, customer, form, and dialog examples. A captured state does not imply every element received a manual visual inspection.
- Exercised ordinary browser interactions against synthetic data in the separate `casa-paraiso-frontend-audit-20261018` Compose project, MariaDB database `testing`, at `localhost:18004`. The account-bearing application database was not used for submissions.

See the [complete coverage matrix](../output/frontend-audit/COVERAGE.md), [measured observations and DOM snapshots](../output/frontend-audit/evidence-index.json), [source excerpts with line numbers](../output/frontend-audit/source-evidence.txt), and [source fingerprints](../output/frontend-audit/source-fingerprints.json).

Severity: **4** blocks a core task completely; **3** causes significant task failure; **2** causes confusion, delay, or an accessibility/consistency gap; **1** is a minor presentation/copy issue; **0** is an intentional difference. No severity-4 finding was established.

## Prioritized findings

### F01 — Email registration has no visible entry point · Severity 3

**Observed:** the homepage sends guests to Log in, and the login screen offers email/password and Google. Neither links to the existing customer registration page. A new customer without a Google account cannot discover how to create the email account required for booking through that path.

**Reproduce:** open `/` as a guest → Book an appointment → inspect `/login`. Then open `/register` directly to see the functioning customer form.

**Evidence:** [guest login](../output/frontend-audit/screenshots/auth-login-desktop.jpg), [registration form](../output/frontend-audit/screenshots/auth-register-mobile.jpg); [login view](../resources/views/auth/login.blade.php), [homepage](../resources/views/welcome.blade.php), and [auth routes](../routes/auth.php), lines 15–17. The source scan found zero registration-route links in the login and homepage views.

**Correction / acceptance:** add a clear “Create a customer account” link on login, beside the existing sign-in choices. Preserve the customer-only registration and team access rules. A guest must reach registration from the homepage booking path using visible links on desktop, mobile, and keyboard navigation.

### F02 — A shared CSS override flattens older page titles · Severity 2

**Observed:** Add service, payment forms, several detail pages, receptionist/therapist pages, and customer history render their `h1` at **16px**. Newer screens retain stronger titles: the admin dashboard is 30px and Services is 43.2px at the desktop reference width. The small title can be visually weaker than the section heading below it.

**Cause:** [app.css](../resources/css/app.css), lines 906–909, sets `font-size: inherit` on heading descendants containing `text-3xl` or `text-4xl`, overriding the intended heading scale. The shared `page-heading` wrapper is already applied by the layout; its absence inside individual views is not a defect.

**Evidence:** [Services list](../output/frontend-audit/screenshots/admin-services-desktop.jpg) versus [Add service](../output/frontend-audit/screenshots/admin-service-create-desktop.jpg), [reception dashboard](../output/frontend-audit/screenshots/reception-dashboard-mobile.jpg), and [customer history](../output/frontend-audit/screenshots/customer-history-desktop.jpg).

**Correction / acceptance:** remove the inherited-size reset and let the shared heading scale apply. Preserve approved scoped marketing/customer and workspace overrides. Generic operational titles must return to the guide's 24–30px range, remain visibly above section headings, and wrap without overflow across all five roles.

### F03 — Dialog accessible names expose implementation identifiers · Severity 2

**Observed:** service and customer cancellation confirmations are named “Confirm action” followed by a UUID. Feedback is named “Customer feedback submit” while the visible title is “Service feedback.” Fetched booking/customer panels and native chart/schedule dialogs have more meaningful names.

**Cause:** [modal component](../resources/views/components/modal.blade.php) derives a fallback `aria-label` from the internal name; [confirm-action](../resources/views/components/confirm-action.blade.php), lines 22–29, does not connect its heading to the existing `labelledBy` prop.

**Evidence:** accessible dialog names in [customer cancellation observations](../output/frontend-audit/evidence-index.json) and [confirmation screenshot](../output/frontend-audit/screenshots/modal-customer-cancel-desktop.jpg).

**Correction / acceptance:** give each visible modal heading a stable ID and pass that ID through the existing `labelledBy` interface. Retain UUIDs only as internal identities. The accessible dialog name must match its visible title; confirm prompts should also associate their brief explanatory message. Verify confirmations, feedback, notes, history details, and completion summaries.

### F04 — Confirmation wording and destructive emphasis are inconsistent · Severity 2

**Observed:** cancelling a booking presents **Cancel** and **Cancel booking** together. One means keep the reservation; the other releases it. Deactivate starts with a red outlined trigger but becomes a green primary confirmation. The generic component hard-codes the safe action's label and the confirmation's primary styling.

**Evidence:** [customer cancellation](../output/frontend-audit/screenshots/modal-customer-cancel-desktop.jpg), [service confirmation](../output/frontend-audit/screenshots/modal-service-confirm-verified-desktop.jpg), and [confirm-action](../resources/views/components/confirm-action.blade.php), lines 30–38.

**Correction / acceptance:** add optional safe-action text and confirmation tone to `confirm-action`, preserving current defaults for compatible callers. Use “Keep booking” / “Cancel booking” for cancellation, and the existing danger style for destructive confirmations. Activation and ordinary saves keep palm-green emphasis. Users must be able to distinguish both outcomes without reading color alone.

### F05 — Form error support varies between workflows · Severity 2

**Observed:** submitting a feedback comment over 5000 characters correctly restores the modal, entered text, and selected appointment with a plain error message. However, the Comment field has neither `aria-invalid` nor `aria-describedby` after that failure. The shared error list has an alert role but no stable field association. The standalone customer-note textarea also lacks an associated label, unlike the inline note editor.

**Evidence:** [feedback validation](../output/frontend-audit/screenshots/modal-feedback-validation-mobile.jpg); [feedback form](../resources/views/customer/feedback/partials/form.blade.php), lines 36–38; [input-error](../resources/views/components/input-error.blade.php); [customer notes](../resources/views/admin/customers/partials/details.blade.php), lines 71–73 and 89–91.

**Correction / acceptance:** use the components' existing attribute passthrough to supply stable error IDs, `aria-describedby`, and `aria-invalid` at field call sites. Add an explicit label/id to standalone notes and a visible invalid-field treatment. Preserve the working input recovery. A keyboard/screen-reader check must identify the field, its invalid state, and the same displayed error.

### F06 — Booking controls miss the shared 44px target rule · Severity 2

**Observed:** desktop step buttons are 32px high, the Paid add-ons disclosure is 21px high, and Cancel is 37px high. Date cells are approximately **38.5px wide at a 390px viewport** and **28.5px wide at 320px**. The seven-column grid fits, but fitting does not establish adequate tap targets.

**Evidence:** [narrow date selection](../output/frontend-audit/screenshots/modal-booking-date-narrow.jpg), [exact date-cell measurements](../output/frontend-audit/booking-target-measurements.json), [booking styles](../resources/css/appointment-form.css), lines 28–31, 63, 81–87, 108–113, and 125–140.

**Correction / acceptance:** give interactive step buttons, optional disclosures, and Cancel a 44px minimum target. Reduce modal/calendar insets and gaps at phone widths, using a full-width compact treatment at 320px so seven 44px date targets fit without horizontal scrolling. Retain the fixed title/footer. Recheck actual width and height at 320px and 390px. Small checkbox graphics with sufficiently large clickable labels are not counted as independent target failures.

### F07 — Functional text bypasses the 14px readability floor · Severity 2

**Observed:** booking weekdays are 12px and the meaningful “Open” status is 10px. Phone step labels are 12px. Guest form labels use 0.82rem and the shared guest primary button uses 0.74rem, while authenticated overrides raise comparable controls to 14px.

**Evidence:** [booking date view](../output/frontend-audit/screenshots/modal-booking-date-mobile.jpg), [login](../output/frontend-audit/screenshots/auth-login-desktop.jpg); [appointment-form.css](../resources/css/appointment-form.css), lines 82, 87, 131, 140; [app.css](../resources/css/app.css), lines 284, 360, 930, and 939–941.

**Correction / acceptance:** apply the 14px functional-text floor consistently to guest labels/actions, booking steps, calendar status/weekday text, and corresponding mobile schedule labels. Keep decorative typography exceptions separate from information needed to operate the interface. This is a Casa design-rule finding; 14px is not being asserted as a universal WCAG font-size requirement.

### F08 — Resting input boundaries are weaker than the guide requires · Severity 2

**Observed:** an unfocused profile input uses `#DCD2C2` against white, approximately **1.50:1** contrast; against Casa Paper it is **1.46:1**. Focus switches to palm green, but the resting field edge remains faint. The guide calls for 3:1 interface boundaries. Palm and muted text on Paper measured approximately 5.86:1 and 5.57:1 respectively, so a palette replacement is unnecessary.

**Evidence:** [profile](../output/frontend-audit/screenshots/profile-customer-desktop.jpg); [shared input rules](../resources/css/app.css), lines 336–355; [profile input rules](../resources/css/profile.css), lines 105–114; [contrast calculations](../output/frontend-audit/measurements-summary.json).

**Correction / acceptance:** introduce an interactive-boundary alias using existing Casa Muted for resting field edges, while retaining Casa Border for decorative card separators. Check ordinary, hover, focus, invalid, and disabled states; required identifying boundaries must meet the guide's 3:1 target without relying on focus alone.

### F09 — Mobile no-JavaScript filtering is uneven · Severity 2 · Source verified

**Observed:** the shared collapsible toolbar hides its fields below 1024px with `hidden lg:block`; only Alpine reveals them. Newer Services, Payments, and several other workspaces add their own no-JavaScript reveal CSS. Generic callers such as Add-ons lack that fallback. The Customers no-JavaScript block concerns modal recovery, not its filter panel.

**Evidence:** [list-toolbar](../resources/views/components/list-toolbar.blade.php), lines 49–79; [Add-ons](../resources/views/admin/addons/index.blade.php); compare [Services](../resources/views/admin/services/index.blade.php), lines 44–47.

**Correction / acceptance:** move the filter reveal/hide-toggle fallback into the shared collapsible toolbar, scoped to its generated panel ID. Verify filters and Apply controls remain usable below 1024px with JavaScript disabled, while normal JavaScript disclosure/count behavior remains unchanged. This scenario was inspected in source; JavaScript was not disabled in the user's browser.

### F10 — Money and status presentation changes between related screens · Severity 1

**Observed:** the service list shows `₱499`, while booking, add-ons, reception, and completion examples use `PHP 499.00`. Some status labels use “No Show,” others “No-show.” Stored values and numerical amounts remained consistent in the reviewed fixtures.

**Evidence:** [Services](../output/frontend-audit/screenshots/admin-services-desktop.jpg), [reception](../output/frontend-audit/screenshots/reception-dashboard-mobile.jpg), [completion summary](../output/frontend-audit/screenshots/modal-completion-summary-desktop.jpg), [Add-ons source](../resources/views/admin/addons/index.blade.php), line 11.

**Correction / acceptance:** use one display formatter and “No-show” label. Use `₱` throughout human-facing screens; catalog prices omit zero decimals and payment records retain two decimals. Keep form values, exports, and saved status keys unchanged. Document these two intentional precision formats.

### F11 — Some customer copy reflects implementation or retired behavior · Severity 1

**Observed:** registration promises to “request appointments” although successful self-booking reserves and confirms immediately. Confirmed visit details retain “Request notes.” The feedback modal explains classification from rating/language keywords rather than helping a customer leave useful feedback.

**Evidence:** [registration view](../resources/views/auth/register.blade.php), line 3; [customer details](../resources/views/customer/appointments/show.blade.php); [feedback form](../resources/views/customer/feedback/partials/form.blade.php), lines 44–45.

**Correction / acceptance:** use “Book appointments and track your visits,” “Visit notes,” and a short care-focused feedback explanation. Preserve established service names and accurate booking/status promises. Keep classification details in administrative evidence/help where they assist a decision.

## Modal consistency matrix

| Workflow | Appropriate surface and verified behavior | Remaining work |
| --- | --- | --- |
| Customer/admin/reception booking; operational edits | Centered shared fetched dialog; three steps; persistent header/footer; full-page fallback. Treatment/date/review and Back were exercised. | F06–F07; retain role-specific fields and automatic customer assignment. |
| Appointment queue preview | Centered read-only panel, Close/full-details actions, fresh data, Escape and trigger focus return checked. | Keep management dialogs out of the preview. |
| Customer Open and notes | Centered details panel with inline note editing; standalone profile uses a short note modal. | F03/F05; equivalent labels and error semantics across both entry points. |
| Finish and payment | Short atomic modal; synthetic completion and transaction saved together; one-time summary verified after refresh. | Apply the same naming/action contract; retain recorded-amount semantics. |
| Cancellation, deactivation, no-show, reward dismissal | Compact contextual confirmation is appropriate. Fresh in-app-browser confirmation focus and wrapping passed. | F03–F04; standardize safe-action language and destructive tone. |
| Customer feedback/history detail | Contextual dialogs remain appropriate; feedback error recovery was exercised. | F03/F05/F07; keep close/cancel reachable. |
| Settings chart | Native dialog; initial field focus; drafts retained after close-only Done/reopen. Page Save publishes. | Preserve this deliberate draft-retention exception and explicit helper text. |
| Schedule editors/reviews | Read-only copy-review GET opened a native dialog; Escape restored trigger focus. Full pages remain fallbacks. | Shift editing/bulk/publish submission scenarios remain source/test verified, per the user's instruction. |
| Profile deletion | Inline identity-confirmation form with explicit destructive language. | A separate modal is unnecessary solely for visual uniformity; deletion was not executed. |

Different engines alone are not a defect. Preserve shared Alpine modals, fetched panels, and native dialogs where appropriate, while matching accessible naming, visible exits, focus containment/return, background modality, and recovery expectations. Confirmations can remain compact; complex therapist/service/payment CRUD stays on full pages.

An initial background Chrome audit tab allowed focus to remain behind a service confirmation. The same service confirmation passed initial-focus and keyboard checks in the fresh in-app browser, as did customer cancellation. This conditional observation is retained in the diagnostic evidence and **excluded from confirmed universal defect counts**. Add a real browser regression covering shared modals; the source currently isolates panel backgrounds more explicitly than the shared modal store.

## Consistency rulebook and fix order

Use [BRAND_UI_GUIDE.md](BRAND_UI_GUIDE.md) and [SCREEN_FLOW.md](SCREEN_FLOW.md) as the baseline. Apply an explicit page-specific approved rule before a general visual default. Homepage 3–5px corners, some referenced workspaces' 8px cards, and generic operational 14–16px surfaces are documented variations, not automatic defects. On the `redesign` branch these corner variations are replaced by the shared Modern Resort tokens (20px cards, 16px panels, 12px controls, pill buttons); see [REDESIGN_MODERN_RESORT.md](REDESIGN_MODERN_RESORT.md). Customer and operational calendars intentionally have different week-start conventions.

1. **Shared foundations first:** F02 heading reset; F03 modal title associations; F04 optional safe-action label/tone; F05 field/error attributes; F06–F08 target, text, and boundary tokens; F09 toolbar fallback. Fix shared owners before adding local CSS patches.
2. **Customer completion path:** F01 visible email registration, then apply the shared confirmation/feedback corrections to booking cancellation and feedback. Recheck the homepage → login → registration path and booking/history flow at phone widths.
3. **Presentation cleanup:** F10 money/status formatting and F11 customer copy. Keep saved values, request fields, role permissions, business hours, and confirmation/reservation semantics intact.

No HTTP route, feed, schema, or business-rule change is required for these frontend corrections. Proposed shared interface additions are limited to optional confirmation safe-action text/tone; the modal title and form attribute interfaces already support the needed associations.

## Verification, exceptions, and remaining limits

**Passed supporting checks:** the existing JavaScript suite completed **78 tests, 78 passed, 0 failed**. It covers modal focus helpers, booking steps/availability, fetched preview/customer dialogs, native schedule/settings dialogs, calendars, notifications, cancellation of stale loads, and cleanup. See [test output](../output/frontend-audit/javascript-tests.txt) and [interaction outcomes](../output/frontend-audit/interaction-summary.json). These unit checks supplement the browser review; they do not establish full browser or accessibility conformance.

**Browser evidence:** booking step recovery, preview focus return, customer detail loading, feedback validation recovery, synthetic completion with one-time summary, settings draft retention, read-only native schedule review, sidebar collapse, mobile navigation, empty results, and 15-record pagination. The customer list's second page displayed “16–19 of 19” and “Page 2 of 2.” No page-wide overflow was measured in the recorded states; internal table/calendar scrolling is intentional.

**Documentation conflict:** SCREEN_FLOW describes therapist completion/no-show actions, but the current therapist detail hides mutation sections and the staff route group exposes appointment index/show only. Record this as a workflow/documentation reconciliation item; do not add backend permissions or buttons as a visual-consistency fix. Generic full-page schedule guidance is also refined by the documented native-dialog enhancement; preserve the fallback rather than treating the enhancement as a policy violation.

**Source/test-only or unexecuted:** shift create/edit, bulk changes and publication; no-JavaScript runtime behavior; network-failure injection; complete browser history restoration; Google OAuth; delivered password-reset/verification links; password changes; account deletion; real camera/QR hardware; and upload replacement. Reset/verification/confirmation templates and unreachable legacy template candidates were inventoried, with shared components inspected; their full lifecycle browser states were not claimed as tested. Published organization-chart/photo variants and long-record combinations were not exhaustively exercised.

Automatic approval review initially blocked the settings Done control; its non-submitting close handler was verified before testing. It also blocked creating a schedule draft. The user selected **source and existing tests only** for draft editing, so no draft was created. Final isolated database inspection found six appointments, one feedback record, and zero schedule weeks/shifts. The invalid feedback submission created no feedback. The disposable audit containers, network, and private database/cache/log volumes were removed after evidence collection.

**Report-only verification:** validate source references, local evidence links, coverage metadata, and `git diff --check`. Full Laravel tests and a production asset build are unnecessary because application code was not changed. Future fixes should run targeted tests first, then the full verification gate required by AGENTS.md.
