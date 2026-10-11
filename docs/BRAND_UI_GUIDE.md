# Casa Paraiso Brand and UI Guide

## Direction

Casa Paraiso should feel warm, tropical, premium, calm, and personally cared for. Guest and customer copy is inviting and restorative. Admin and staff copy is direct, concise, and operational.

The signature visual is the **tropical canopy**: an arched image frame paired with restrained five-leaf linework inspired by the existing logo. Use it for high-emotion marketing moments, not as decoration on every screen.

## Logo

- Use `public/images/casa_paraiso_logo.jpg` without recoloring, cropping, stretching, transparency, gradients, or effects.
- Place the logo on white or Casa Paper surfaces so the supplied white background feels intentional.
- Keep clear space around the artwork equal to the approximate height of its leaf mark.
- Keep the full logo at least 120px wide in digital interfaces.

## Color System

| Token | Hex | Primary use |
| --- | --- | --- |
| Casa Cacao | `#7A3E14` | Brand emphasis, editorial labels, links |
| Casa Palm | `#4F6A4E` | Primary actions and active states |
| Deep Palm | `#334736` | Hover states and dark green contrast |
| Casa Brass | `#B38745` | Restrained accent, dividers, decorative emphasis |
| Brass Light | `#D4AF73` | Small labels and meaningful text on dark surfaces |
| Casa Sand | `#F3EBDD` | Secondary surfaces and grouped controls |
| Casa Paper | `#FFFCF7` | Cards, forms, and logo surfaces |
| Casa Ink | `#232620` | Primary text |
| Casa Background | `#F5F0E7` | Application background |
| Casa Muted | `#67675F` | Secondary text |
| Casa Border | `#DCD2C2` | Borders and separators |

Casa Brass is decorative and should not carry small text. Use Brass Light for meaningful text on dark surfaces. Success, warning, error, and information states must include text or icons rather than relying on color alone.

## Typography

- **Cormorant Garamond 600–700:** marketing heroes, customer lounge display moments, and short emotional statements.
- **Manrope 400–800:** navigation, operational headings, body text, forms, tables, buttons, metrics, and captions.
- Body copy is 16px where space permits and never below 14px for meaningful content.
- Uppercase text is limited to short labels with increased letter spacing.

## Components and Layout

- Marketing and customer editorial cards use 24–32px radii and generous breathing room.
- Admin and staff surfaces use 14–16px radii, compact metrics, and information-dense tables.
- Primary controls have a minimum 44px target size.
- Customer appointment and feedback history use responsive cards. Operational record lists remain tables with keyboard-focusable horizontal scrolling.
- In the Blade application, Admin and staff use a persistent desktop sidebar and mobile drawer. Customers use a desktop sidebar and mobile bottom navigation for My appointments, My feedback, and My profile.
## Authenticated Density Scale

Compact density applies only inside the authenticated application shell. Public marketing and guest authentication screens keep their spacious editorial layout.

| Element | Authenticated standard |
| --- | --- |
| Desktop sidebar | 256px (`16rem`) expanded; 80px (`5rem`) collapsed icon rail, at 1024px and above |
| Page and section rhythm | 12–16px gaps; 16px base card padding, increasing to 20px where a form needs it |
| Operational card radius | 14–16px with a light, low-spread shadow; compact controls may use 10–12px |
| Page title | 24–30px |
| Section heading | 18–22px |
| Meaningful text | 14px minimum |
| Buttons, fields, tabs, and page controls | 44px minimum interaction target |

