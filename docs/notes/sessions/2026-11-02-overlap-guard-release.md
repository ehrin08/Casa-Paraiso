---
type: session-log
date: 2026-11-02
---

# Customer double-booking guard release

## Task

Put the customer double-booking fix (`c34ec53`) live on https://casaparaiso.site. See [Customer double-booking guard](2026-11-02-customer-overlap-guard.md).

## What Changed

- Pushed `redesign` to `origin` and fast-forwarded `main` to it. Both are at `c34ec53`.
- Packaged `c34ec53` as `casa-paraiso-hostinger-20261102-060954-5f22cc.zip`. Uploaded it and its checksum to the private `.casa-deploy-20261102b/` folder.
- Extracted it on Hostinger as the sibling `casa-paraiso-redesign-20261102b/`:
  - its `.env` is a mode-600 copy of the 20261102 release's (never displayed);
  - its `storage/app/public` is a symlink to `casa-paraiso/storage/app/public`;
  - `storage:link`, `config:cache`, `route:cache` and `view:cache` were run.
- No migrations were pending, so the database was not dumped or changed.
- Switched `public_html` atomically to `casa-paraiso-redesign-20261102b/public`.
- Recorded the release in [Project Memory](../../PROJECT_MEMORY.md).

## Verification

- Local: 641 Laravel tests passed, `npm run build` passed, Docker started, and the package's archive smoke checks and dependency audits passed.
- Host, before the switch:
  - `sha256sum -c` passed.
  - `migrate:status` showed no pending migrations.
  - `casa:audit-crud-integrity` and `casa:check-hosting` passed.
  - A private kernel render returned 200 for `/`, `/login`, `/privacy-policy` and `/up`, and 404 for an unknown path.
  - The release code contains `hasCustomerOverlap`.
- Live, after the switch:
  - `/`, `/login`, `/privacy-policy`, `/up` and both build assets return 200.
  - `/.env`, `/.git/config` and the log path return 403. `/composer.json` and unknown paths return 404.
  - `http://www.` ends at the bare HTTPS URL, keeping the query.
  - The new release has written no log.

## Decisions

- This is a full sibling release, not an in-place file patch, so rollback stays a single link switch.

## Follow-ups

- [ ] Repoint the hPanel cron to `casa-paraiso-redesign-20261102b/artisan schedule:run` and remove the older jobs.
- [x] Delete the uploaded ZIP and checksum in `.casa-deploy-20261102b/`. Done with the owner's approval; `RELEASE-20261102b.json` is kept, and the domain directory has no ZIPs left.
- [ ] Revoke the deploy SSH keys in hPanel once host work is finished.
- Rollback if needed: from the domain directory, `ln -s "$PWD/casa-paraiso-redesign-20261102/public" public_html.new && mv -T public_html.new public_html`, then repoint the cron.
