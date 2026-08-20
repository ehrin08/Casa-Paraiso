# Casa Paraiso Project Memory

> Last reviewed: 2026-08-02
> Role: orientation and routing; verify current source before changing behavior.

## Current Shape

Casa Paraiso is a Laravel 12 server-rendered web application using MySQL 8.4. It supports customer, receptionist, therapist, administrator, and super-administrator workspaces. Core modules are services, staff and customers, weekly availability, calendar appointments, transactions, attendance, commissions, feedback and sentiment, RFM rewards, settings, reports, and CSV export.

Earlier native-client, versioned API, device-token, database, tunnel, and hosted-pilot integrations are retired. The previous repository and database infrastructure remain archival rollback sources only.

## Authority Map

| Question | Source |
| --- | --- |
| Mandatory safety and environment rules | `AGENTS.md` |
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
- `tests/Feature` contains the primary behavioral coverage.
- Super-administrator error inbox routes are `admin.system-errors.index`, `admin.system-errors.show`, `admin.system-errors.resolve`, and `admin.system-errors.reopen`; no current business route is intentionally marked unavailable.

## Durable Rules

- MySQL is the runtime and test database. Do not add a second database platform configuration to the application.
- `phpunit.xml` always targets `mysql/testing`; never use destructive test commands against real data.
- Appointment capacity is transactional. Asia/Manila hours are 1:00 PM through midnight with 30-minute starts.
- Continue using Blade, shared workspace components, and fixed 15-record pagination.
- Browser attendance camera access is limited to the staff attendance page.
- Existing account and business data must be preserved; source imports require an external, read-only cutover process and are never committed.
- Local demo data uses `http://localhost:18001`, service `app`, and additive `php artisan db:seed` only on a verified disposable local database; never use `migrate:fresh` or `db:wipe` for demo setup.
- The app source is bind-mounted from Windows for local Docker. `storage/logs` is a Docker-managed `storage-logs` volume initialized for `www-data`; the `single` and `daily` Monolog channels leave file modes to that volume instead of requesting `chmod`.

## Verification

```powershell
.\scripts\casa-docker.ps1 setup
.\scripts\casa-docker.ps1 compose exec -T app php artisan test
.\scripts\casa-docker.ps1 compose exec -T app npm run build
```
