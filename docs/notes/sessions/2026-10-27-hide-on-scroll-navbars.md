---
type: session-log
date: 2026-10-27
---

# Hide-on-scroll navbars

## Task

Make both navbars (the public landing header and the signed-in workspace top bar) hide while scrolling down and return when scrolling up.

## What Changed

- `resources/js/scroll-header.js` (new): `nextHeaderHidden()` decides hide/show from scroll movement with an 8px threshold. The header is always shown within its own height of the top. `headerMustStay()` keeps it visible while focus is inside or anything inside has `aria-expanded="true"`, which covers the drawer toggle, the account dropdown and the landing mobile menu. `initializeScrollHeaders()` uses one passive, rAF-throttled scroll listener and resets on `turbo:load` and `pageshow`. It is wired in `resources/js/app.js`.
- `resources/views/welcome.blade.php` and `resources/views/layouts/navigation.blade.php`: `data-scroll-header` on the landing `<header>` and the workspace `<nav>`.
- `resources/css/landing.css`: the landing header slides up with `translateY(-100%)` over 250ms.
- `resources/css/app.css`: only the inner `.casa-topbar` slides, because a transform on the nav would make it the containing block for its fixed drawer and mobile dock. The hidden nav gets `pointer-events: none` so its empty sticky box does not block clicks, and its other children keep pointer events. The existing global reduced-motion rule removes the animation.
- Tests: `tests/js/scroll-header.test.mjs` (new). `RoleWorkspaceTest` and `ExampleTest` now assert the attribute.
- Docs: [BRAND_UI_GUIDE](../../BRAND_UI_GUIDE.md), [SCREEN_FLOW](../../SCREEN_FLOW.md) and [PROJECT_MEMORY](../../PROJECT_MEMORY.md).

## Verification

- `node --test 'tests/js/*.test.mjs'`: 92 passed.
- `php artisan test --filter='RoleWorkspaceTest|ExampleTest'`: 20 passed.
- Full `php artisan test`: 512 passed (5644 assertions) when run alone. Earlier runs had 39, 200 and 89 failures because another session was running the suite against the shared `testing` database at the same time. All of those were schema clashes, such as tables that already existed or were missing.
- `npm run build` succeeded (only the existing chunk-size warning). The Docker `app`, `mariadb` and `mailpit` services are running.
- Landing page in the browser pane at desktop and 375px widths: hides on scroll down, returns on scroll up and on focus, and stays while the mobile menu is open.
- The workspace bar was not checked in a browser because no local test account exists.

## Decisions

- This replaces the 2026-10-26 "always visible" decision at the user's request.
- The script uses the existing `aria-expanded` state instead of new hooks into the Alpine stores.

## Follow-ups

- Check the workspace bar visually once a local test account is available.
