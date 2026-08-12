# Casa Paraiso Docker Workflow

## Services

The Docker Desktop `desktop-linux` Compose project is `casa-paraiso-web`.

- `app`: Apache/PHP 8.2 with Composer, Node 24, and required Laravel extensions at `http://localhost:18001`.
- `mysql`: MySQL 8.4 at `127.0.0.1:13306`; database `casa_paraiso`.
- `mailpit`: SMTP at `127.0.0.1:11026`; dashboard at `http://localhost:18025`.

All ports bind to loopback. Named volumes preserve MySQL data, container-side dependencies,
and Laravel logs. The application source is a Windows bind mount, so `storage/logs` uses its
own Docker-managed volume; the app entrypoint assigns it to Apache's `www-data` user. Logging
channel file modes are managed by that volume rather than passed to Monolog.

## First Setup

```powershell
.\scripts\casa-docker.ps1 setup
```

Setup builds and starts the services, creates the ignored `.env` from `.env.example` when needed, installs Composer and npm dependencies, generates a missing app key, runs normal migrations, and builds assets. It never seeds, truncates, or wipes a database.

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

## Data Cutover

The archived database is the historical source and is never modified by this repository. Before importing its data into a new MySQL database, take a checksummed source backup, make the source read-only, and use a temporary uncommitted transfer harness with supplied credentials. Validate source/target counts, fingerprints, foreign keys, account/profile totals, and MySQL auto-increment values before allowing application writes.

Never run destructive reset commands against a database that contains real accounts or business records. The dedicated `testing` database is the only target for destructive test migrations.
