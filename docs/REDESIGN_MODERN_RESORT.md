# Modern Resort Redesign

Branch-only visual redesign of Casa Paraiso. The work lives on the local `redesign` branch. `main` remains the stable, live version, and nothing here is merged into, committed to or pushed to it until the user decides otherwise.

## Direction

Modern Resort: lighter and airier, like a boutique hotel app. Same concept, same brand.

- **Kept:** the logo artwork and its rules, every Casa color token and hex value, Cormorant Garamond and Manrope, all routes, behavior, data, copy rules and the accessibility rules in [BRAND_UI_GUIDE.md](BRAND_UI_GUIDE.md).
- **Changed:** how the palette is used (cream and paper dominate, palm is the single action color, cacao and brass are accents), radii, shadows, spacing, the sidebar, header and button shapes, and photo prominence.

## Tokens

Defined in the `@theme` block of `resources/css/app.css`. The existing `--color-casa-*` names remain, and the new semantic tokens build on them.

| Token | Value | Use |
| --- | --- | --- |
| `--radius-casa-card` | 20px | Cards, dialogs, editorial panels |
| `--radius-casa-panel` | 16px | Operational panels, tables, toolbars |
| `--radius-casa-control` | 12px | Inputs, icon buttons, nav links |
| `--radius-casa-pill` | 999px | Buttons, badges, chips, pagination |
| `--shadow-casa-soft` | wide, low-opacity | Default card elevation |
| `--shadow-casa-card` | lighter than before | Raised cards and the frosted header |
| `--shadow-casa-lift` | unchanged | Dialogs and drawers |
| `--color-casa-glass` | paper at about 80% | Frosted header, dock and dialog bars |
| `--color-casa-danger`, `--color-casa-danger-dark` | the existing error reds | Danger buttons and errors, replacing hardcoded hex |
| `--color-casa-success`, `-warning`, `-info`, `-danger-text` and their `-bg` pairs | existing status shades | Status pills, notices and calendar events |
| `--color-casa-violet`, `--color-casa-rose` and their `-bg` pairs | existing categorical shades | Therapist calendar lanes and role badges |

Page stylesheets contain no raw hex values. Tints, lines and chart shades are `color-mix()` blends of these tokens. Only `landing.css` keeps its local `--landing-*` variables.

## Component rules

- **Buttons** are pills, with sentence-case labels in the workspaces. Primary is solid palm; secondary is paper with a hairline border and cacao text; danger keeps the existing reds. All are at least 44px tall.
- **Inputs** use the control radius, a paper fill, a hairline border and a soft palm focus ring.
- **Cards** use the card radius, a paper surface, a hairline border and the soft shadow. Operational panels use the panel radius.
- **Header:** the page header and the mobile top bar are frosted (glass color plus backdrop blur), sticky, with a hairline bottom border. Text on glass must keep AA contrast.
- **Sidebar:** a light paper sidebar replaces the dark wood panel. Its active link is a pale palm pill with palm-dark text. The collapse rail, tooltips, badges and the mobile drawer keep their behavior.
- **Badges, chips and pagination controls** are pills.
- **Density:** the workspaces keep their compact table density and 12–16px gaps. Only shape, surface and elevation change there.

## Phase checklist

- [x] 0. Branch guard and this spec
- [x] 1. Tokens, plus exact palette and status hex values mapped to tokens. Page-only one-off shades remain until each page's detailed pass.
- [x] 2. Shared shell (layouts, navigation) and shared components
- [x] 3. Public homepage (verified at 1440px and 375px), guest layout and auth pages
- [~] 4–6. Shape pass applied to every page stylesheet: cards 20px, panels 16px, controls 12px, buttons pills. Calendar events, chart bars, tabs, dots and badges keep their own corners. Each workspace still needs a signed-in visual review and any detailed page polish.
- [x] 7. Brand guide shape rules and project memory updated

## Verification per phase

Run the relevant tests, then the full Laravel suite, the asset build and the Docker startup through `scripts/casa-docker.ps1`. Check the pages in the browser at 1440px and 375px: keyboard focus, 44px targets, AA contrast and reduced motion. Screenshots go to `output/redesign/` and are not committed.