- Use the shared `page-heading` component for authenticated page titles. Customer headings may use its editorial variant; admin and staff headings use the operational variant.
- All roles share a desktop sidebar toggle with a browser-local preference (`casa.sidebar.collapsed`), initially expanded. The collapsed rail retains accessible link names, hover/focus labels, active states, badges, account initials, and logout. Mobile navigation stays independent. Sidebar and page offset animate together for 180ms only on a user toggle, respecting reduced motion.
- Use `stat-strip` for compact context on detail pages and appointment calendars. Reserve larger metric cards for dashboards, feedback, customer rewards, transactions, and reports.
- Put record totals and useful status counts in toolbar chips. Do not add generic “Showing” or “Search Ready” metric cards above lists.
- List filters collapse below the 1024px breakpoint. The toggle exposes an accessible expanded state and active-filter count; render “Clear filters” only when at least one filter is active.
- Keep tables inside the shared keyboard-focusable horizontal-scroll region. Workspace week tabs and month grids may scroll internally when seven 44px targets cannot fit; the booking wizard's seven-column month grid fits the modal without horizontal scrolling.
- Operational week selectors use tab semantics with Left/Right and Home/End keyboard movement. Customer month grids and operational week strips must keep an accessible label on their keyboard-focusable scroll region.
- At very narrow widths, customer dock labels may be visually hidden, but their accessible names must remain available.

## Admin Dashboard

The Today dashboard uses four cream metric cards with muted palm icon circles, an attention strip, an upcoming-visits table beside latest feedback and quick actions, and a seven-day bar chart below. The existing workspace header and sidebar remain shared. Dashboard-only styles live in `resources/css/admin-dashboard.css`; metrics use two columns on small screens and one below 360px, while the main panels stack below 1280px. The chart includes confirmed and completed visits and exposes day/count text to assistive technology. Upcoming visits beyond today include their date; attention copy describes overdue visits and new feedback without implying unread tracking or outstanding payment balances.

## Form and Modal Policy

- Appointment booking for admin, super-admin, reception, and customers uses a centered dialog, at most 70rem wide and 90dvh high, with a dimmed backdrop, one title, step indicator, and persistent footer. The three steps are Treatment details, Date and time, and Review and save. The body scrolls; Back, Next, Cancel, and the final Save/Confirm action stay visible. Admin/reception edits use the same flow; direct form URLs retain a full-page version. Preserve the shared Casa palette, typography, customer vouchers, operational internal notes, and permitted edit-status controls.
- Operational Treatment details uses a typed customer name, optional phone, and keyboard-accessible guest/enrolled suggestions with name, phone, code, and type. Selecting a profile reuses saved contact details; unmatched names book new guests. The month calendar highlights store-open dates independently of therapist capacity. On desktop, place the calendar left and native Start time dropdown right, with Assigned therapist directly below for operations. Include disabled Unavailable options for all blocked store-hour starts. Customer assignment stays automatic. Stack the columns on small screens. Preserve values when returning to steps, show the earliest erroneous step after validation, and keep historical booking fields read-only. Unchanged existing schedules remain valid for permitted notes/status edits even when no longer bookable.

- Use full-page forms for complex CRUD work: therapist and service management, schedule entries and exceptions, and transaction creation/editing.
- Reserve modals for short contextual workflows: calendar-based booking, the atomic Finish service action, feedback and notes, and explicit confirmation prompts.
- Full-page forms must provide a clear Cancel or Back action to the relevant list or record detail; validation errors stay on that page with entered values preserved.

## Pagination Pattern

- `casa.pagination.per_page` is the single page-size source and is fixed at 15 records. Controllers must not read a user-provided `per_page` value or expose a page-size selector.
- Use Laravel's registered `pagination.compact` view for authenticated record lists. It always shows `first–last of total` when results exist, including a single-page result, and renders nothing for an empty result.
- Wide layouts show numbered pages with Previous and Next controls. Small-screen pagination shows Previous, `Page X of Y`, and Next. Current and disabled states must be programmatically exposed and every page control must retain a 44px target.
- Preserve active filters and sorting with `withQueryString()`; state-changing forms and exports keep their existing request paths.
- When two independent lists share a screen, give the secondary paginator its own query key and fragment. Therapists and Services are separate workspaces, each using `page`; therapist page links preserve filters and sorting while dropping `selected` and `tab`.
- Do not paginate appointment calendars, dashboard previews, detail-history previews, or form selector collections. The admin active service queue uses fixed 15-record pagination with `queue_page` and `#service-queue`.

## Admin Appointments

