---
type: session-log
date: 2026-10-26
---

# Hostinger public cutover

## Task

Continue the Hostinger deployment: verify Gmail SMTP, check for data drift, switch the public root, verify the public site, clean up temporary host files and commit the Hostinger readiness work.

## What Changed

- Production `.env` on the host now has the user-supplied Gmail app password, and the configuration cache was rebuilt. No value was displayed or recorded.
- `public_html` on casaparaiso.site is now a symlink to `casa-paraiso/public`, with the user's explicit approval. Hostinger's default directory is kept as `public_html.default-20261026`.
- Temporary host copies of the OAuth credentials, the database export, the uploads archive, the helper scripts and the private mail entry were removed. Recovery copies and fingerprint evidence were kept.
- [PROJECT_MEMORY.md](../../PROJECT_MEMORY.md) records the live cutover and the open launch gates. [HOSTINGER_DEPLOYMENT.md](../../HOSTINGER_DEPLOYMENT.md) notes the `www` redirect.
- Committed the Hostinger readiness work that was already verified: the `casa:check-hosting` command, `HostingReadiness`, trusted-proxy config, packaging script and `deploy/`, tests and docs.

## Verification

- Gmail STARTTLS authentication succeeded, without sending mail. `casa:check-hosting` passed all 23 checks on the host.
- The local database snapshot matched the import baseline exactly. The Hostinger snapshot differed only in the runtime `cache` and `sessions` tables; the other 35 tables, totals, sequences, foreign keys and the integrity audit matched.
- Public HTTPS:
  - `/`, `/login`, `/privacy-policy` and `/up` return 200.
  - HTTP returns a 301 to HTTPS.
  - Private paths return 403/404, and unknown pages show the branded 404.
  - Cookies are secure; the session cookie is HttpOnly.
  - Assets load, and the browser shows no console errors.
  - `/auth/google` redirects with the production callback and only basic scopes.
- No application errors were logged.
- No application code changed this session, so the earlier local gates (483 Laravel tests, asset build, Docker startup) were reused. `git diff --check` is clean.

## Decisions

- The cache and session row differences come from private checks and cron, not business activity, so no re-transfer was needed.
- Hostinger is now the live database. Local business writes must not resume.
- The `www` 400 was not a cutover blocker. It was fixed afterwards with a generic `.htaccess` redirect rather than by trusting `www`, so trusted hosts stay apex-only.

## Follow-ups

- [ ] Live signed-in checks: password and Google sign-in, both Google flows, role restrictions, and the booking, reports, settings and attendance workspaces. No sign-in reached the site during this session.
- [ ] Send a real test email (only on the user's instruction) and confirm delivery.
- [x] Redirect `www.casaparaiso.site` to the bare domain. A `public/.htaccess` rule now sends `www.` hosts to the bare HTTPS domain (301, path and query kept). It is deployed and verified on the live site and in local Apache, and the full suite (483 tests), asset build and Docker startup passed.
- [ ] Recheck the domain registration email warning in hPanel.
- [ ] The user revokes the temporary deployment SSH key in hPanel once the host work is finished.
