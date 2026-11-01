---
type: session-log
date: 2026-11-01
---

# 20261101 release live

## Task

Deploy everything built since the 2026-10-30 release (`891bd20`) to https://casaparaiso.site.

## What Changed

- Pushed `redesign` and fast-forwarded `main` to it. Both are at `fc8ec25` on `origin`.
- Packaged `fc8ec25` as `casa-paraiso-hostinger-20261101-132517-2c53e0.zip`.
- The deploy SSH key from 2026-10-26 was not on this PC. The owner added this PC's key in hPanel as `casa-paraiso-deploy-20261101`.
- Extracted the release on Hostinger as the sibling `casa-paraiso-redesign-20261101/`:
  - It has a copied `.env` (mode 600, never displayed).
  - Its `storage/app/public` is a symlink to `casa-paraiso/storage/app/public`.
- Took a private dump of the live database into `.casa-deploy-20261101/pre-migrate-20261101.sql` (owner-only, 425 KB).
- Ran the seven pending additive migrations: `2026_10_30_120000` anonymous feedback, `2026_11_01_000000` RFM rules, `_010000` memberships, `_020000` gift vouchers, `_120000` online-booking block, `2026_11_02_000000` back massage, and `_010000` closed dates.
- Switched sentiment to Gemini in the new release's `.env` only:
  - The owner copied `GEMINI_API_KEY` from the local `.env` with a one-off helper script, which was then deleted. The key was never displayed.
  - `SENTIMENT_MODEL_DRIVER=gemini` and `SENTIMENT_CLASSIFIER_MODE=model` are set.
- Switched `public_html` atomically to `casa-paraiso-redesign-20261101/public`.
- Added an every-minute hPanel cron for `casa-paraiso-redesign-20261101/artisan schedule:run`.

## Verification

- Local:
  - `php artisan test` passed 625 tests. The first run segfaulted (signal 11) with no failing test, and passed after clearing the CLI OPcache file cache.
  - The 95 JS tests passed.
  - `npm run build` passed.
  - Docker `app`, `mariadb` and `mailpit` are running.
- Packaging: the composer and npm audits were clean and the archive smoke checks passed.
- Host, before the switch:
  - `sha256sum -c` passed, and the CLI has `zip`, `dom`, `mbstring` and `gd`.
  - `migrate:status` listed exactly the seven expected migrations, all of which ran.
  - `casa:audit-crud-integrity` and every `casa:check-hosting` check passed on MariaDB 12.3.2.
  - One Gemini call with a fictional comment returned `positive`.
  - A private kernel render returned 200 for `/`, `/login`, `/privacy-policy` and `/up`, and 404 for an unknown path.
  - The old release kept serving 200 after the migrations.
- Live, after the switch:
  - `/`, `/login`, `/privacy-policy`, `/up` and both build assets return 200.
  - `/.env`, `/.git/config` and the log path return 403. `/composer.json` and unknown paths return 404.
  - `http://www.` redirects to the bare HTTPS URL, keeping the query.
  - The homepage shows 1:00 PM to 11:00 PM and prices from PHP 299.
  - The new release has written no log.
  - A manual `schedule:run` returned "No scheduled commands are ready to run".

## Decisions

- The owner copied the Gemini key themself, so it never passed through Claude.
- The rerun after the segfault counted as the test gate, because the crash came from the OPcache file cache, not a test.

## Follow-ups

- [x] Confirm the new cron job's hPanel output, then delete the `casa-paraiso-redesign-20261030` job. Its output was the normal "No scheduled commands are ready to run", and the 20261030 job was deleted with the owner's approval, leaving only the new one.
- [ ] Rollback if needed: from the domain directory, `ln -s "$PWD/casa-paraiso-redesign-20261030/public" public_html.new && mv -T public_html.new public_html`, then repoint the cron. That release runs on the rules sentiment.
- [ ] Revoke the `casa-paraiso-deploy-20261026` and `casa-paraiso-deploy-20261101` SSH keys in hPanel once host work is finished.
- [ ] Old upload ZIP `casa-paraiso-hostinger-20261026-044221-be8a29.zip` still sits in the domain directory.
