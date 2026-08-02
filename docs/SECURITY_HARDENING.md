# Security Hardening Checklist

## Application

- [x] Enforce authenticated, active, verified, and role-scoped web workspaces.
- [x] Rate-limit password, recovery, and Google OAuth entry points.
- [x] Use CSRF protection for state-changing web forms.
- [x] Send security headers and restrict browser camera access to the attendance scanner.
- [x] Keep secrets, backups, source exports, and local environments out of Git.

## MySQL

- [x] Use MySQL 8.4 with `utf8mb4_unicode_ci` and strict Laravel connection settings.
- [x] Keep the production connection and PHPUnit `testing` connection separate.
- [ ] Configure production MySQL credentials with least-privilege application access, automated backups, encrypted transport where hosted, and an accepted restore rehearsal.
- [ ] Before a historical data import, take a checksummed read-only source backup and verify IDs, credentials, counts, fingerprints, foreign keys, and auto-increment positions.

## Release Checks

- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, approved `APP_URL`, trusted hosts, secure cookies, and HTTPS before deployment.
- [ ] Verify guest, customer, receptionist, therapist, administrator, and super-administrator workflows.
- [ ] Run `composer audit`, the Laravel test suite, and the web asset build.
- [ ] Confirm no retired client, public API, alternate database, hosted-pilot, or secret artifacts are present.
