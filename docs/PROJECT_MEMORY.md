# Casa Paraiso Project Memory

> Last reviewed: 2026-09-03
> Role: orientation and routing; verify current source before changing behavior.

## Current Shape

Casa Paraiso is a Laravel 12 server-rendered web application using MySQL 8.4. It supports customer, receptionist, therapist, administrator, and super-administrator workspaces. Core modules are services, staff and customers, weekly availability, calendar appointments, transactions, attendance, commissions, feedback and sentiment, RFM rewards, settings, reports, and CSV export.

Earlier native-client, versioned API, device-token, database, tunnel, and hosted-pilot integrations are retired. The previous repository and database infrastructure remain archival rollback sources only.

## Authority Map

| Question | Source |
| --- | --- |
| Mandatory safety, environment, agent workflow, and verification rules | `AGENTS.md` |
| Product scope | `MVP_SCOPE.md` |
| Stack and database runtime | `TECH_STACK.md`, `DOCKER_WORKFLOW.md` |
| Data design and invariants | `DATABASE_DESIGN.md` |
| Role workflows and UI | `SCREEN_FLOW.md`, `BRAND_UI_GUIDE.md` |
| Security release gate | `SECURITY_HARDENING.md` |

## Entry Points

- `routes/web.php` and `routes/auth.php` own all HTTP surfaces.
- `SystemErrorResponseFinalizer`, `SystemErrorRecorder`, and `SystemErrorRetention` centralize request references, safe 4xx/5xx rendering, super-admin error reporting, and bounded retention.
- Role controllers are under `app/Http/Controllers/{Admin,Reception,Staff,Customer}`.
- Business invariants are implemented in services including `AppointmentWorkflow`, `AppointmentAvailability`, `AppointmentCompletion`, `TransactionWorkflow`, `AttendanceWorkflow`, `RfmPromotionGenerator`, and `SentimentClassifier`.
- `config/casa.php` owns business hours, pagination, reward presets, and security switches.
- The public landing page displays active `Service` records when available. If none are active, it displays the four `casa.service_packages` as informational reference cards only; fallback packages are never bookable or written to the database.
- Admin Settings stores optional plain-text company history and a role-based Owner → Administrator → Team organizational chart. The landing page exposes one About section and navigation link only after an admin publishes at least one of those content areas; it never derives public chart entries from staff accounts.
- Admin Settings accepts the browser's nested organizational-photo removal payload, preserves existing chart photos across text-only updates, and displays validation failures instead of silently returning to fallback defaults. Reward-settings updates preserve unrelated application-setting defaults when creating the singleton row.
- `tests/Feature` contains the primary behavioral coverage.
- Super-administrator error inbox routes are `admin.system-errors.index`, `admin.system-errors.show`, `admin.system-errors.resolve`, and `admin.system-errors.reopen`; no current business route is intentionally marked unavailable.
- Customer self-bookings create retained database notifications for the assigned therapist and active admins, super admins, and receptionists. Operational users access the inbox at `notifications.index`; opening an item marks it read and redirects to the recipient's scoped appointment detail page.
- Operational sidebars show a live, private-Reverb unread customer-booking count on their Schedule/Appointments link. The browser reconciles through `notifications.bookings.count`, acknowledges displayed schedule pages through `notifications.bookings.read`, and retains the separate inbox without a badge.

## Durable Rules

- MySQL is the runtime and test database. Do not add a second database platform configuration to the application.
- `phpunit.xml` always targets `mysql/testing`; never use destructive test commands against real data.
- Appointment capacity is transactional. Asia/Manila hours are 1:00 PM through midnight with 30-minute starts.
- Continue using Blade, shared workspace components, and fixed 15-record pagination.
- Browser attendance camera access is limited to the staff attendance page.
- Existing account and business data must be preserved; source imports require an external, read-only cutover process and are never committed.
- Local demo data uses `http://localhost:18001`, service `app`, and additive `php artisan db:seed` only on a verified disposable local database; never use `migrate:fresh` or `db:wipe` for demo setup.
- The app source is bind-mounted from Windows for local Docker. `storage/logs` is a Docker-managed `storage-logs` volume initialized for `www-data`; the `single` and `daily` Monolog channels leave file modes to that volume instead of requesting `chmod`.
- Apache and Laravel Reverb run under Supervisor in the `app` container. Apache proxies the same-origin `/app` and `/apps` WebSocket paths to Reverb on loopback; production Reverb credentials and allowed origins are provider-managed secrets.
- Production deployment uses the separate `casa-paraiso-web` Render service from `main` and an Oracle Cloud Always Free MySQL HeatWave DB System over TLS. `Dockerfile.render`, `docker/app/render-entrypoint.sh`, `render.yaml`, and `RENDER_ORACLE_DEPLOYMENT.md` own this workflow; the archived `casa-paraiso-supabase-api-poc` service must remain unchanged. Render secrets and Oracle CA files are only configured in provider dashboards. `RUN_MIGRATIONS` is false except for one controlled deploy that runs additive `migrate --force`.

## Verification

Follow [AGENTS.md](../AGENTS.md#verification-and-delivery) for check selection and reuse. The commands below are runtime references; `setup` is for a clean checkout as described in [DOCKER_WORKFLOW.md](DOCKER_WORKFLOW.md#first-setup).

```powershell
.\scripts\casa-docker.ps1 setup
.\scripts\casa-docker.ps1 compose exec -T app php artisan test
.\scripts\casa-docker.ps1 compose exec -T app npm run build
```
