# Security Hardening Checklist

## Application

- [x] Enforce authenticated, active, verified, and role-scoped web workspaces.
- [x] Rate-limit password, recovery, and Google OAuth entry points.
- [x] Use CSRF protection for state-changing web forms.
- [x] Send security headers and restrict browser camera access to the attendance scanner.
- [x] Keep secrets, backups, source exports, and local environments out of Git.
- Capture HTTP 4xx/5xx responses with a server-generated request reference. End-user error pages must not expose exception messages, traces, request payloads, query strings, headers, cookies, sessions, raw IP addresses, SQL bindings, or credentials.
- Restrict the technical system-error inbox to the configured super-administrator account. Store sanitized diagnostic text and argument-free stack frames encrypted at rest.

## MariaDB

- [x] Use MariaDB 10.11 locally with `utf8mb4_unicode_ci` and strict Laravel `mysql`/PDO settings; verify the actual Hostinger version separately.
- [x] Keep the production connection and PHPUnit `testing` connection separate.
- [ ] Configure production MariaDB credentials with least-privilege application access, automated backups, encrypted transport where hosted, and an accepted restore rehearsal.
- [ ] Before a historical data import, take a checksummed read-only source backup and verify IDs, credentials, counts, fingerprints, foreign keys, and auto-increment positions.

## Release Checks

- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, approved `APP_URL`, trusted hosts, secure cookies, and HTTPS before deployment.
- [ ] Verify guest, customer, receptionist, therapist, administrator, and super-administrator workflows.
- [x] Local release checks on 2026-10-26: clean `composer audit`, 483 passing Laravel tests, 78 passing JavaScript tests, and successful web asset build. Re-run when relevant code/dependencies change.
- [ ] Require a clean full npm audit, verify the Hostinger archive/checksum, and run `php artisan casa:check-hosting` with private production settings. Verify real email, both Google callbacks, cron, HTTPS and recovery separately; see `HOSTINGER_DEPLOYMENT.md`.
- [ ] Confirm no retired client, public API, alternate database, hosted-pilot, or secret artifacts are present.
- Verify the production scheduler or the once-daily fallback prune path is active so system-error occurrence retention remains bounded.
