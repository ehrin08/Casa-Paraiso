# Casa Paraiso Agent Notes

## Project Direction

Casa Paraiso is a Laravel 12, Blade, Tailwind, and MySQL web application. The repository contains the customer, receptionist, therapist, administrator, and super-administrator workspaces for spa scheduling, transactions, attendance, feedback, RFM rewards, and reports.

Earlier client, API, database, and hosted-pilot integrations are retired. The archived predecessor repository and its databases remain rollback evidence and must not be changed from this repository.

## Required Reading

For every task, read this file, then `docs/PROJECT_MEMORY.md`, then the relevant authoritative document and representative tests. Update project memory with durable changes to routes, models, migrations, services, Docker workflow, or verification steps.

Primary documents are `docs/MVP_SCOPE.md`, `docs/TECH_STACK.md`, `docs/DATABASE_DESIGN.md`, `docs/SCREEN_FLOW.md`, `docs/DOCKER_WORKFLOW.md`, `docs/IMPLEMENTATION_ROADMAP.md`, `docs/BRAND_UI_GUIDE.md`, and `docs/SECURITY_HARDENING.md`.

## Local Runtime

- Use Docker Desktop's `desktop-linux` context through `scripts\casa-docker.ps1`.
- The Compose project is `casa-paraiso-web` with `app`, `mysql`, and `mailpit` services.
- Run `./scripts/casa-docker.ps1 setup` for a clean checkout; it installs dependencies, creates the ignored `.env` when needed, creates an app key, migrates, and builds web assets. It never seeds or wipes the database.
- Use `./scripts/casa-docker.ps1 compose exec -T app php artisan ...` for Artisan and `./scripts/casa-docker.ps1 compose exec -T app npm run build` for assets.

## Database Safety

- MySQL 8.4 is the application and test database. `phpunit.xml` targets the isolated `testing` database.
- Never run `migrate:fresh`, `db:wipe`, truncation, or bulk deletion against an account-bearing database. Those commands are allowed only against the dedicated testing database.
- Preserve existing accounts and business data. Use additive migrations and idempotent seeders.
- Any one-time transfer from the archived source must use uncommitted credentials, a read-only source, checked backups, dry-run validation, and a separately verified MySQL target. Never commit exports, CA files, or credentials.

## Application Rules

- Keep the application server-rendered. Alpine.js and Turbo may enhance safe web interactions; state-changing actions remain normal Laravel requests.
- Keep calendar mutation workflows on web form routes. Role-scoped JSON calendar feeds remain web support endpoints, not a public API contract.
- Use `page-heading`, `stat-strip`, `list-toolbar`, `table-shell`, and `pagination.compact`; page size is `casa.pagination.per_page = 15`.
- Booking is Asia/Manila, 1:00 PM through midnight, with 30-minute starts. Confirmed appointments reserve therapist capacity transactionally.
- Keep browser attendance camera access limited to the attendance page.

## Verification and Delivery

- Verify relevant tests first, then `php artisan test`, `npm run build`, and the Docker startup path.
- Do not expose secrets in source, logs, documentation, commits, or the browser.
- Commit only scoped changes. The fresh web-only history is published to `ehrin08/Casa-Paraiso`; the prior remote remains an archive.
