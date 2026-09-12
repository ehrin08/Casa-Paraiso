# Casa Paraiso Spa Appointment and Management System

Casa Paraiso is a responsive Laravel web application for appointment scheduling, customer records, therapist availability, transactions, attendance, feedback sentiment, RFM rewards, and operational reporting.

## Stack

- Laravel 12 and Blade
- Tailwind CSS, Alpine.js, Turbo Drive, and Vite
- MySQL 8.4
- Docker Desktop: Apache/PHP 8.2, MySQL, and Mailpit

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

See [the Docker workflow](docs/DOCKER_WORKFLOW.md), [Render and Oracle deployment](docs/RENDER_ORACLE_DEPLOYMENT.md), [project memory](docs/PROJECT_MEMORY.md), and [AGENTS.md](AGENTS.md) for operational rules.