Admin and super-admin appointment details use a customer identity card, Booking details with treatment/add-on rows, Visit notes, and a Booking activity timeline in the left column; Visit status and Payment summary occupy the right column. The 63/37 layout stacks below 1280px, with note panels stacking below 640px. Cream cards, light borders, green visit states, plain-text action labels, and restrained icons follow the supplied reference. Page-specific styles live in `resources/css/appointment-detail.css`; the shared sidebar stays unchanged.

Finish and No-show remain disabled until the scheduled start. Confirmed appointments record payment through the existing Finish service modal; terminal appointments retain the standalone Record payment link. The payment card shows expected booking total and the latest transaction's exact status and recorded amount, followed by links to all transactions and feedback when available. No paid amount or balance is inferred from partial, refunded, or void transactions.

The admin and super-admin Appointments page uses three bordered status cards for appointments scheduled today in Asia/Manila, followed by a full-width next-visits table and a Monday–Sunday booking schedule. The queue includes today's confirmed visits and earlier unfinished confirmed visits; older visits include their date and overdue visits are labelled. View opens a centered, read-only detail preview with Close and Open full details, without embedded management dialogs. The preview shares the booking shell's 70rem maximum width and 90dvh height limit, keeps its close control visible, and scrolls internally. It fetches current details on every open, contains keyboard focus, and restores trigger focus on dismissal; ordinary navigation retains the full detail page. Finish opens the existing atomic completion/payment form, and No-show requires confirmation. Both actions become available at the scheduled start. Successful queue actions return to Appointments with the queue page and calendar mode retained, clamping an emptied last page; Finish from the standalone detail page retains its payment-record redirect.

The booking schedule has stable therapist colors, textual statuses, actual appointment durations, and separate lanes for simultaneous bookings. It spans 1 PM through midnight and scrolls internally. Below 1024px, it becomes a selectable-day agenda. Availability keeps the selected-day therapist coverage view. Therapist selection is always visible; service and status filters are expandable. Page-specific styles live in `resources/css/appointments.css`, and the weekly presentation is opt-in so other role calendars keep their existing layout.

View all appointments opens `admin.appointments.records`, a searchable history table with status, therapist, and inclusive Manila scheduled-date filters and fixed 15-record pagination.

## Admin Therapists

The admin and super-admin Therapists index uses a 57/43 list/detail split at 1280px and above, stacking the profile below the list on smaller screens. Search, Account status, and Bookability filters sit above the cream bordered panels; mobile filters use the shared disclosure. Initials, service counts, separate account/booking-setting pills, and current-week working days keep the list compact. The selected row uses a pale green background. Styles live in `resources/css/therapists.css` and stay scoped to this workspace.

Profile, Services, Schedule, and Availability are server-rendered navigation links with an active state. Profile shows contacts, biography, assigned-service previews, effective schedule summaries, and the booking setting. Schedule shows Sunday–Saturday effective hours in Asia/Manila, including published roster precedence and exceptions; midnight ends are labelled explicitly. Availability explains that actual appointment availability also depends on account status, services, schedules, and existing bookings. The native actions disclosure offers Edit therapist and Open full profile; manage links retain the existing full-page forms and roster. Only super administrators see Add therapist. The service catalog remains on Services.

## Admin Services

The admin and super-admin Services index uses a prominent heading and Add service action above separate cream filter and catalog panels. Search sits on the left and the labelled Status selector on the right; Enter or the search icon submits the GET form, and status changes submit automatically. Below 1024px, filters use the shared disclosure; without JavaScript, the fields and Apply filters button remain available.

The five-column table shows Treatment with the existing description, Duration, Price, Status, and Actions. Whole peso prices omit decimals; fractional prices retain two decimal places. Active and inactive pills include text and a status dot, while Edit, Deactivate, and Activate use outlined icon buttons. Changes to activation still require the existing confirmation dialog. Descriptions wrap and remain escaped; slugs and usage counts stay on the service details page. All statuses is the default, sorting and fixed 15-record pagination retain query state, and empty searches offer Clear filters while an empty catalog offers Add service. Styles are scoped in `resources/css/services.css`; the shared sidebar and other workspaces retain their presentation.

