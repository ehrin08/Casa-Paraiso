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
- In the Blade application, Admin and staff use a persistent desktop sidebar and mobile drawer. Customers use a desktop sidebar and mobile bottom navigation for Appointments, Feedback, and Profile.
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
- Keep tables inside the shared keyboard-focusable horizontal-scroll region. Week tabs and month grids may scroll internally when seven 44px targets cannot fit.
- Operational week selectors use tab semantics with Left/Right and Home/End keyboard movement. Customer month grids and operational week strips must keep an accessible label on their keyboard-focusable scroll region.
- At very narrow widths, customer dock labels may be visually hidden, but their accessible names must remain available.

## Form and Modal Policy

- Use full-page forms for complex CRUD work: therapist and service management, schedule entries and exceptions, transaction creation/editing, and appointment editing.
- Reserve modals for short contextual workflows: calendar-based booking, the atomic Finish service action, feedback and notes, and explicit confirmation prompts.
- Full-page forms must provide a clear Cancel or Back action to the relevant list or record detail; validation errors stay on that page with entered values preserved.

## Pagination Pattern

- `casa.pagination.per_page` is the single page-size source and is fixed at 15 records. Controllers must not read a user-provided `per_page` value or expose a page-size selector.
- Use Laravel's registered `pagination.compact` view for authenticated record lists. It always shows `first–last of total` when results exist, including a single-page result, and renders nothing for an empty result.
- Wide layouts show numbered pages with Previous and Next controls. Small-screen pagination shows Previous, `Page X of Y`, and Next. Current and disabled states must be programmatically exposed and every page control must retain a 44px target.
- Preserve active filters and sorting with `withQueryString()`; state-changing forms and exports keep their existing request paths.
- When two independent lists share a screen, give the secondary paginator its own query key and fragment. Team & Services uses `page` for staff and `services_page` plus `#service-catalog` for the embedded catalog.
- Do not paginate appointment calendars, the active service queue, dashboard previews, detail-history previews, or form selector collections.

## Imagery

Use warm editorial treatment details, anonymous hands, natural linens, dark wood, woven cane, ceramic vessels, restrained foliage, and believable window light. Avoid identifiable faces, clinical equipment, excessive candles, glossy stock-photo styling, heavy orange casts, text, and third-party logos.

Project imagery is stored under `public/images/spa/` as responsive WebP variants. Load the landing hero eagerly and all below-the-fold imagery lazily.

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
