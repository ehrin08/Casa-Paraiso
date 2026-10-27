# Casa Paraiso Spa Appointment and Management System

Casa Paraiso is a responsive Laravel web application for appointment scheduling, customer records, therapist availability, transactions, attendance, feedback sentiment, RFM rewards, and operational reporting.

## Stack

- Laravel 12 and Blade
- Tailwind CSS, Alpine.js, Turbo Drive, and Vite
- MariaDB 10.11 LTS
- Docker Desktop: Apache/PHP 8.2, MariaDB, and Mailpit

## Quick start

```powershell
.\scripts\casa-docker.ps1 setup
```

Open `http://localhost:18001`. Mailpit is available at `http://localhost:18025`.

Run tests and build assets with:

```powershell
.\scripts\casa-docker.ps1 compose exec -T app php artisan test
.\scripts\casa-docker.ps1 compose exec -T app npm run build
```

Build the audited Hostinger Unlimited upload package with `.\scripts\casa-hostinger.ps1 package`. It includes production PHP dependencies and compiled assets, with credentials, business data and uploads transferred separately. Follow [Hostinger deployment](docs/HOSTINGER_DEPLOYMENT.md) for the private application/public-directory layout, data preservation, production settings and live verification.

Existing MySQL installations must follow [the MariaDB migration runbook](docs/MARIADB_MIGRATION.md) before setup. See [the Docker workflow](docs/DOCKER_WORKFLOW.md), [project memory](docs/PROJECT_MEMORY.md), and [AGENTS.md](AGENTS.md) for operational rules. Retired plans and deployments are kept in [docs/archive](docs/archive/).
