---
type: session-log
date: 2026-10-26
---

# Sticky workspace top bar

## Task

Keep the signed-in workspace top bar visible while scrolling, and merge the logo and "View site" links because both open the public homepage.

## What Changed

- `resources/views/layouts/navigation.blade.php`: `sticky top-0 z-40` moved from the inner `.casa-topbar` div to the outer `<nav data-role-navigation>`. The inner div was the only in-flow child of the nav, so it had no room to stick and scrolled away. The separate View site link is removed. The logo now carries `data-view-site-link`, the aria-label "View the Casa Paraiso website" and a "View site" title.
- `resources/css/app.css`: `scroll-padding-top: 4.5rem` on workspace pages, so the skip link and anchors land below the pinned bar.
- `resources/views/customer/feedback/index.blade.php`: the sticky side card's offset changed from `lg:top-6` to `lg:top-20` so it clears the bar.
- `tests/Feature/RoleWorkspaceTest.php`: asserts the sticky nav, the merged logo link, and that the old separate link is gone.
- Docs: top-bar wording updated in [BRAND_UI_GUIDE](../../BRAND_UI_GUIDE.md), [SCREEN_FLOW](../../SCREEN_FLOW.md) and [PROJECT_MEMORY](../../PROJECT_MEMORY.md).

## Verification

- `php artisan test --filter=RoleWorkspaceTest`: 15 passed.
- Full `php artisan test`: 498 passed.
- `npm run build` succeeded (only the existing chunk-size warning).
- Docker `app`, `mariadb` and `mailpit` are up.
- Not checked in a browser: the local database has no seeded test accounts, and none were added.

## Decisions

- The bar is always visible rather than hide-on-scroll-down, which keeps it simple and needs no JavaScript.
- Calendar and table sticky headers scroll inside their own containers, so they did not need an offset.

## Follow-ups

- Check the pinned bar visually once a test account is available locally.
