# Casa Paraiso Docker Workflow

## Services

The Docker Desktop `desktop-linux` Compose project is `casa-paraiso-web`.

- `app`: Apache/PHP 8.2 with Composer, Node 24, and required Laravel extensions at `http://localhost:18001`. Booking badges poll the authenticated count endpoint every 30 seconds while the page is visible; no WebSocket service is required.
- `mariadb`: MariaDB 10.11 LTS at `127.0.0.1:13306`; database `casa_paraiso`.
- `mailpit`: SMTP at `127.0.0.1:11026`; dashboard at `http://localhost:18025`.

All ports bind to loopback. Named volumes preserve MariaDB data, container-side dependencies,
and Laravel logs. The application source is a Windows bind mount, so `storage/logs` uses its
own Docker-managed volume; the app entrypoint assigns it to Apache's `www-data` user. Logging
channel file modes are managed by that volume rather than passed to Monolog.

## First Setup

```powershell
.\scripts\casa-docker.ps1 setup
```

Setup builds and starts the services, creates the ignored `.env` from `.env.example` when needed, installs Composer and npm dependencies, generates a missing app key, runs normal migrations, and builds assets. It never seeds, truncates, or wipes a database.

Existing MySQL checkouts must follow [the MariaDB migration runbook](MARIADB_MIGRATION.md) first. Setup/start reject the old `DB_HOST=mysql` setting so it cannot silently start the application against an unmigrated database. MariaDB uses a new `mariadb-data` volume; the retired `mysql-data` volume remains rollback evidence. Do not use `down --volumes` during migration or recovery.

## Daily Commands

```powershell
.\scripts\casa-docker.ps1 start
.\scripts\casa-docker.ps1 status
.\scripts\casa-docker.ps1 compose exec -T app php artisan migrate
.\scripts\casa-docker.ps1 compose exec -T app php artisan test
.\scripts\casa-docker.ps1 compose exec -T app npm run build
.\scripts\casa-docker.ps1 stop
```

Use the wrapper rather than bare Compose so commands target Docker Desktop and the correct project.

For commands with Compose options, use an explicit argument array: PowerShell can otherwise consume flags such as `-d` or `-c` as script parameters.

```powershell
.\scripts\casa-docker.ps1 -Action compose -ComposeArguments @('up', '-d', 'mariadb')
```

## Data Cutover

The archived database is the historical source and is never modified by this repository. Before importing data into a new MariaDB database, take a checksummed source backup, stop source writes, and use a temporary uncommitted transfer harness with supplied credentials. Validate source/target counts, canonical fingerprints, foreign keys, account/profile totals, financial totals, and auto-increment values before allowing application writes. Use table DDL for sequence values; MySQL information-schema statistics can differ from the values in `SHOW CREATE TABLE`.

Never run destructive reset commands against a database that contains real accounts or business records. The dedicated `testing` database is the only target for destructive test migrations.
