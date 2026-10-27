# Casa Paraiso Agent Notes

## Project Direction

Casa Paraiso is a Laravel 12, Blade, Tailwind, and MariaDB web application. The repository contains the customer, receptionist, therapist, administrator, and super-administrator workspaces for spa scheduling, transactions, attendance, feedback, RFM rewards, and reports.

Earlier client, API, database, and hosted-pilot integrations are retired. The archived predecessor repository and its databases remain rollback evidence and must not be changed from this repository.

## Required Reading

For every task, read this file, then `docs/PROJECT_MEMORY.md` once; reuse their contents while unchanged. Then read `docs/HOME.md`, the three newest entries in `docs/notes/SESSIONS.md`, and any linked notes relevant to the task. Read only task-relevant sections of authoritative documents and representative tests. Documentation-only work does not require application-test inspection. Update project memory with durable changes to routes, models, migrations, services, Docker workflow, or verification steps.

Primary documents are `docs/MVP_SCOPE.md`, `docs/TECH_STACK.md`, `docs/DATABASE_DESIGN.md`, `docs/SCREEN_FLOW.md`, `docs/DOCKER_WORKFLOW.md`, `docs/HOSTINGER_DEPLOYMENT.md`, `docs/BRAND_UI_GUIDE.md`, and `docs/SECURITY_HARDENING.md`. `docs/archive/` holds finished or retired documents; read them only for history.

## Obsidian Knowledge Base

- The Obsidian vault is `C:\Obsidian`; its `Casa Paraiso` folder is a junction to `docs/`, so `docs/` stays the source of truth.
- `docs/HOME.md` indexes the documentation. Link every new document from it.
- At the end of every task, including documentation-only work, write `docs/notes/sessions/YYYY-MM-DD-<short-slug>.md` with front matter (`type: session-log`, `date`) and sections for Task, What Changed, Verification, Decisions, and Follow-ups. Add its link to the top of `docs/notes/SESSIONS.md`.
- Use relative Markdown links, not `[[wikilinks]]`, so notes render on GitHub.
- Never record secrets, credentials, or `.env` values in notes.

## Efficient Agent Workflow

- Start with a focused question and the narrowest useful directory. Expand discovery only to resolve missing dependencies or unanswered questions.
- Use `rg` for code search and standard file and shell tools. Reuse source already returned; fetch exact source when needed for edits or evidence.
- Batch independent reads and searches. Retrieve only relevant tool definitions and avoid repeated metadata dumps.
- Keep generated output, dependencies, logs, and archived material out of ordinary discovery unless the task concerns them.
- After a permanent tool failure, use an available supported alternative rather than retrying an unchanged call.
- Stop exploration once implementation and verification needs are established; investigate further when new evidence warrants it.
- Keep progress updates concise. Report the outcome, verification, and remaining limitations at delivery.

## Local Runtime

- Use Docker Desktop's `desktop-linux` context through `scripts\casa-docker.ps1`.
- The Compose project is `casa-paraiso-web` with `app`, `mariadb`, and `mailpit` services.
- Run `./scripts/casa-docker.ps1 setup` for a clean checkout; it installs dependencies, creates the ignored `.env` when needed, creates an app key, migrates, and builds web assets. It never seeds or wipes the database.
- Use `./scripts/casa-docker.ps1 compose exec -T app php artisan ...` for Artisan and `./scripts/casa-docker.ps1 compose exec -T app npm run build` for assets.

## Database Safety

- MariaDB 10.11 LTS is the application and test database. `phpunit.xml` targets the isolated `testing` database.
- Never run `migrate:fresh`, `db:wipe`, truncation, or bulk deletion against an account-bearing database. Those commands are allowed only against the dedicated testing database.
- Preserve existing accounts and business data. Use additive migrations and idempotent seeders.
- Laravel retains the `mysql` connection and `pdo_mysql` driver for MariaDB; local `DB_HOST` is `mariadb`.
- Any one-time transfer from the archived source must use uncommitted credentials, a read-only source, checked backups, dry-run validation, and a separately verified MariaDB target. Never commit exports, CA files, or credentials.
- Follow `docs/MARIADB_MIGRATION.md` for existing MySQL installations. Never mount the old `mysql-data` volume into MariaDB or delete it during cutover.

## Application Rules

- Keep the application server-rendered. Alpine.js and Turbo may enhance safe web interactions; state-changing actions remain normal Laravel requests.
- Keep calendar mutation workflows on web form routes. Role-scoped JSON calendar feeds remain web support endpoints, not a public API contract.
- Use `page-heading`, `stat-strip`, `list-toolbar`, `table-shell`, and `pagination.compact`; page size is `casa.pagination.per_page = 15`.
- Booking is Asia/Manila, 1:00 PM through midnight, with 30-minute starts. Confirmed appointments reserve therapist capacity transactionally.
- Keep browser attendance camera access limited to the attendance page.

## Verification and Delivery

- Every delivery includes its session note and `docs/notes/SESSIONS.md` entry; these are documentation and do not trigger application checks.
- Documentation-only changes: check accuracy, referenced paths, instruction consistency, and whitespace errors with `git diff --check`. Skip application tests, asset builds, and Docker startup.
- Application changes: verify relevant tests first, then the full Laravel suite (`php artisan test`), asset build (`npm run build`), and Docker startup path through the existing Docker wrapper.
- Reuse successful checks within the same task while their relevant code and environment remain unchanged. Rerun checks when subsequent changes or failures invalidate their evidence. Investigate a failed targeted test and rerun it after correction before proceeding to the full verification gate; report unresolved failures.
- Do not expose secrets in source, logs, documentation, commits, or the browser.
- Commit only scoped changes. The fresh web-only history is published to `ehrin08/Casa-Paraiso`; the prior remote remains an archive.