## Admin Payments

The admin and super-admin Payments index follows the supplied Payments reference: Record payment and Export CSV actions, two equal cream metric cards with pale green icon circles, a separate search/status/visit-month panel, and a bordered Transactions table. Page-specific styles live in `resources/css/payments.css`; the shared sidebar, header, and other workspaces retain their presentation.

Paid total includes only fully paid transactions; Unpaid total includes only unpaid transaction amounts. Both follow all selected filters across pages. The interface never infers a remaining balance from partial transaction amounts. Table rows show customer emails, Manila visit dates/times, peso amounts, labelled method icons, textual status pills, and outlined View/Edit actions. Whole peso amounts omit decimals; fractional amounts retain two places.

The month picker defaults to All dates and filters linked scheduled visits when selected. Status and month changes submit the GET form automatically; Enter or the search button applies search, and no-JavaScript controls include Apply filters. Mobile filters use the shared disclosure, metric cards stack below 640px, and the table scrolls in the keyboard-focusable shared region. Pagination stays fixed at 15, without a page-size selector. CSV exports use the same filters and order as the table, while Revenue report remains a secondary link.

## Admin Reports

The admin and super-admin Reports dashboard follows the supplied Reports reference while retaining the shared sidebar and page heading. A cream date-range/category panel with Export CSV precedes three metrics with palm icon circles: Total revenue, Completed appointments, and Unique customers. Whole peso values omit decimals; fractional values retain two places. Previous-period comparisons include text and directional arrows, and show No prior activity when the baseline is zero.

At desktop widths, revenue bars sit beside appointment-status and feedback-sentiment doughnuts. Service revenue rankings sit beside a four-column RFM customer-group table, followed by the five report record tabs. Charts use server-rendered CSS/SVG with visible totals and legends; they require neither JavaScript nor a chart CDN. Longer revenue ranges switch from weekly to monthly buckets and wrap into additional rows; horizontal scrolling is confined to record and RFM tables. Empty states retain true zero totals. The existing preset group names and appointment statuses replace unsupported sample labels.

Styles are scoped in `resources/css/reports.css`. Panels stack on smaller screens; tabs, charts, and tables scroll internally with accessible names and keyboard focus. Meaningful text remains at least 14px and interactive targets at least 44px. Native date disclosures and GET forms work without JavaScript; Alpine only enhances select submission. All reports shows all panels; focused categories show the relevant metrics and charts. Search/status controls clearly apply to record rows and the exported CSV, while dashboard aggregates use the selected date range.

## Admin Commissions


The admin and super-admin Commissions index uses three cream Pending, Paid, and Net total cards with pale green icon circles above a separate therapist/status/earned-date filter panel. All cards follow the current filters across all pages and include signed adjustments. Basis amount, stored rate, and Commission replace the reference's gross/deduction labels to reflect the actual commission policy. Whole peso amounts omit decimals; fractional amounts retain two decimal places.

Commission records and Payout details use a 63/37 split at 1280px and above, stacking on smaller screens. The seven-column records table highlights the selected row and uses keyboard-focusable horizontal scrolling. Therapist and eye links select the record and anchor to its details; filtering and pagination reset selection. Shared compact pagination stays fixed at 15. Mobile filters use the shared disclosure with a no-JavaScript fallback.

The shared details panel shows therapist contacts, earned date, record type, service basis/rate/commission, source and adjustment links, payout metadata, and escaped notes. Mark as paid expands a native disclosure containing the required payout date and optional note; server errors reopen the form with entered values. Paid records and zero-value commissions explain their existing payout states. Export commissions downloads every matching record using the table's order. Styles live in `resources/css/commissions.css`; the shared sidebar and header retain their existing presentation.

## Admin Attendance

