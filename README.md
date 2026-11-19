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

## AI assistance

The team built this repository with generative AI assistance, declared in the capstone's Declaration of AI Use. Claude Code (Anthropic; Claude Opus 5.5 and Claude Fable 5.2) generated or revised much of the application code, tests, and documentation under the direction of Ehrin John Galang, who planned what to build and how, and the team reviewed and tested each change. The team also used Google Antigravity (Gemini 3.1 Pro) and ChatGPT Plus as coding and writing aids. Every Claude Code commit carries a `Co-Authored-By: Claude …` trailer. To list them, run `git log --grep="Co-Authored-By: Claude"`. These trailers record the tool used. They do not make the tool an author. The system's Gemini sentiment feature is part of the application, not a development aid.