Admin and super-admin Attendance follows the supplied reference with a Manila date subtitle, Attendance station action, separate cream Date/All dates/Therapist/Attendance status filter panel, and a compact records table beside the selected record. The roughly 2:1 split applies at 1280px and above; smaller screens stack the panel below the table. Initials, names/emails, Manila times, textual status pills, and outlined Correct record links identify rows; the selected row uses pale green. Keep 14px meaningful text, 44px controls, shared table scrolling, mobile filter disclosure with a no-JavaScript fallback, and fixed 15-record compact pagination. Styles remain scoped in `resources/css/attendance.css`.

The correction panel retains the time-in/time-out choice and corrected date/time alongside a reason dropdown and details limited to 300 characters (required for Other). Show current saved values, actual recorder/source, and expandable audit activity; do not invent a system recorder or an original snapshot. Cancel resets entered values; Close hides the panel. Errors preserve inputs on the affected record; success retains filters and clamps an empty last page. This page always provides Clear filters to open all history, including from its default daily view.

## Admin Customer Rewards

Admin and super-admin Customer rewards follows the supplied reference with four cream Available, Reserved, Used, and Expired cards, pale green icon circles, and a six-column Reward history table beside Automatic reward settings. The 68/32 split applies at 1280px and above; panels stack below that width, metrics use two columns below 1024px and one below 360px. Styles are scoped in `resources/css/rewards.css`; the existing shared sidebar, header, Manrope typography, 14px meaningful text, and 44px controls remain in use.

Search, lifecycle status (including Dismissed), and labelled native From/To issued-date filters affect history and every status count across all pages. Dates use inclusive Manila day boundaries and default to All dates. Mobile filters use the shared disclosure with a no-JavaScript fallback; the table retains keyboard-focusable horizontal scrolling and fixed 15-record compact pagination. Customer emails or guest phones sit beneath names, dates reflect stored issue and expiry values, and historical add-on names include inactive add-ons with stored-offer fallbacks.

The settings summary shows the complimentary add-on reward type, saved validity or No expiration, active fixed-group chips, and a read-only Enabled/Disabled indicator derived from group activation. Automatic reward settings and Edit open the full-page five-group form at `admin.promotions.settings.edit`; Save returns to history and validation retains inputs. Configuration changes affect future rewards only. View opens the existing reward detail, where an available reward can be dismissed through its confirmed PATCH workflow. Existing issuance and RFM thresholds remain unchanged.

## Admin Feedback

Admin and super-admin Feedback follows the supplied reference with three cream Positive, Neutral, and Negative cards using pale green face-icon circles, followed by a separate filter panel, equal-width service/topic panels at 1280px and above, and Customer reviews. Panels stack below that width and metric cards stack below 640px. Styles are scoped in `resources/css/feedback.css`; Casa typography, the shared sidebar and header, 14px meaningful text, 44px controls, and keyboard-focusable table scrolling remain in use.

Search, Service, and Sentiment are visible on desktop. The native More filters disclosure contains optional Submitted from/to dates and Experience topic, and opens when those filters are active or invalid. Dropdown changes submit automatically; Enter and the search button submit search. Mobile uses the shared filter disclosure, with Apply filters and visible filter fields available without JavaScript. All history is the default and Clear filters removes every filter. All filters affect the review table, sentiment cards, service summary, and topic findings across every page; submission dates follow inclusive Manila days.

The service table includes every represented service with Positive, Neutral, Negative, and Total counts, ordered by total descending and name. Its bounded scroll region accommodates larger histories. Experience topics use their configured labels and accessible meters showing positive findings divided by all stored findings for that topic. A review can contribute findings of both polarities, so the denominator is findings rather than reviews.

The five-column review table shows customer names linked to feedback details, emails or guest phones, treatment/service, Manila submission date, textual sentiment pills, and escaped comment previews limited to 180 characters. Longer comments offer Read full review; ratings, classification evidence, and manual review remain in the existing detail screen. Pagination remains fixed at 15. Empty history, no matching reviews, and absent topic findings use distinct truthful messages.

## Notifications

Operational Notifications follows the supplied reference with All/Unread navigation, a customer/service/booking-number search field, a cream booking-alert list, and a booking-detail panel. The 62/38 split applies at 1280px and above; smaller screens stack details below the list and retain the shared keyboard-focusable table scrolling. Selected rows use pale green, unread dots include accessible status text, and the detail read-status pill includes a visible label.

Current customer contacts, service and scheduled duration, therapist, Manila schedule, received timestamp, and a shaded customer-note block use restrained green icon circles. View booking and View in calendar occupy the footer, stacking on mobile. Missing or reassigned bookings show an unavailable message and disabled actions. Page-specific styles live in `resources/css/notifications.css`; shared navigation, the approved logo, Casa colors, 14px meaningful text, and 44px controls remain in use. Search and preview work with normal Laravel GET requests; previewing alone never marks an alert read.

## Admin Settings

Admin and super-admin Settings follows the supplied reference within the existing shared sidebar and page heading. Two equal columns apply at 1024px and above: Business identity beside a Payments/Environment checks stack, then Company history beside Public organization chart. Smaller screens stack the cards. Scoped styles live in `resources/css/settings.css`, using Casa Paper surfaces, light borders, restrained palm accents, Manrope typography, 14px meaningful text, and 44px controls.

Business identity shows name, email, phone, and address; a native More public details disclosure contains landmarks and public links. Environment rows display the real debug, HTTPS-cookie, session-encryption, and trusted-host configuration values with textual status pills; they do not claim application, database, or email health. Operating rules and role-aware user access sit in a secondary disclosure below the main cards.

The saved chart preview connects owner, administrator, and team tiers, retaining public role titles, names, summaries, and photos. An empty chart invites editing without inventing published roles. Edit chart expands the existing editor in place; closing it preserves inputs. Native disclosures reopen for their validation errors. One multipart Save changes submission retains the existing PATCH route and publishes all edited settings. The team editor preserves stable row identities during removal and has an eight-slot server-rendered no-JavaScript fallback, so unrelated saves preserve saved chart fields and photos. Plain-text history retains paragraph breaks and the existing 6000-character limit.

## Imagery

Use warm editorial treatment details, anonymous hands, natural linens, dark wood, woven cane, ceramic vessels, restrained foliage, and believable window light. Avoid identifiable faces, clinical equipment, excessive candles, glossy stock-photo styling, added heavy orange casts, text, and third-party logos. The approved Casa Paraiso business photos retain their original colors and embedded spa branding.

Project imagery is stored under `public/images/spa/` as responsive WebP variants. Load the landing hero eagerly and all below-the-fold imagery lazily.

The homepage hero uses `casa-lobby-960.webp` and `casa-lobby-1600.webp`, showing the actual reception and lounge. The booking section uses `casa-treatment-room-800.webp` and `casa-treatment-room-1200.webp` in a square frame at every breakpoint to preserve the embedded Casa Paraiso logo. The shared guest layout uses `casa-towel-detail-720.webp` and `casa-towel-detail-1024.webp`, with the crop focused on the towels and massage table. Preserve source proportions, do not upscale, and keep the original supplied JPEGs unchanged.

## Voice and Tone

| Context | Voice | Example |
| --- | --- | --- |
| Public marketing | Warm and restorative | “Let the day soften here.” |
| Customer workflow | Calm and transparent | “Your time and therapist are reserved as soon as booking succeeds.” |
| Therapist operations | Clear and focused | “Review the confirmed time and treatment.” |
| Admin operations | Concise and decision-oriented | “Review time-sensitive requests first.” |
| Errors | Plain and helpful | “Availability could not be loaded. Try another service or month.” |
| Success | Reassuring and specific | “Appointment confirmed and added to the schedule.” |

Preserve established service names, prices, business hours, statuses, and the line “Reserve your spot. You deserve this.”

## Accessibility and Motion

- Meet WCAG AA: 4.5:1 for normal text and 3:1 for large text and interface boundaries.
- Every control needs a visible label or accessible name and a visible keyboard focus state.
- Keep motion between 160–220ms and use it only to explain navigation or state changes.
- Honor `prefers-reduced-motion` and never make animation necessary to understand content.
